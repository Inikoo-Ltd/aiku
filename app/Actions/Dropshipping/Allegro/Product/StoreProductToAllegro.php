<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 10 Mar 2025 16:53:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Allegro\Product;

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

    public function handle(Portfolio $portfolio): Portfolio
    {
        /** @var CustomerSalesChannel $customerSalesChannel */
        $customerSalesChannel = $portfolio->customerSalesChannel;

        /** @var AllegroUser $allegroUser */
        $allegroUser = $customerSalesChannel->user;

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

            $getRecommendedCategory = $allegroUser->getRecommendedCategory($product->family->name);
            $categoryId = Arr::get($getRecommendedCategory, 'matchingCategories.0.id');

            $getParameters = $allegroUser->getCategoryParameters($categoryId);

            $allegroProductId = null;
            try {
                $proposedProduct = ProposeAllegroProduct::run($allegroUser, $portfolio, [
                    'category_id' => $categoryId,
                    'parameters' => $getParameters
                ]);

                $allegroProductId = Arr::get($proposedProduct, 'id');
            } catch (\Exception $e) {
                dd($e);
                $res = Str::contains($e->getMessage(), ['Produkt z takimi danymi już istnieje. Skontaktuj się z autorem aplikacji.']);

                if ($res) {
                    $proposedProduct = $allegroUser->searchProducts([
                        'phrase' => $portfolio->barcode,
                        'mode' => 'GTIN'
                    ]);

                    $allegroProductId = Arr::get($proposedProduct, 'products.0.id');
                }
            }

            if (!$allegroProductId) {
                throw new \Exception('Failed to propose product to Allegro: no product ID returned.');
            }

            $availableQuantity = $product->available_quantity;

            if ($customerSalesChannel->max_quantity_advertise > 0) {
                $availableQuantity = min($availableQuantity, $customerSalesChannel->max_quantity_advertise);
            }

            $targetCurrency = Currency::where('code', 'PLN')->first();
            $plnPriceExchange = GetCurrencyExchange::run($shop->currency, $targetCurrency);
            $customerPrice = $portfolio->customer_price * $plnPriceExchange;

            $offerData = [
                'productSet' => [
                    [
                        'product' => [
                            'id' => $allegroProductId
                        ],
                        'quantity' => [
                            'value' => $availableQuantity
                        ]
                    ]
                ],
                'name'     => $portfolio->customer_product_name,
                'category' => [
                    'id' => $categoryId
                ],
                'sellingMode' => [
                    'format' => 'BUY_NOW',
                    'price'  => [
                        'amount'   => number_format((float) $customerPrice, 2, '.', ''),
                        'currency' => 'PLN'
                    ]
                ],
                'stock' => [
                    'available' => $availableQuantity,
                    'unit'      => 'UNIT'
                ],
                'delivery' => [
                    'handlingTime'  => 'PT24H',
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
                    'status'    => 'ACTIVE',
                    'republish' => true
                ],
                'external' => [
                    'id' => (string) $portfolio->id
                ],
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
            $allegroUser->publishOffers(Str::uuid(), [Arr::get($allegroOffer, 'id')]);

            UpdatePortfolio::run($portfolio, [
                'platform_product_id'         => Arr::get($allegroOffer, 'id'),
                'platform_product_variant_id' => Arr::get($allegroOffer, 'id')
            ]);

            CheckAllegroPortfolio::run($portfolio);

            $portfolio->refresh();

            if ($portfolio->platform_status) {
                UpdatePlatformPortfolioLog::run($logs, [
                    'status' => PlatformPortfolioLogsStatusEnum::OK
                ]);
            } else {
                UpdatePlatformPortfolioLog::run($logs, [
                    'status'   => PlatformPortfolioLogsStatusEnum::FAIL,
                    'response' => $allegroOffer
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
}
