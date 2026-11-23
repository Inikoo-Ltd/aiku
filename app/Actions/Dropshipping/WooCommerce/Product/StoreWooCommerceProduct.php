<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Product;

use App\Actions\Dropshipping\Portfolio\Logs\StorePlatformPortfolioLog;
use App\Actions\Dropshipping\Portfolio\Logs\UpdatePlatformPortfolioLog;
use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\Dropshipping\WithPortfolioErrorResponse;
use App\Actions\Helpers\Images\GetImgProxyUrl;
use App\Actions\RetinaAction;
use App\Actions\Traits\HasBucketAttachment;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Enums\Ordering\PlatformLogs\PlatformPortfolioLogsStatusEnum;
use App\Enums\Ordering\PlatformLogs\PlatformPortfolioLogsTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\WooCommerceUser;
use App\Helpers\DimensionsFormatter;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreWooCommerceProduct extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use HasBucketAttachment;
    use WithPortfolioErrorResponse;

    /**
     * The store downloads every product image while it creates the product, so a slow host
     * needs far longer than the 30 seconds the probes and stock pushes are allowed.
     */
    public const int CREATE_TIMEOUT_SECONDS = 120;

    /**
     * @throws \Exception
     */
    public function handle(WooCommerceUser $wooCommerceUser, Portfolio $portfolio): Portfolio
    {
        $logs = StorePlatformPortfolioLog::run($portfolio, [
            'type' => PlatformPortfolioLogsTypeEnum::UPLOAD
        ]);

        try {
            /** @var Product $product */
            $product = $portfolio->item;
            $customerSalesChannel = $wooCommerceUser->customerSalesChannel;

            $images = [];
            if (app()->isProduction()) {
                foreach ($product->orderedImages() as $image) {
                    $images[] = [
                        'src' => GetImgProxyUrl::run($image->getImage()->extension('jpg')->resize(1600, 1600))
                    ];
                }
            }

            $description = $this->descriptionWithAttachments($portfolio);

            $availableQuantity = UpdateWooCustomerSalesChannelPortfolio::quantityToSend($product, $customerSalesChannel);

            $attributes = [];
            $dimensions = [];
            $inStoreUnit = DimensionsFormatter::fromMetres([...($product->marketing_dimensions ?? []), 'units' => self::dimensionUnit($wooCommerceUser)]);
            $w = Arr::get($inStoreUnit, 'w');
            $h = Arr::get($inStoreUnit, 'h');
            $l = Arr::get($inStoreUnit, 'l');

            if ($w && $h && $l) {
                $dimensions = [
                    'width' => (string) $w,
                    'height' => (string) $h,
                    'length' => (string) $l
                ];
            }

            if ($product->country_of_origin) {
                $attributes = [
                    [
                        'id' => 0,
                        'name' => 'Country of Origin',
                        'position' => 0,
                        'visible' => true,
                        'variation' => false,
                        'options' => [$product->country_of_origin]
                    ]
                ];
            }

            $ingredients = array_values(array_filter(array_map('trim', explode(',', (string) $product->marketing_ingredients))));

            if (! blank($ingredients)) {
                $attributes[] = [
                    'id' => 0,
                    'name' => 'Ingredients',
                    'position' => count($attributes) + 1,
                    'visible' => true,
                    'variation' => false,
                    'options' => array_values($ingredients)
                ];
            }

            $weightOption = Arr::get($wooCommerceUser->settings, 'weight_option', 'kg');

            $weight = match ($weightOption) {
                'kg' => round($product->gross_weight / 1000, 3),
                'oz' => round($product->gross_weight * 0.035274, 2),
                'lbs' => round($product->gross_weight * 0.00220462, 2),
                default => $product->gross_weight
            };

            $wooCommerceProduct = [
                'name'              => $portfolio->customer_product_name,
                'type'              => 'simple',
                'regular_price'     => (string)$portfolio->customer_price,
                'description'       => $description,
                'short_description' => $description,
                'global_unique_id'  => $product->barcode,
                'categories'        => [],
                'images'            => $images,
                'stock_quantity'    => $availableQuantity,
                'manage_stock'      => true,
                'stock_status'      => $availableQuantity > 0 ? 'instock' : 'outofstock',
                'sku'               => $portfolio->sku,
                'weight'            => (string) $weight,
                'dimensions'        => $dimensions,
                'status'            => $this->mapProductStateToWooCommerce($product->status->value),
                'attributes'        => $attributes
            ];

            $isOnDemand = false;
            foreach ($product->orgStocks as $orgStock) {
                if ($orgStock->is_on_demand) {
                    $isOnDemand = true;
                }
            }

            if ($isOnDemand) {
                data_set($wooCommerceProduct, 'backorders', 'yes');
            }

            $wooCommerceUser->setTimeout(self::CREATE_TIMEOUT_SECONDS);
            $result = $wooCommerceUser->createWooCommerceProduct($wooCommerceProduct);

            if (is_string(Arr::get($result, '0'))) {
                if (Arr::get(json_decode(Arr::get($result, '0', ''), true), 'code') === 'product_invalid_sku') {

                    $duplicatedProducts = $wooCommerceUser->getWooCommerceProducts([
                        'sku' => $portfolio->sku
                    ]);

                    $result = collect($duplicatedProducts ?? [])
                        ->first(fn ($duplicatedProduct) => is_array($duplicatedProduct) && strcasecmp((string) Arr::get($duplicatedProduct, 'sku'), (string) $portfolio->sku) === 0)
                        ?? $result;
                }
            }

            UpdatePortfolio::run($portfolio, [
                'platform_product_id'         => Arr::get($result, 'id'),
                'platform_product_variant_id' => Arr::get($result, 'id'),
            ]);

            CheckWooPortfolio::run($portfolio, is_array($result) ? $result : null);

            $portfolio->refresh();

            if ($portfolio->platform_status) {
                UpdatePortfolio::run($portfolio, [
                    'errors_response' => null
                ]);

                UpdatePlatformPortfolioLog::run($logs, [
                    'status' => PlatformPortfolioLogsStatusEnum::OK
                ]);
            } else {
                UpdatePortfolio::run($portfolio, [
                    'errors_response' => $this->portfolioErrorResponse($result)
                ]);

                UpdatePlatformPortfolioLog::run($logs, [
                    'status' => PlatformPortfolioLogsStatusEnum::FAIL,
                    'response' => $result
                ]);
            }

            return $portfolio;
        } catch (\Exception $e) {
            // Sentry::captureMessage("Failed to upload product due to: " . $e->getMessage());

            UpdatePortfolio::run($portfolio, [
                'errors_response' => $this->portfolioErrorResponse($e->getMessage())
            ]);

            if ($logs) {
                UpdatePlatformPortfolioLog::run($logs, [
                    'status'   => PlatformPortfolioLogsStatusEnum::FAIL,
                    'response' => $e->getMessage()
                ]);
            }



            return $portfolio;
        }
    }

    /**
     * The customer's description followed by the links to the product's public documents (SDS, IFRA, CPSR...), as the
     * store shows it in both the description and the short description.
     */
    public function descriptionWithAttachments(Portfolio $portfolio): string
    {
        /** @var Product $product */
        $product = $portfolio->item;
        $website = $portfolio->customerSalesChannel?->shop?->website;

        $attachmentLinks = '';
        foreach (Arr::get($this->getAttachmentData($product), 'public', []) as $tradeUnitAttachment) {
            /** @var array{id: int, ulid: string}|null $attachment */
            $attachment = Arr::get($tradeUnitAttachment, 'attachment');

            if ($attachment) {
                $label           = Arr::get($tradeUnitAttachment, 'label');
                $attachmentLinks .= '<strong>'.$label.'</strong>: <a href="https://'.$website?->domain.'/attachment/'.$attachment['ulid'].'/download">'.$label.'</a><br>';
            }
        }

        return $portfolio->customer_description.'<br><br>'.$attachmentLinks;
    }

    private function mapProductStateToWooCommerce($status): string
    {
        $stateMap = [
            ProductStatusEnum::FOR_SALE->value     => 'publish',
            ProductStatusEnum::DISCONTINUED->value => 'pending',
            ProductStatusEnum::IN_PROCESS->value   => 'draft',
            ProductStatusEnum::OUT_OF_STOCK->value => 'draft',
            ProductStatusEnum::COMING_SOON->value => 'draft'
        ];

        return $stateMap[$status] ?? 'draft';
    }

    /**
     * The store's own dimension unit, read by CheckWooChannel; a store not checked yet gets WooCommerce's default, cm.
     */
    public static function dimensionUnit(WooCommerceUser $wooCommerceUser): string
    {
        return match (Arr::get($wooCommerceUser->settings, 'dimension_option')) {
            'm', 'mm' => Arr::get($wooCommerceUser->settings, 'dimension_option'),
            'in'      => 'inch',
            default   => 'cm',
        };
    }
}
