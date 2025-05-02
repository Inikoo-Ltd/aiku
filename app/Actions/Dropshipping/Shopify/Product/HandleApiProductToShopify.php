<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Events\UploadProductToShopifyProgressEvent;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class HandleApiProductToShopify extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    /**
     * @throws \Exception
     */
    public function handle(ShopifyUser $shopifyUser, array $attributes): void
    {
        $portfolios = $shopifyUser
            ->customer->portfolios()
            ->whereIn('id', $attributes)
            ->get();

        $totalProducts = $portfolios->count();
        $uploaded      = 0;
        foreach ($portfolios->chunk(2) as $portfolioChunk) {
            $client   = $shopifyUser->api()->getRestClient();

            $variants = [];
            $images   = [];
            foreach ($portfolioChunk as $portfolio) {
                $product = $portfolio->item;
                foreach ($product->productVariants as $variant) {
                    $existingOptions = Arr::pluck($variants, 'option1');

                    if (!in_array($variant->name, $existingOptions)) {
                        $variants[] = [
                            "option1"      => $variant->name,
                            "price"        => $variant->price,
                            "barcode"      => $variant->slug
                        ];
                    }
                }

                //                foreach ($product->images as $image) {
                //                    $images[] = [
                //                        "attachment" => $image->getBase64Image()
                //                    ];
                //                }

                $body = [
                    "product" => [
                        "id"           => $product->id,
                        "title"        => $product->name,
                        "body_html"    => $product->description,
                        "vendor"       => $product->shop->name,
                        "product_type" => $product->family?->name,
                        "images"       => $images,
                        "variants"     => $variants,
                        "options"      => [
                            "name"   => "Options",
                            "values" => Arr::pluck($variants, "option1")
                        ]
                    ]
                ];

                $response =  $client->request('POST', '/admin/api/2024-04/products.json', $body);

                if ($response['errors']) {
                    throw ValidationException::withMessages(['Internal server error, please wait a while']);
                }

                $shopifyUser->products()->attach($product, [
                    'shopify_user_id' => $shopifyUser->id,
                    'product_type' => class_basename($product),
                    'product_id' => $product->id,
                    'portfolio_id' => $portfolio->id,
                    'shopify_product_id' => $response['body']['product']['id']
                ]);

                $uploaded++;

                UploadProductToShopifyProgressEvent::dispatch($shopifyUser, $totalProducts, $uploaded);
            }
        }
    }
}
