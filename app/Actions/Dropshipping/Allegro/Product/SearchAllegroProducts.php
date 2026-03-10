<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Allegro\Product;

use App\Models\Dropshipping\AllegroUser;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class SearchAllegroProducts
{
    use AsAction;
    use WithAttributes;

    public $commandSignature = 'dropshipping:allegro:product:get {allegroUser}';

    /**
     * @throws \Exception
     */
    private function transformToStandardFormat($product): array
    {
        if (blank($product)) {
            return [];
        }

        return [
            'id' => Arr::get($product, 'id'),
            'name' => Arr::get($product, 'name'),
            'slug' => Arr::get($product, 'skus.0.seller_sku'),
            'code' => Arr::get($product, 'skus.0.seller_sku'),
            'price' => Arr::get($product, 'skus.0.price.amount'),
            'images' => [
                [
                    'src' => Arr::get($product, 'images.0.url')
                ]
            ]
        ];
    }

    public function handle(AllegroUser $allegroUser, $query = ''): array
    {
        $products = $allegroUser->searchProducts([
            'phrase' => $query
        ]);

        return array_map([$this, 'transformToStandardFormat'], Arr::get($products, 'data.products', []));
    }

    public function asController(AllegroUser $allegroUser, ActionRequest $request): array
    {
        return $this->handle($allegroUser);
    }
}
