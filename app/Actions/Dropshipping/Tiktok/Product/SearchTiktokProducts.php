<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Tiktok\Product;

use App\Models\Dropshipping\TiktokUser;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class SearchTiktokProducts
{
    use AsAction;
    use WithAttributes;

    public $commandSignature = 'dropshipping:tiktok:product:get {tiktokUser}';

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
            'name' => Arr::get($product, 'title'),
            'slug' => Arr::get($product, 'skus.0.seller_sku'),
            'code' => Arr::get($product, 'skus.0.seller_sku'),
            'price' => Arr::get($product, 'skus.0.price.amount'),
            'images' => Arr::get($product, 'images')
        ];
    }

    public function handle(TiktokUser $tiktokUser, $query = ''): array
    {
        $products = $tiktokUser->getProducts([
            'status' => 'ACTIVATE',
            'seller_skus' => [$query]
        ], [
            'page_size' => 100
        ]);

        return array_map([$this, 'transformToStandardFormat'], Arr::get($products, 'data.products'));
    }

    public function asController(TiktokUser $tiktokUser, ActionRequest $request): array
    {
        return $this->handle($tiktokUser);
    }
}
