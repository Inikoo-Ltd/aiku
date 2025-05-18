<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Product;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Events\UploadProductToWooCommerceProgressEvent;
use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class HandleApiProductToWooCommerce extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    /**
     * @throws \Exception
     */
    public function handle(WooCommerceUser $wooCommerceUser, array $attributes): void
    {
        $portfolios = $wooCommerceUser
            ->customer->portfolios()
            ->whereIn('id', $attributes)
            ->get();

        $totalProducts = $portfolios->count();
        $uploaded      = 0;
        foreach ($portfolios->chunk(2) as $portfolioChunk) {
            $client   = $wooCommerceUser->api()->getRestClient();

            $variants = [];
            $images   = [];
            foreach ($portfolioChunk as $portfolio) {
                $product = $portfolio->product;
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

                foreach ($product->images as $image) {
                    $images[] = [
                        "attachment" => $image->getBase64Image()
                    ];
                }

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

                if ($response['status'] == 422) {
                    abort($response['status'], $response['body']);
                }

                $wooCommerceUser->products()->attach($product->id, [
                    'wc_product_id' => $response['body']['product']['id'],
                    'portfolio_id' => $portfolio->id
                ]);

                $uploaded++;

                UploadProductToWooCommerceProgressEvent::dispatch($wooCommerceUser, $totalProducts, $uploaded);
            }
        }
    }
}
