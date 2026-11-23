<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Portfolio;

use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\RetinaAction;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use App\Actions\Dropshipping\Ebay\Product\UpdateEbayOffer;
use App\Actions\Dropshipping\Shopify\Product\UpdateShopifyProduct;
use App\Actions\Dropshipping\WooCommerce\Product\UpdateWooProduct;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use Illuminate\Support\Arr;
use Throwable;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * Only ever run when the customer presses Re-translate on the portfolios they selected: it replaces their own name and
 * description with ours in the channel language where we have a translation, and sends only the texts that changed to
 * their store (Shopify title/description, WooCommerce name/description with its document links and short description,
 * eBay title, never a description): no price is sent and an eBay draft is not published. A product already in their
 * store keeps its handle. When the store refuses, the portfolio goes back to its texts, so My Products never shows a
 * text the store does not have.
 */
class RetranslateRetinaPortfolios extends RetinaAction
{
    public const int MAX_PORTFOLIOS = UnlinkAndDeleteBulkRetinaPortfolio::SYNC_LIMIT;

    private const string SENT = 'sent';
    private const string SAVED = 'saved';

    private CustomerSalesChannel $customerSalesChannel;

    /**
     * @return array{in_store: int, only_my_products: int, refused: array<int, string>}
     */
    public function handle(CustomerSalesChannel $customerSalesChannel, array $portfolioIds): array
    {
        $locale = $customerSalesChannel->productTextsLocale();

        if (!$locale) {
            throw ValidationException::withMessages([
                'portfolios' => __('Choose a language for this channel in its settings first.'),
            ]);
        }

        $result = ['in_store' => 0, 'only_my_products' => 0, 'refused' => []];

        $portfolios = Portfolio::where('customer_sales_channel_id', $customerSalesChannel->id)
            ->whereIn('id', $portfolioIds)
            ->where('item_type', class_basename(Product::class))
            ->where('is_bundle', false)
            ->with('item')
            ->get();

        foreach ($portfolios as $portfolio) {
            $texts = $this->translatedTexts($portfolio, $locale);

            if ($texts === []) {
                continue;
            }

            $before = $portfolio->only(['customer_product_name', 'customer_description']);

            if ($portfolio->platform_product_id) {
                data_set($texts, 'platform_handle', $portfolio->platform_handle);
            }

            $portfolio = UpdatePortfolio::run($portfolio, $texts);

            try {
                $outcome = $this->sendToStore($portfolio, Arr::has($texts, 'customer_product_name'), Arr::has($texts, 'customer_description'));
            } catch (Throwable $exception) {
                $outcome = $exception->getMessage();
            }

            if ($outcome === self::SENT) {
                $result['in_store']++;
            } elseif ($outcome === self::SAVED) {
                $result['only_my_products']++;
            } else {
                $portfolio->forceFill($before)->save();
                $result['refused'][] = $portfolio->item_code.': '.$outcome;
            }
        }

        return $result;
    }

    /**
     * @return string SENT, SAVED (nothing of ours in the store to change) or the store's refusal
     */
    public function sendToStore(Portfolio $portfolio, bool $withTitle, bool $withDescription): string
    {
        if (!$portfolio->platform_product_id) {
            return self::SAVED;
        }

        $isLiveOrDraft = $portfolio->platform_status || Arr::get($portfolio->data, 'is_platform_draft');

        switch ($portfolio->platform->type) {
            case PlatformTypeEnum::SHOPIFY:
                if ($portfolio->isShopifyVariantAdopted()) {
                    return self::SAVED;
                }
                [$sent, $message] = UpdateShopifyProduct::run($portfolio, $withTitle, $withDescription);

                return $sent ? self::SENT : $message;

            case PlatformTypeEnum::EBAY:
                if (!$withTitle || !$isLiveOrDraft) {
                    return self::SAVED;
                }

                return UpdateEbayOffer::run($portfolio, withTitle: true, titleOnly: true) ?? self::SENT;

            case PlatformTypeEnum::WOOCOMMERCE:
                if (!$portfolio->platform_status) {
                    return self::SAVED;
                }
                $fields = array_keys(array_filter(['name' => $withTitle, 'description' => $withDescription]));

                return UpdateWooProduct::run($portfolio, $fields) ? self::SENT : __('WooCommerce did not take the change');

            default:
                return self::SAVED;
        }
    }

    /**
     * Only the texts we have in the channel language that read differently from what the customer sees now: a missing
     * translation leaves their text as it is, so a description is never blanked.
     *
     * @return array<string, string>
     */
    public function translatedTexts(Portfolio $portfolio, string $locale): array
    {
        /** @var Product $product */
        $product = $portfolio->item;

        $shown = [
            'customer_product_name' => $portfolio->customer_product_name ?? $product?->name,
            'customer_description'  => $portfolio->customer_description ?? $product?->description,
        ];

        $texts = [];
        foreach (['customer_product_name' => 'name_i8n', 'customer_description' => 'description_i8n'] as $column => $field) {
            $translation = $product?->getTranslation($field, $locale, false);
            if (filled($translation) && UpdateAndUploadRetinaPortfolioToCurrentChannel::readableText($translation) !== UpdateAndUploadRetinaPortfolioToCurrentChannel::readableText($shown[$column])) {
                $texts[$column] = $translation;
            }
        }

        return $texts;
    }

    public function rules(): array
    {
        return [
            'portfolios'   => ['required', 'array', 'max:'.self::MAX_PORTFOLIOS],
            'portfolios.*' => ['required', 'integer'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        return $this->customerSalesChannel->customer_id == $this->customer->id
            && $this->customerSalesChannel->shop->customersChooseChannelLanguage();
    }

    /**
     * @return array{in_store: int, only_my_products: int, refused: array<int, string>}
     */
    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): array
    {
        $this->customerSalesChannel = $customerSalesChannel;
        $this->initialisation($request);

        return $this->handle($customerSalesChannel, $this->validatedData['portfolios']);
    }
}
