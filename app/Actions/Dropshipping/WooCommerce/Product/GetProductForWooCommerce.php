<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Product;

use App\Actions\Dropshipping\WithPlatformListedProducts;
use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class GetProductForWooCommerce
{
    use AsAction;
    use WithAttributes;
    use WithPlatformListedProducts;

    public $commandSignature = 'dropshipping:wooCommerce:product:get {wooCommerceUser}';

    private const int PAGE_SIZE = 100;

    private const int MAX_PAGES = 20;

    /**
     * A draft or pending product is not on sale, so it is left out: matching a portfolio to one
     * would point it at something no buyer can see.
     */
    public function handle(?WooCommerceUser $wooCommerceUser, $query = '', $offset = 0, $limit = 50): array
    {
        if (!$wooCommerceUser) {
            return [];
        }

        return $this->pickListedProducts(
            'woo-listed-products-'.$wooCommerceUser->id,
            fn () => $this->fetchListedProducts($wooCommerceUser),
            (string) $query,
            (int) $offset,
            (int) $limit
        );
    }

    /**
     * @return array<int, array>
     */
    private function fetchListedProducts(WooCommerceUser $wooCommerceUser): array
    {
        $listedProducts = [];

        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $products = $wooCommerceUser->getWooCommerceProducts([
                'per_page' => self::PAGE_SIZE,
                'page'     => $page,
                'status'   => 'publish'
            ]);

            if (!is_array($products) || !array_is_list($products)) {
                return $listedProducts;
            }

            foreach ($products as $product) {
                $listedProducts[] = $this->transformToStandardFormat($product);
            }

            if (count($products) < self::PAGE_SIZE) {
                return $listedProducts;
            }
        }

        Log::warning('WooCommerce catalogue too large to read in full for the product picker', [
            'woo_commerce_user_id' => $wooCommerceUser->id,
            'products_read'        => count($listedProducts)
        ]);

        return $listedProducts;
    }

    private function transformToStandardFormat($product): array
    {
        return [
            'id'     => Arr::get($product, 'id'),
            'name'   => Arr::get($product, 'name'),
            'slug'   => Arr::get($product, 'slug'),
            'code'   => Arr::get($product, 'sku'),
            'price'  => Arr::get($product, 'price'),
            'images' => Arr::get($product, 'images')
        ];
    }

    public function asController(WooCommerceUser $wooCommerceUser, ActionRequest $request): array
    {
        return $this->handle($wooCommerceUser);
    }
}
