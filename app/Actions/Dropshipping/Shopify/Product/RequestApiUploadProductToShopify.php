<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Actions\Dropshipping\CustomerSalesChannel\UpdateCustomerSalesChannel;
use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\CustomerSalesChannelStateEnum;
use App\Events\UploadProductToShopifyProgressEvent;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Sentry;

class RequestApiUploadProductToShopify extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public string $jobQueue = 'shopify';
    public int $jobBackoff = 5;

    /**
     * @throws \Exception
     * @throws \Throwable
     */
    public function handle(ShopifyUser $shopifyUser, Portfolio $portfolio, array $body = []): void
    {
        DB::transaction(function () use ($shopifyUser, $portfolio, $body) {
            try {
                try {
                    $images = [];
                    $count  = 0;
                    foreach ($portfolio->item->images as $image) {
                        if ($count >= 3) {
                            break;
                        }

                        $images[] = [
                            "attachment" => $image->getBase64Image()
                        ];

                        $count++;
                    }
                } catch (\Exception $e) {
                    Sentry::captureException($e);
                }

                $body = [
                    "product" => [
                        "id"           => $portfolio->item->id,
                        "title"        => $portfolio->customer_product_name,
                        "handle"       => $portfolio->platform_handle,
                        "body_html"    => $portfolio->customer_description,
                        "vendor"       => $portfolio->item->shop->name,
                        "product_type" => $portfolio->item->family?->name,
                        "images"       => $images,
                        "variants"     => [
                            [
                                "price"                => number_format($portfolio->customer_price, 2, '.', ''),
                                "sku"                  => $portfolio->id,
                                "inventory_management" => "shopify",
                                "inventory_policy"     => "deny",
                                "weight"               => $portfolio->item->gross_weight,
                                "weight_unit"          => "g"
                            ]
                        ]
                    ]
                ];

                $productShopify    = [];
                $client            = $shopifyUser->getShopifyClient();
                $availableProducts = CheckDropshippingExistPortfolioInShopify::run($shopifyUser, $portfolio);

                if (count($availableProducts) <= 0) {
                    try {
                        $response = $client->request('POST', '/admin/api/2024-07/products.json', $body);
                        if ($response['errors']) {
                            $portfolio = UpdatePortfolio::run($portfolio, [
                                'errors_response' => [Arr::get($response, 'body')]
                            ]);

                            \Sentry::captureMessage("Product upload failed: ".$portfolio->errors_response);
                        } else {
                            $productShopify = Arr::get($response, 'body.product');
                        }
                    } catch (\Exception $e) {
                        \Sentry::captureMessage($e->getMessage());

                        $availableProducts = CheckDropshippingExistPortfolioInShopify::run($shopifyUser, $portfolio);
                        $productShopify    = Arr::get($availableProducts, '0');
                    }
                } else {
                    $productShopify = Arr::get($availableProducts, '0');
                }

                $inventoryVariants = [];
                foreach (Arr::get($productShopify, 'variants', []) as $variant) {
                    $variant['available_quantity'] = $portfolio->item->available_quantity;
                    $inventoryVariants[]           = $variant;
                }

                HandleApiInventoryProductShopify::dispatch($shopifyUser, $inventoryVariants);

                UpdatePortfolio::run($portfolio, [
                    'platform_product_id' => Arr::get($productShopify, 'id')
                ]);


                if (! in_array($shopifyUser->customerSalesChannel->state, [CustomerSalesChannelStateEnum::WITH_PORTFOLIO])) {
                    UpdateCustomerSalesChannel::run($shopifyUser->customerSalesChannel, [
                        'state' => CustomerSalesChannelStateEnum::WITH_PORTFOLIO
                    ]);
                }

                UploadProductToShopifyProgressEvent::dispatch($shopifyUser, $portfolio);
            } catch (\Exception $e) {
                \Sentry::captureMessage($e->getMessage());

                UpdatePortfolio::run($portfolio, [
                    'errors_response' => [$e->getMessage()]
                ]);

                $portfolio->refresh();

                UploadProductToShopifyProgressEvent::dispatch($shopifyUser, $portfolio);
            }
        });
    }
}
