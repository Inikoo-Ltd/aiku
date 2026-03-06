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
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\PlatformLogs\PlatformPortfolioLogsStatusEnum;
use App\Enums\Ordering\PlatformLogs\PlatformPortfolioLogsTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\AllegroUser;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreProductToAllegro extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(Portfolio $portfolio): Portfolio
    {
        /** @var AllegroUser $allegroUser */
        $allegroUser = $portfolio->customerSalesChannel->user;

        $logs = StorePlatformPortfolioLog::run($portfolio, [
            'type' => PlatformPortfolioLogsTypeEnum::UPLOAD
        ]);

        try {
            /** @var Product $product */
            $product = $portfolio->item;

            $proposedProduct = ProposeAllegroProduct::run($allegroUser, $portfolio);
            $allegroProductId = Arr::get($proposedProduct, 'id');

            if (!$allegroProductId) {
                throw new \Exception('Failed to propose product to Allegro: no product ID returned.');
            }

            $offerData = [
                'productSet' => [
                    [
                        'product' => [
                            'id' => $allegroProductId
                        ],
                        'quantity' => [
                            'value' => 1
                        ]
                    ]
                ],
                'name'     => $portfolio->customer_product_name,
                'category' => [
                    'id' => $allegroUser->allegro_category_id ?? '257931'
                ],
                'sellingMode' => [
                    'format' => 'BUY_NOW',
                    'price'  => [
                        'amount'   => number_format((float) $portfolio->customer_price, 2, '.', ''),
                        'currency' => $allegroUser->customer->shop->currency->code ?? 'PLN'
                    ]
                ],
                'stock' => [
                    'available' => $product->available_quantity,
                    'unit'      => 'UNIT'
                ],
                'delivery' => [
                    'handlingTime'  => 'PT24H',
                    'shippingRates' => [
                        'id' => $allegroUser->allegro_shipping_rates_id
                    ]
                ],
                'publication' => [
                    'status'    => 'ACTIVE',
                    'republish' => true
                ],
                'location' => [
                    'city'        => $allegroUser->city ?? 'Warszawa',
                    'countryCode' => $allegroUser->country_code ?? 'PL',
                    'postCode'    => $allegroUser->post_code ?? '00-001',
                    'province'    => $allegroUser->province ?? 'MAZOWIECKIE'
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
                                    'text' => $portfolio->customer_description ?? ''
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

    public function asController(AllegroUser $allegroUser, Portfolio $portfolio, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($allegroUser, $portfolio);
    }
}
