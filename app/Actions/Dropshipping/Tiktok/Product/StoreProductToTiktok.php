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
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreProductToTiktok extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(Portfolio $portfolio): Portfolio
    {
        /** @var TiktokUser $tiktokUser */
        $tiktokUser = $portfolio->customerSalesChannel->user;

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

            $recommendCategory = $tiktokUser->recommendCategory([
                'product_title' => $product->family?->name ?? Str::words($product->name, 2, '')
            ]);

            $leafCategoryId = Arr::get($recommendCategory, 'data.leaf_category_id', '600009');
            $leafCategoryId = $this->resolveSafeCategoryId($tiktokUser, $leafCategoryId);

            $categoryRules = $tiktokUser->getCategoryRules($leafCategoryId);
            $requiredCertifications = collect(Arr::get($categoryRules, 'data.product_certifications', []))
                ->filter(fn ($cert) => Arr::get($cert, 'is_required') === true)
                ->map(fn ($cert) => [
                    'id'    => Arr::get($cert, 'id'),
                    'files' => []
                ])
                ->values()
                ->toArray();

            $categoryAttributes = $tiktokUser->getCategoryAttributes($leafCategoryId);
            $attributes = Arr::get($categoryAttributes, 'data.attributes', []);

            $productAttributes = collect($attributes)
                ->filter(fn ($attribute) => Arr::get($attribute, 'is_requried') === true)
                ->map(function ($attribute) {
                    $firstValue = Arr::first(Arr::get($attribute, 'values', []));

                    if ($firstValue) {
                        return [
                            'id'     => (string) Arr::get($attribute, 'id'),
                            'values' => [
                                [
                                    'id'   => (string) Arr::get($firstValue, 'id'),
                                    'name' => Arr::get($firstValue, 'name')
                                ]
                            ]
                        ];
                    }

                    if (Arr::get($attribute, 'is_customizable')) {
                        return [
                            'id'     => (string) Arr::get($attribute, 'id'),
                            'values' => [['name' => 'N/A']]
                        ];
                    }

                    return null;
                })
                ->filter()
                ->values()
                ->toArray();

            $w = max(Arr::get($product->marketing_dimensions, 'w', 1), 20);
            $h = max(Arr::get($product->marketing_dimensions, 'h', 1), 20);
            $l = max(Arr::get($product->marketing_dimensions, 'l', 1), 80);

            $description = $portfolio->customer_description;

            if (! $description) {
                $description = $portfolio->item_name;
            }

            $productData = [
                'title' => $portfolio->customer_product_name,
                'description' => $description,
                'price' => (string) $portfolio->customer_price,
                'category_id' => $leafCategoryId,
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
                'product_certifications' => $requiredCertifications,
                'external_product_id' => (string) $portfolio->id,
                'identifier_code' => [
                    'code' => (string) $product->barcode,
                    'type' => 'EAN'
                ],
                'product_attributes' => $productAttributes,
                'skus' => [
                    [
                        'sales_attributes' => [],
                        'inventory' => [
                            [
                                'quantity' => $product->available_quantity,
                                'warehouse_id' => (string) $tiktokUser->tiktok_warehouse_id
                            ]
                        ],
                        'seller_sku' => $portfolio->sku,
                        'price' => [
                            'amount' => (string) $portfolio->customer_price,
                            'currency' => $tiktokUser->customer->shop->currency->code
                        ],
                    ]
                ]
            ];

            $tiktokProduct = $tiktokUser->uploadProductToTiktok($productData);

            if (Arr::get($tiktokProduct, 'error')) {
                UpdatePortfolio::run($portfolio, [
                    'errors_response' => [
                        'message' => Arr::get($tiktokProduct, 'data')
                    ]
                ]);
            }

            /*$result = $tiktokUser->activateProduct([
                'product_ids' => [Arr::get($tiktokProduct, 'data.product_id')]
            ]);*/

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
                    'response' => Arr::get($tiktokProduct, 'data')
                ]);
            }

            return $portfolio;
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

            return $portfolio;
        }
    }

    public function asController(TiktokUser $tiktokUser, Portfolio $portfolio, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($portfolio);
    }

    private function resolveSafeCategoryId(TiktokUser $tiktokUser, string $categoryId): string
    {
        $cacheKey = "tiktok_safe_category_{$tiktokUser->id}";

        $cachedSafeId = cache()->get($cacheKey);
        if ($cachedSafeId) {
            return $cachedSafeId;
        }

        $rules = $tiktokUser->getCategoryRules($categoryId);
        $hasRequiredCerts = collect(Arr::get($rules, 'data.product_certifications', []))
            ->filter(fn ($cert) => Arr::get($cert, 'is_required') === true)
            ->isNotEmpty();

        if (!$hasRequiredCerts) {
            return $categoryId;
        }

        $allCategories = $tiktokUser->getCategories();
        $leafAvailable = collect(Arr::get($allCategories, 'data.categories', []))
            ->filter(
                fn ($cat) =>
                Arr::get($cat, 'is_leaf') === true &&
                in_array('AVAILABLE', Arr::get($cat, 'permission_statuses', []))
            )
            ->values();

        foreach ($leafAvailable as $cat) {
            $catId = Arr::get($cat, 'id');
            $catRules = $tiktokUser->getCategoryRules($catId);
            $certRequired = collect(Arr::get($catRules, 'data.product_certifications', []))
                ->filter(fn ($cert) => Arr::get($cert, 'is_required') === true)
                ->isNotEmpty();

            if (!$certRequired) {
                cache()->put($cacheKey, $catId, now()->addHours(24));
                return $catId;
            }
        }

        return $categoryId;
    }
}
