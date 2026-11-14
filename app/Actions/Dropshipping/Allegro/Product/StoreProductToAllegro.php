<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 10 Mar 2025 16:53:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Allegro\Product;

use App\Actions\Dropshipping\Allegro\Traits\WithAllegroDispatchLocation;
use App\Actions\Dropshipping\Allegro\Traits\WithAllegroMarketplace;
use App\Actions\Dropshipping\Allegro\Traits\WithAllegroShippingRates;
use App\Actions\Dropshipping\Portfolio\Logs\StorePlatformPortfolioLog;
use App\Actions\Dropshipping\Portfolio\Logs\UpdatePlatformPortfolioLog;
use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\Dropshipping\WithPortfolioErrorResponse;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\PlatformLogs\PlatformPortfolioLogsStatusEnum;
use App\Enums\Ordering\PlatformLogs\PlatformPortfolioLogsTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\AllegroUser;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use App\Models\Helpers\Currency;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreProductToAllegro extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;
    use WithAllegroDispatchLocation;
    use WithAllegroMarketplace;
    use WithAllegroShippingRates;
    use WithPortfolioErrorResponse;

    public function handle(Portfolio $portfolio): Portfolio
    {
        /** @var CustomerSalesChannel $customerSalesChannel */
        $customerSalesChannel = $portfolio->customerSalesChannel;

        /** @var AllegroUser $allegroUser */
        $allegroUser = $customerSalesChannel->user;

        if (!$allegroUser || ($portfolio->platform_product_id && $portfolio->platform_status)) {
            return $portfolio;
        }

        if ($portfolio->platform_product_id && $portfolio->exist_in_platform) {
            return $this->activateOffer($allegroUser, $portfolio);
        }

        /** @var Customer $customer */
        $customer = $customerSalesChannel->customer;

        /** @var Shop $shop */
        $shop = $customer->shop;

        $logs = StorePlatformPortfolioLog::run($portfolio, [
            'type' => PlatformPortfolioLogsTypeEnum::UPLOAD
        ]);

        try {
            /** @var Product $product */
            $product = $portfolio->item;

            $marketplaceId = Arr::get($allegroUser->data, 'marketplace_id');

            $offerLanguage = $this->getAllegroOfferLanguage($marketplaceId);

            $catalogueProduct = null;
            if ($product->barcode) {
                $catalogueProduct = $this->catalogueProductWithBarcode(
                    Arr::get($allegroUser->getProductByEan($product->barcode), 'products', []),
                    $product->barcode
                );
            }

            if ($catalogueCategoryId = Arr::get($catalogueProduct, 'category.id')) {
                $categoryId = $catalogueCategoryId;
            } else {
                $parent = $product->subDepartment?->name;
                if (!$parent) {
                    $parent = $product->name;
                }

                $getRecommendedCategory = $allegroUser->getRecommendedCategory($parent);
                $categoryId = Arr::get($getRecommendedCategory, 'matchingCategories.0.id');

                if (!$categoryId) {
                    throw new \Exception(Arr::get($getRecommendedCategory, 'message', "Allegro has no matching category for \"$parent\"."));
                }
            }

            $getParameters = $allegroUser->getCategoryParameters($categoryId);

            $proposedProduct = ProposeAllegroProduct::run($allegroUser, $portfolio, [
                'category_id' => $categoryId,
                'parameters' => $getParameters,
                'language' => $offerLanguage
            ]);

            $allegroProductId = Arr::get($proposedProduct, 'id');
            $linkedToExistingProduct = false;

            if (!$allegroProductId && Str::contains((string)Arr::get($proposedProduct, 'message'), 'Product already exists')) {
                if (!$catalogueProduct) {
                    throw new \Exception($product->barcode
                        ? "Allegro reports this product already exists, but no product in its catalogue has barcode $product->barcode, so it was not listed under another product."
                        : 'Allegro reports this product already exists, but there is no barcode to find it with.');
                }

                $proposedProduct = $catalogueProduct;
                $allegroProductId = Arr::get($catalogueProduct, 'id');
                $linkedToExistingProduct = true;
            } elseif (Arr::get($proposedProduct, 'existing_product')) {
                $proposedProduct = $allegroUser->getProduct($allegroProductId);
                $linkedToExistingProduct = true;

                if ($getProductError = Arr::get($proposedProduct, 'message')) {
                    throw new \Exception($getProductError);
                }

                if ($product->barcode && !$this->catalogueProductHasBarcode($proposedProduct, $product->barcode)) {
                    throw new \Exception("Allegro matched this product to its catalogue product $allegroProductId (".Arr::get($proposedProduct, 'name')."), which does not have barcode $product->barcode, so it was not listed under it.");
                }
            }

            if (!$allegroProductId) {
                throw new \Exception(Arr::get($proposedProduct, 'message', 'Failed to propose product to Allegro: no product ID returned.'));
            }

            $offerCategoryId = $this->resolveOfferCategoryId(
                $allegroUser,
                is_array($proposedProduct) ? $proposedProduct : [],
                $allegroProductId,
                $categoryId
            );

            $availableQuantity = $product->available_quantity;

            if ($customerSalesChannel->max_quantity_advertise > 0) {
                $availableQuantity = min($availableQuantity, $customerSalesChannel->max_quantity_advertise);
            }

            $marketplaceCurrencyCode = $this->getAllegroCurrencyCode($marketplaceId);

            $targetCurrency = $marketplaceCurrencyCode
                ? Currency::where('code', $marketplaceCurrencyCode)->first() ?? $shop->currency
                : $shop->currency;

            $customerPrice = $portfolio->customer_price;

            if ($targetCurrency->code !== $shop->currency->code) {
                $priceExchange = GetCurrencyExchange::run($shop->currency, $targetCurrency);

                if (!$priceExchange) {
                    throw new \Exception("Unable to get the {$shop->currency->code} to {$targetCurrency->code} exchange rate.");
                }

                $customerPrice = $customerPrice * $priceExchange;
            }

            $shippingRatesId = $this->ensureAllegroShippingRatesId($allegroUser);

            if (!$shippingRatesId) {
                throw new \Exception(__('No shipping price list is set on this Allegro channel, and Allegro refused to create one. Please contact us.'));
            }

            $responsibleProducerId = Arr::get($allegroUser->data, 'responsible_producer_id');
            $responsiblePersonId = Arr::get($allegroUser->data, 'responsible_person_id');

            $offerData = [
                'productSet' => [
                    [
                        'product' => [
                            'id' => $allegroProductId
                        ],
                        'quantity' => [
                            'value' => (int) $product->units
                        ],
                        'responsibleProducer' => [
                            'id' => $responsibleProducerId
                        ],
                        'responsiblePerson' => [
                            'id' => $responsiblePersonId
                        ],
                        'safetyInformation' => [
                            'type' => 'TEXT',
                            'description' => __('This product is safe for use.')
                        ]
                    ]
                ],
                'name' => Str::substr($portfolio->customer_product_name, 0, 75),
                'category' => [
                    'id' => $offerCategoryId
                ],
                'sellingMode' => [
                    'format' => 'BUY_NOW',
                    'price' => [
                        'amount' => $this->formatAllegroPrice($customerPrice, $marketplaceId),
                        'currency' => $targetCurrency->code
                    ]
                ],
                'stock' => [
                    'available' => $availableQuantity,
                    'unit' => 'UNIT'
                ],
                'location' => $this->allegroOfferLocation(),
                'delivery' => [
                    'handlingTime' => 'PT24H',
                    'shippingRates' => [
                        'id' => $shippingRatesId
                    ]
                ],
                'afterSalesServices' => [
                    'returnPolicy' => [
                        'id' => Arr::get($allegroUser->settings, 'policy.return_id')
                    ]
                ],
                'publication' => [
                    'status' => 'ACTIVE',
                    'republish' => true
                ],
                'external' => [
                    'id' => (string)$portfolio->id
                ],
                'language' => $offerLanguage,
                'description' => [
                    'sections' => [
                        [
                            'items' => [
                                [
                                    'type' => 'TEXT',
                                    'content' => $allegroUser->sanitizeAllegroDescription($portfolio->customer_description)
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            $allegroOffer = $allegroUser->createOffer($offerData);
            $allegroOfferId = Arr::get($allegroOffer, 'id');

            if (!$allegroOfferId) {
                throw new \Exception(Arr::get($allegroOffer, 'message', 'Failed to create Allegro offer: no offer ID returned.'));
            }

            UpdatePortfolio::run($portfolio, [
                'platform_product_id' => $allegroOfferId,
                'platform_product_variant_id' => $allegroOfferId,
                'has_valid_platform_product_id' => true,
                'exist_in_platform' => true,
                'errors_response' => null
            ]);

            $this->activateOffer($allegroUser, $portfolio);

            if (CheckAllegroPortfolio::isLiveOrActivating($portfolio)) {
                UpdatePlatformPortfolioLog::run($logs, [
                    'status' => PlatformPortfolioLogsStatusEnum::OK,
                    'response' => json_encode([
                        'offer_id' => $allegroOfferId,
                        'catalogue_product' => [
                            'id' => $allegroProductId,
                            'existing' => $linkedToExistingProduct,
                            'name' => Arr::get($proposedProduct, 'name'),
                            'gtins' => $this->catalogueProductGtins($proposedProduct),
                            'image' => Arr::get($proposedProduct, 'images.0.url'),
                        ],
                    ])
                ]);
            } else {
                UpdatePlatformPortfolioLog::run($logs, [
                    'status' => PlatformPortfolioLogsStatusEnum::FAIL,
                    'response' => json_encode(['offer_id' => $allegroOfferId, 'errors_response' => $portfolio->errors_response])
                ]);
            }

            return $portfolio;
        } catch (\Throwable $e) {
            UpdatePlatformPortfolioLog::run($logs, [
                'status' => PlatformPortfolioLogsStatusEnum::FAIL,
                'response' => $e->getMessage()
            ]);

            UpdatePortfolio::run($portfolio, [
                'errors_response' => $this->portfolioErrorResponse($e->getMessage())
            ]);

            return $portfolio;
        }
    }

    public function activateOffer(AllegroUser $allegroUser, Portfolio $portfolio): Portfolio
    {
        $commandId = (string) Str::uuid();

        $publishResponse = $allegroUser->publishOffers($commandId, [$portfolio->platform_product_id]);

        if ($publishError = Arr::get($publishResponse, 'message')) {
            UpdatePortfolio::run($portfolio, [
                'platform_status' => false,
                'errors_response' => $this->portfolioErrorResponse("Allegro refused to activate this offer: $publishError"),
            ]);

            return $portfolio;
        }

        UpdatePortfolio::run($portfolio, [
            'has_valid_platform_product_id' => true,
            'exist_in_platform'             => true,
            'platform_status'               => false,
            'errors_response'               => ['message' => CheckAllegroPortfolio::ACTIVATING_MESSAGE],
            'data'                          => ['allegro_publication' => ['status' => 'ACTIVATING', 'ended_by' => null]],
        ]);

        CheckAllegroPortfolio::dispatch($portfolio, $commandId)->delay(now()->addSeconds(CheckAllegroPortfolio::RECHECK_AFTER_PUBLISH_SECONDS));

        return $portfolio;
    }

    public function catalogueProductWithBarcode(array $catalogueProducts, string $barcode): ?array
    {
        foreach ($catalogueProducts as $catalogueProduct) {
            if ($this->catalogueProductHasBarcode($catalogueProduct, $barcode)) {
                return $catalogueProduct;
            }
        }

        return null;
    }

    public function catalogueProductHasBarcode(array $catalogueProduct, string $barcode): bool
    {
        return in_array($this->normalizeGtin($barcode), array_map($this->normalizeGtin(...), $this->catalogueProductGtins($catalogueProduct)), true);
    }

    private function catalogueProductGtins(array $catalogueProduct): array
    {
        return collect(Arr::get($catalogueProduct, 'parameters', []))
            ->filter(fn ($parameter) => Arr::get($parameter, 'options.isGTIN') || preg_match('/\b(ean|gtin)\b/i', (string) Arr::get($parameter, 'name')))
            ->flatMap(fn ($parameter) => Arr::get($parameter, 'values', []))
            ->map(fn ($value) => (string) $value)
            ->values()
            ->all();
    }

    private function normalizeGtin(string $gtin): string
    {
        return ltrim(preg_replace('/\D/', '', $gtin), '0');
    }

    private function resolveOfferCategoryId(
        AllegroUser $allegroUser,
        array $productResponse,
        ?string $allegroProductId,
        string $categoryId
    ): string {
        $productCategoryId = Arr::get($productResponse, 'category.id')
            ?? Arr::get($productResponse, 'product.category.id')
            ?? Arr::get($productResponse, 'products.0.category.id');

        if (!$productCategoryId && $allegroProductId) {
            $productCategoryId = Arr::get($allegroUser->getProduct($allegroProductId), 'category.id');
        }

        return (string) ($productCategoryId ?: $categoryId);
    }
}
