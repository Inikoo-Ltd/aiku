<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 10 Mar 2025 16:53:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Tiktok\Product;

use App\Actions\Dropshipping\Portfolio\Logs\StorePlatformPortfolioLog;
use App\Actions\Dropshipping\Portfolio\Logs\UpdatePlatformPortfolioLog;
use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\PlatformLogs\PlatformPortfolioLogsStatusEnum;
use App\Enums\Ordering\PlatformLogs\PlatformPortfolioLogsTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\TiktokUser;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreProductToTiktok extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(TiktokUser $tiktokUser, Portfolio $portfolio): array
    {
        $logs = StorePlatformPortfolioLog::run($portfolio, [
            'type' => PlatformPortfolioLogsTypeEnum::UPLOAD
        ]);

        try {
            /** @var Product $product */
            $product = $portfolio->item;

            $productImages = [];
            foreach ($product->images as $image) {
                $productImage = UploadProductImageToTiktok::run($tiktokUser, $image);

                $productImages[] = [
                    'uri' => Arr::get($productImage, 'data.uri')
                ];
            }

            $w = max(Arr::get($product->marketing_dimensions, 'w', 1), 20);
            $h = max(Arr::get($product->marketing_dimensions, 'h', 1), 20);
            $l = max(Arr::get($product->marketing_dimensions, 'l', 1), 80);

            $productData = [
                'title' => $portfolio->customer_product_name,
                'description' => $portfolio->customer_description,
                'price' => (string) $portfolio->customer_price,
                'category_id' => "2348816",
                'main_images' => $productImages,
                'package_weight' => [
                    'value' => (string) ($product->gross_weight / 1000),
                    'unit' => 'KILOGRAM'
                ],
                'package_dimensions' => [
                    'width' => (string) ceil($w),
                    'length' => (string) ceil($l),
                    'height' => (string) ceil($h),
                    'unit' => "CENTIMETER",
                ],
                'external_product_id' => (string) $portfolio->id,
                'product_attributes' => [
                    [
                        'id' => "101710",
                        'values' => [
                            [
                                'id' => "1000059",
                                'name' => "No"
                            ]
                        ]
                    ],
                    [
                        'id' => "100110",
                        'values' => [
                            [
                                'id' => "1000059",
                                'name' => "No"
                            ]
                        ]
                    ]
                ],
                'skus' => [
                    [
                        'sales_attributes' => [],
                        'inventory' => [
                            [
                                'quantity' => $product->available_quantity,
                                'warehouse_id' => (string) $tiktokUser->tiktok_warehouse_id
                            ]
                        ],
                        'price' => [
                            'amount' => (string) $portfolio->customer_price,
                            'currency' => $tiktokUser->customer->shop->currency->code
                        ],
                    ]
                ]
            ];

            $tiktokProduct = $tiktokUser->uploadProductToTiktok($productData);

            UpdatePortfolio::run($portfolio, [
                'platform_product_id' => Arr::get($tiktokProduct, 'data.product_id'),
                'platform_product_variant_id' => Arr::get($tiktokProduct, 'data.product_id')
            ]);

            CheckTiktokPortfolio::run($portfolio);

            $portfolio->refresh();

            if ($portfolio->platform_status) {
                UpdatePlatformPortfolioLog::run($logs, [
                    'status' => PlatformPortfolioLogsStatusEnum::OK
                ]);
            } else {
                UpdatePlatformPortfolioLog::run($logs, [
                    'status' => PlatformPortfolioLogsStatusEnum::FAIL,
                    'response' => $tiktokProduct
                ]);
            }

            return $tiktokProduct;
        } catch (\Exception $e) {
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

            return [];
        }
    }

    public function asController(TiktokUser $tiktokUser, Portfolio $portfolio, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($tiktokUser, $portfolio);
    }
}
