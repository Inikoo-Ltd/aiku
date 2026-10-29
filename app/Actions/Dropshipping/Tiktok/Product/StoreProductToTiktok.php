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
use App\Actions\Dropshipping\WithPortfolioErrorResponse;
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
    use WithPortfolioErrorResponse;
    public const float FALLBACK_DIMENSION_IN_CM = 10;

    /**
     * @throws \Exception
     */
    public function handle(Portfolio $portfolio): Portfolio
    {
        /** @var TiktokUser $tiktokUser */
        $tiktokUser = $portfolio->customerSalesChannel->user;

        if (! $tiktokUser) {
            throw new \Exception('No authenticated TikTok channel found.');
        }

        $logs = StorePlatformPortfolioLog::run($portfolio, [
            'type' => PlatformPortfolioLogsTypeEnum::UPLOAD
        ]);

        try {
            /** @var Product $product */
            $product = $portfolio->item;

            $productImages = [];
            foreach ($product->orderedImages() as $image) {
                $productImage = UploadProductImageToTiktok::run($tiktokUser, $image);

                $productImages[] = [
                    'uri' => Arr::get($productImage, 'data.uri')
                ];
            }

            $recommendCategory = $tiktokUser->recommendCategory([
                'product_title' => $product->family?->name ?? Str::words($product->name, 2, '')
            ]);

            $leafCategoryId = Arr::get($recommendCategory, 'data.leaf_category_id', '600009');
            $leafCategoryId = $this->resolveSafeCategoryId($tiktokUser, $leafCategoryId, Arr::get($recommendCategory, 'data.categories', []));

            $categoryRules = $tiktokUser->getCategoryRules($leafCategoryId);

            $requiredCertifications = collect(Arr::get($categoryRules, 'data.product_certifications', []))
                ->filter(fn ($cert) => Arr::get($cert, 'is_required') === true)
                ->map(fn ($cert) => [
                    'id'    => Arr::get($cert, 'id'),
                    'files' => []
                ])
                ->values()
                ->toArray();

            $requiredPersonResponsible = [];

            if (Arr::get($categoryRules, 'data.responsible_person.is_required')) {
                $personResponsible = $tiktokUser->getPersonResponsible();
                /** @var array $personId */
                $personId = collect(Arr::get($personResponsible, 'data.responsible_persons', []))->first();
                $requiredPersonResponsible = [
                    'responsible_person_ids' => [Arr::get($personId, 'id')]
                ];
            }

            $requiredManufacturer = [];

            if (Arr::get($categoryRules, 'data.manufacturer.is_required')) {
                $manufacturers = $tiktokUser->getManufacturers();
                /** @var array $manufacturer */
                $manufacturer = collect(Arr::get($manufacturers, 'data.manufacturers', []))->first();

                if (Arr::get($manufacturer, 'id')) {
                    $requiredManufacturer = [
                        'manufacturer_ids' => [Arr::get($manufacturer, 'id')]
                    ];
                }
            }

            $identifierCode = blank($product->barcode) ? [] : [
                'identifier_code' => [
                    'code' => (string) $product->barcode,
                    'type' => 'EAN'
                ]
            ];

            $categoryAttributes = $tiktokUser->getCategoryAttributes($leafCategoryId);
            $attributes = Arr::get($categoryAttributes, 'data.attributes', []);

            $productAttributes = collect($attributes)
                ->filter(fn ($attribute) => in_array(true, [Arr::get($attribute, 'is_requried'), Arr::get($attribute, 'is_required')]))
                ->map(function ($attribute) {
                    $attributeValues = Arr::get($attribute, 'values', []);
                    $firstValue = collect($attributeValues)->firstWhere('name', 'No') ?? Arr::first($attributeValues);

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
                'package_dimensions' => self::packageDimensions($product),
                'product_certifications' => $requiredCertifications,
                ...$requiredPersonResponsible,
                ...$requiredManufacturer,
                ...$identifierCode,
                'external_product_id' => (string) $portfolio->id,
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
                $warnings = collect(Arr::get($tiktokProduct, 'data.warnings', []))
                    ->pluck('message')
                    ->filter()
                    ->implode(' ');

                UpdatePortfolio::run($portfolio, [
                    'errors_response' => null,
                    'upload_warning'  => $warnings ?: $portfolio->upload_warning
                ]);

                UpdatePlatformPortfolioLog::run($logs, [
                    'status' => PlatformPortfolioLogsStatusEnum::OK
                ]);
            } else {
                UpdatePortfolio::run($portfolio, [
                    'errors_response' => $this->portfolioErrorResponse(Arr::get($tiktokProduct, 'data'))
                ]);

                UpdatePlatformPortfolioLog::run($logs, [
                    'status' => PlatformPortfolioLogsStatusEnum::FAIL,
                    'response' => Arr::get($tiktokProduct, 'data')
                ]);
            }

            return $portfolio;
        } catch (\Exception $e) {
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

    public function asController(TiktokUser $tiktokUser, Portfolio $portfolio, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($portfolio);
    }

    /**
     * The recommended category is kept unless the seller is not qualified for it (TikTok answers "Precondition Required")
     * or it needs certifications we cannot supply; only then is the seller's fallback category used.
     */
    private function resolveSafeCategoryId(TiktokUser $tiktokUser, string $categoryId, array $recommendedCategories = []): string
    {
        $sellerCategories   = null;
        $permissionStatuses = Arr::get(collect($recommendedCategories)->firstWhere('id', $categoryId), 'permission_statuses');

        if (!is_array($permissionStatuses)) {
            $sellerCategories   = Arr::get($tiktokUser->getCategories(), 'data.categories', []);
            $permissionStatuses = Arr::get(collect($sellerCategories)->firstWhere('id', $categoryId), 'permission_statuses');
        }

        $sellerIsQualified = !is_array($permissionStatuses) || in_array('AVAILABLE', $permissionStatuses);

        if ($sellerIsQualified) {
            $rules = $tiktokUser->getCategoryRules($categoryId);
            $hasRequiredCerts = collect(Arr::get($rules, 'data.product_certifications', []))
                ->filter(fn ($cert) => Arr::get($cert, 'is_required') === true)
                ->isNotEmpty();

            if (!$hasRequiredCerts) {
                return $categoryId;
            }
        }

        $cacheKey = "tiktok_safe_category_{$tiktokUser->id}";

        $cachedSafeId = cache()->get($cacheKey);
        if ($cachedSafeId) {
            return $cachedSafeId;
        }

        $sellerCategories ??= Arr::get($tiktokUser->getCategories(), 'data.categories', []);
        $leafAvailable = collect($sellerCategories)
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

    /**
     * Marketing dimensions are held in metres, TikTok reads the package in centimetres.
     *
     * @return array{width: string, length: string, height: string, unit: string}
     */
    public static function packageDimensions(Product $product): array
    {
        $centimetres = function (string $dimension) use ($product): string {
            $value = Arr::get($product->marketing_dimensions, $dimension);

            $value = is_numeric($value) && (float) $value > 0
                ? (float) $value * 100
                : self::FALLBACK_DIMENSION_IN_CM;

            return number_format(ceil($value), 2, '.', '');
        };

        return [
            'width'  => $centimetres('w'),
            'length' => $centimetres('l'),
            'height' => $centimetres('h'),
            'unit'   => 'CENTIMETER',
        ];
    }
}
