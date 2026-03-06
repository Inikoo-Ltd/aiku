<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 10 Mar 2025 16:53:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Allegro\Product;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\AllegroUser;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class ProposeAllegroProduct
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    /**
     * Propose a new product to the Allegro catalogue.
     * POST /sale/products
     *
     * Returns the proposed product data including its ID, which is then
     * used when creating the offer via POST /sale/product-offers.
     */
    public function handle(AllegroUser $allegroUser, Portfolio $portfolio): array
    {
        /** @var Product $product */
        $product = $portfolio->item;

        $productImages = [];
        foreach ($product->images as $image) {
            $image = UploadProductImageToAllegro::run($allegroUser, $image);
            $productImages[] = [
                'url' => Arr::get($image, 'location')
            ];
        }

        $productData = [
            'name'     => Str::substr($portfolio->customer_product_name, 0, 75),
            'category' => [
                'id' => $allegroUser->allegro_category_id ?? '257931'
            ],
            'images'     => $productImages,
            'parameters' => $this->buildParameters($product),
            'description' => [
                'sections' => [
                    [
                        'items' => [
                            [
                                'type' => 'TEXT',
                                'content' => $portfolio->customer_description ?? ''
                            ]
                        ]
                    ]
                ]
            ],
            'language' => 'en-US'
        ];

        return $allegroUser->proposeProduct($productData);
    }

    private function buildParameters(Product $product): array
    {
        $parameters = [];

        if ($product->barcode) {
            $parameters[] = [
                'id'     => '225694', // EAN/GTIN parameter ID in Allegro
                'values' => [(string) $product->barcode]
            ];
        }

        return $parameters;
    }
}
