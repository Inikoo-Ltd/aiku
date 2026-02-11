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
use App\Actions\Helpers\Images\GetImgProxyUrl;
use App\Actions\RetinaAction;
use App\Actions\Traits\HasBucketAttachment;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Enums\Ordering\PlatformLogs\PlatformPortfolioLogsStatusEnum;
use App\Enums\Ordering\PlatformLogs\PlatformPortfolioLogsTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\WooCommerceUser;
use App\Models\Helpers\Media;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreWooCommerceProduct extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use HasBucketAttachment;

    /**
     * @throws \Exception
     */
    public function handle(WooCommerceUser $wooCommerceUser, Portfolio $portfolio)
    {
        $logs = StorePlatformPortfolioLog::run($portfolio, [
            'type' => PlatformPortfolioLogsTypeEnum::UPLOAD
        ]);

        try {
            /** @var Product $product */
            $product = $portfolio->item;
            $customerSalesChannel = $wooCommerceUser->customerSalesChannel;
            $website = $customerSalesChannel?->shop?->website;

            $images = [];
            if (app()->isProduction()) {
                foreach ($product->images as $image) {
                    $images[] = [
                        'src' => GetImgProxyUrl::run($image->getImage()->extension('jpg'))
                    ];
                }
            }

            $customAttributes = [];
            $tradeUnitAttachments = Arr::get($this->getAttachmentData($product), 'public', []);
            foreach ($tradeUnitAttachments as $key => $tradeUnitAttachment) {
                /** @var Media|null $attachment */
                $attachment = Arr::get($tradeUnitAttachment, 'attachment');

                if ($attachment) {
                    $customAttributes[] = [
                        'id' => (string)$attachment->id,
                        'name' => '<strong>' . Arr::get($tradeUnitAttachment, 'label') . '</strong>',
                        'option' => '<a href="https://' . $website?->domain . '/attachment/'.$attachment->ulid.'/download' . '">' .
                            Arr::get($tradeUnitAttachment, 'label') . '</a>'
                    ];
                }
            }

            $attachmentLinks = '';
            foreach ($customAttributes as $attr) {
                $attachmentLinks .= $attr['name'] . ': ' . $attr['option'] . '<br>';
            }

            $description = $portfolio->customer_description . '<br><br>' . $attachmentLinks;

            $availableQuantity = $product->available_quantity;

            if ($customerSalesChannel->max_quantity_advertise > 0) {
                $availableQuantity = min($availableQuantity, $customerSalesChannel->max_quantity_advertise);
            }

            $attributes = [];
            $dimensions = [];
            $w = Arr::get($product->marketing_dimensions, 'w');
            $h = Arr::get($product->marketing_dimensions, 'h');
            $l = Arr::get($product->marketing_dimensions, 'l');

            if ($w && $h && $l) {
                $dimensions = [
                    'width' => (string) $w,
                    'height' => (string) $h,
                    'length' => (string) $l
                ];
            }

            if($product->country_of_origin) {
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

            if (! blank($customAttributes)) {
                foreach ($customAttributes as $key => $attr) {
                    $attributes[] = [
                        'id' => 0,
                        'name' => $attr['name'],
                        'position' => $key + 1,
                        'visible' => true,
                        'variation' => false,
                        'options' => [$attr['option']]
                    ];
                }
            }

            $ingredients = explode(',', $product->marketing_ingredients);

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
                'manage_stock'      => !is_null($availableQuantity),
                'stock_status'      => Arr::get($product, 'stock_status', 'instock'),
                'sku'               => $portfolio->sku,
                'weight'            => (string)($product->gross_weight / 1000),
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

            $result = $wooCommerceUser->createWooCommerceProduct($wooCommerceProduct);

            if (is_string(Arr::get($result, '0'))) {
                if (json_decode(Arr::get($result, '0', ''), true)['code'] === 'product_invalid_sku') {
                    throw new \Exception(trans('Invalid or duplicated SKU: SKU already exists'));
                }
            }

            UpdatePortfolio::run($portfolio, [
                'platform_product_id'         => Arr::get($result, 'id'),
                'platform_product_variant_id' => Arr::get($result, 'id'),
            ]);

            CheckWooPortfolio::run($portfolio, []);

            $portfolio->refresh();

            if ($portfolio->platform_status) {
                UpdatePlatformPortfolioLog::run($logs, [
                    'status' => PlatformPortfolioLogsStatusEnum::OK
                ]);
            } else {
                UpdatePlatformPortfolioLog::run($logs, [
                    'status' => PlatformPortfolioLogsStatusEnum::FAIL,
                    'response' => $result
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            // Sentry::captureMessage("Failed to upload product due to: " . $e->getMessage());

            UpdatePortfolio::run($portfolio, [
                'errors_response' => [
                    'message' => $e->getMessage()
                ]
            ]);

            if ($logs) {
                UpdatePlatformPortfolioLog::run($logs, [
                    'status'   => PlatformPortfolioLogsStatusEnum::FAIL,
                    'response' => $e->getMessage()
                ]);
            }



            return null;
        }
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
}
