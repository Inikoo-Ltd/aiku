<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Product;

use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class GetProductForWooCommerce
{
    use AsAction;
    use WithAttributes;

    public $commandSignature = 'dropshipping:wooCommerce:product:get {wooCommerceUser}';

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

    public function handle(WooCommerceUser $wooCommerceUser, $query = '')
    {
        $products = $wooCommerceUser->getWooCommerceProducts([
            'search' => $query
        ]);

        return array_map([$this, 'transformToStandardFormat'], $products);
    }

    public function asController(WooCommerceUser $wooCommerceUser, ActionRequest $request)
    {
        return $this->handle($wooCommerceUser);
    }
}
