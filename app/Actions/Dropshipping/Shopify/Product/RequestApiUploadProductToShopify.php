<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Sentry;

class RequestApiUploadProductToShopify extends RetinaAction implements ShouldBeUnique
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public string $jobQueue = 'shopify';

    /**
     * @throws \Exception
     */
    public function handle(ShopifyUser $shopifyUser, Portfolio $portfolio, array $body = []): void
    {
        DB::transaction(function () use ($shopifyUser, $portfolio, $body) {
            try {
                $images = [];
                foreach ($portfolio->item->images as $image) {
                    $images[] = [
                        "attachment" => $image->getBase64Image()
                    ];
                }
            } catch (\Exception $e) {
                Sentry::captureException($e);
            }

            $body = [
                "product" => [
                    "id" => $portfolio->item->id,
                    "title" => $portfolio->item->name,
                    "body_html" => $portfolio->item->description,
                    "vendor" => $portfolio->item->shop->name,
                    "product_type" => $portfolio->item->family?->name,
                    "images" => $images,
                    "variants" => [
                        [
                            "price" => $portfolio->item->price,
                            "sku" => $portfolio->id,
                            "inventory_management" => "shopify",
                            "inventory_policy" => "deny",
                            "weight" => $portfolio->item->gross_weight,
                            "weight_unit" => "g"
                        ]
                    ]
                ]
            ];

            $productShopify = [];
            $client = $shopifyUser->getShopifyClient();

            $response = $client->request('POST', '/admin/api/2024-04/products.json', $body);
            if ($response['errors']) {
                UpdatePortfolio::run($portfolio, [
                    'errors_response' => Arr::get($response, 'body.errors')
                ]);

                \Sentry::captureMessage("Product upload failed: " . json_encode(Arr::get($response, 'body')));
            } else {
                $productShopify = Arr::get($response, 'body.product');
            }

            $inventoryVariants = [];
            foreach (Arr::get($productShopify, 'variants') as $variant) {
                $variant['available_quantity'] = $portfolio->item->available_quantity;
                $inventoryVariants[] = $variant;
            }

            HandleApiInventoryProductShopify::run($shopifyUser, $inventoryVariants);

            UpdatePortfolio::run($portfolio, [
                'shopify_product_id' => Arr::get($productShopify, 'id')
            ]);
        });
    }

    public function getJobUniqueId(ShopifyUser $shopifyUser, Portfolio $portfolio, array $body): int
    {
        return rand();
    }
}
