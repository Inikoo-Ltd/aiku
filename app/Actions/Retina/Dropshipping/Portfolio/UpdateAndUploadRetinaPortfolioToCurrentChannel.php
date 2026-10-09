<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Jul 2025 11:35:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Portfolio;

use App\Actions\Dropshipping\Ebay\Product\UpdateEbayOffer;
use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\Dropshipping\Shopify\Product\UpdateShopifyProduct;
use App\Actions\Dropshipping\Shopify\Product\UpdateShopifyProductVariant;
use App\Actions\Dropshipping\Wix\Product\UpdateWixProduct;
use App\Actions\Dropshipping\WooCommerce\Product\UpdateWooProduct;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithRetinaCustomerOwnedRouteModels;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\Portfolio;
use App\Traits\SanitizeInputs;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateAndUploadRetinaPortfolioToCurrentChannel extends RetinaAction
{
    use WithRetinaCustomerOwnedRouteModels;
    use AsAction;
    use SanitizeInputs;

    private ?string $shopifyPriceRefusal = null;

    public function handle(Portfolio $portfolio, array $modelData, $isDraft = false): void
    {
        $pricingType  = Arr::pull($modelData, 'pricing_type');
        $pricingValue = Arr::pull($modelData, 'pricing_value');

        if ($pricingType === 'not_follow') {
            data_set($modelData, 'settings.pricing.type', 'not_follow');
            data_set($modelData, 'settings.pricing.value', null);
            data_set($modelData, 'settings.pricing_opt_out', true);
        } elseif ($pricingType !== null && $pricingValue !== null) {
            $basePrice = $portfolio->item instanceof Product ? $portfolio->item->dropshippingBasePrice() : 0;

            $customerPrice = $pricingType === 'percent'
                ? round($basePrice * (1 + $pricingValue / 100), 2)
                : round($basePrice + $pricingValue, 2);

            if ($customerPrice <= 0) {
                throw ValidationException::withMessages([
                    'pricing_value' => __('This adjustment takes the price to zero or below.')
                ]);
            }

            data_set($modelData, 'customer_price', (string) $customerPrice);
            data_set($modelData, 'settings.pricing.type', $pricingType);
            data_set($modelData, 'settings.pricing.value', $pricingValue);
        }

        if (Arr::has($modelData, 'customer_price')) {
            data_set($modelData, 'settings.pricing_opt_out', true);
        }

        $portfolio = UpdatePortfolio::run($portfolio, $modelData);

        if (! $isDraft) {
            match ($portfolio->platform->type) {
                PlatformTypeEnum::EBAY => $this->updateEbayChannel($portfolio),
                PlatformTypeEnum::WOOCOMMERCE => UpdateWooProduct::run($portfolio),
                PlatformTypeEnum::SHOPIFY => $this->updateShopifyChannel($portfolio),
                PlatformTypeEnum::WIX => UpdateWixProduct::run($portfolio),
                default => null
            };
        }
    }

    public function updateEbayChannel(Portfolio $portfolio): void
    {
        $ebayError = UpdateEbayOffer::run($portfolio, withTitle: true);

        if ($ebayError) {
            throw ValidationException::withMessages([
                'title' => __('Saved, but eBay did not accept the change: :reason', ['reason' => $ebayError])
            ]);
        }
    }

    public function updateShopifyChannel(Portfolio $portfolio): void
    {
        [$priced, $priceMessage] = UpdateShopifyProductVariant::run($portfolio);

        if ($portfolio->wasChanged(['customer_product_name', 'customer_description'])) {
            UpdateShopifyProduct::run($portfolio);
        }

        $this->shopifyPriceRefusal = !$priced && $portfolio->platform_product_id ? $priceMessage : null;
    }

    public function rules(): array
    {
        return [
            'customer_product_name' => ['sometimes', 'string', 'max:255'],
            'customer_price' => ['sometimes', 'string', 'numeric'],
            'customer_description' => ['sometimes', 'string', 'max:10000'],
            'pricing_type' => ['sometimes', 'required', Rule::in(['percent', 'fixed', 'not_follow'])],
            'pricing_value' => ['exclude_if:pricing_type,not_follow', 'required_with:pricing_type', 'numeric', 'gte:-100'],
        ];
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $this->setSanitizeFields([
            'title',
            'price',
        ]);
        $this->sanitizeInputs();
        $this->sanitizeHtml('description');

        $this->set('customer_product_name', $request->input('title'));
        if ($request->filled('price')) {
            $this->set('customer_price', (string) $request->input('price'));
        }
        $this->set('customer_description', $request->input('description'));
    }

    public function asController(Portfolio $portfolio, ActionRequest $request): void
    {
        $this->enableSanitize();
        $this->initialisation($request);
        $this->handle($portfolio, $this->validatedData);

        if ($this->shopifyPriceRefusal !== null) {
            throw ValidationException::withMessages([
                'price' => __('Saved, but Shopify did not accept the price: :reason', ['reason' => $this->shopifyPriceRefusal])
            ]);
        }
    }

    public function asDraft(Portfolio $portfolio, ActionRequest $request): void
    {
        $this->enableSanitize();
        $this->initialisation($request);
        $this->handle($portfolio, $this->validatedData, true);
    }

    public string $commandSignature = 'UpdateAndUploadRetinaPortfolioToCurrentChannel {portfolio_id}';

    public function asCommand(Command $command): void
    {
        $portfolio = Portfolio::find($command->argument('portfolio_id'));

        $this->handle($portfolio, [
            'customer_price' => '999'
        ]);
    }
}
