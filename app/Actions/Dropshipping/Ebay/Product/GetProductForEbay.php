<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Ebay\Product;

use App\Models\Dropshipping\EbayUser;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class GetProductForEbay
{
    use AsAction;
    use WithAttributes;

    public $commandSignature = 'dropshipping:ebay:product:get {ebay}';

    /**
     * @throws \Exception
     */
    private function transformToStandardFormat($product): array
    {
        return [
            'id' => $product['id'],
            'name' => $product['name'],
            'slug' => $product['slug'],
            'images' => Arr::get($product, 'images.0')
        ];
    }

    public function handle(EbayUser $ebayUser, $query = ''): array
    {
        $products = $ebayUser->getProducts();

        return array_map([$this, 'transformToStandardFormat'], $products);
    }

    public function asController(EbayUser $ebayUser, ActionRequest $request): array
    {
        return $this->handle($ebayUser);
    }
}
