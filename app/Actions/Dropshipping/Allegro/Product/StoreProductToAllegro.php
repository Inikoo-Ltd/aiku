<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 10 Mar 2025 16:53:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Allegro\Product;

use App\Actions\Dropshipping\Allegro\Traits\WithAllegroMarketplace;
use App\Actions\Dropshipping\Portfolio\Logs\StorePlatformPortfolioLog;
use App\Actions\Dropshipping\Portfolio\Logs\UpdatePlatformPortfolioLog;
use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
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
    use WithAllegroMarketplace;

    public function handle(Portfolio $portfolio): Portfolio
    {
        /** @var CustomerSalesChannel $customerSalesChannel */
        $customerSalesChannel = $portfolio->customerSalesChannel;

        /** @var AllegroUser $allegroUser */
        $allegroUser = $customerSalesChannel->user;

        if (!$allegroUser) {
            return $portfolio;
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

            $productSearch = [];
            if ($product->barcode) {
                $productSearch = $allegroUser->getProductByEan($product->barcode);
            }

            if ($foundedProduct = Arr::get($productSearch, 'products.0.category.id')) {
                $categoryId = $foundedProduct;
            } else {
                $parent = $product->subDepartment?->name;
                if (!$parent) {
                    $parent = $product->name;
                }

                $getRecommendedCategory = $allegroUser->getRecommendedCategory($parent);
                $categoryId = Arr::get($getRecommendedCategory, 'matchingCategories.0.id', '12');
            }

            $getParameters = $allegroUser->getCategoryParameters($categoryId);

            try {
                $proposedProduct = ProposeAllegroProduct::run($allegroUser, $portfolio, [
                    'category_id' => $categoryId,
                    'parameters' => $getParameters,
                    'language' => $offerLanguage
                ]);

                $allegroProductId = Arr::get($proposedProduct, 'id');

                if (!$allegroProductId && Str::contains((string)Arr::get($proposedProduct, 'message'), 'Product already exists')) {
                    $proposedProduct = $allegroUser->searchProducts([
                        'phrase' => $portfolio->barcode,
                        'mode' => 'GTIN'
                    ]);

                    $allegroProductId = Arr::get($proposedProduct, 'products.0.id');

                    if (!$allegroProductId) {
                        throw new \Exception(Arr::get($proposedProduct, 'message', 'Failed to propose product to Allegro: no product ID returned.'));
                    }
                }
            } catch (\Exception $e) {
                throw new \Exception($e->getMessage());
            }

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
                    'id' => $categoryId
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
                'delivery' => [
                    'handlingTime' => 'PT24H',
                    'shippingRates' => [
                        'id' => Arr::get($allegroUser->settings, 'shipping.id')
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
                'errors_response' => null
            ]);

            $publishResponse = $allegroUser->publishOffers(Str::uuid(), [$allegroOfferId]);

            if ($publishError = Arr::get($publishResponse, 'message')) {
                throw new \Exception($publishError);
            }

            CheckAllegroPortfolio::run($portfolio);

            $portfolio->refresh();

            if ($portfolio->platform_status) {
                UpdatePlatformPortfolioLog::dispatch($logs, [
                    'status' => PlatformPortfolioLogsStatusEnum::OK
                ]);
            } else {
                UpdatePlatformPortfolioLog::dispatch($logs, [
                    'status' => PlatformPortfolioLogsStatusEnum::FAIL,
                    'response' => json_encode($allegroOffer)
                ]);
            }

            return $portfolio;
        } catch (\Throwable $e) {
            UpdatePlatformPortfolioLog::dispatch($logs, [
                'status' => PlatformPortfolioLogsStatusEnum::FAIL,
                'response' => $e->getMessage()
            ]);

            UpdatePortfolio::run($portfolio, [
                'errors_response' => [
                    'message' => $e->getMessage()
                ]
            ]);

            return $portfolio;
        }
    }
}
