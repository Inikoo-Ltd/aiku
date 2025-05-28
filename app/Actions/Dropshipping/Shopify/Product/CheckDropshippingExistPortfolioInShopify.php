<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\ShopifyUser;
use Gnikyt\BasicShopifyAPI\ResponseAccess;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class CheckDropshippingExistPortfolioInShopify
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    /**
     * @throws \Exception
     */
    public function handle(ShopifyUser $shopifyUser, Portfolio $portfolio): array|null|ResponseAccess
    {
        try {
            $response = $shopifyUser->getShopifyClient()->request('GET', '/admin/api/2025-04/products.json', [
                'handle' => Str::slug($portfolio->customer_product_name ?? $portfolio->item_name)
            ]);

            if (Arr::get($response, 'errors')) {
                throw new \Exception(Arr::get($response, 'errors'));
            }

            return Arr::get($response, 'body.products', []);
        } catch (\Exception $e) {
            \Sentry::captureMessage('Error in CheckExistPortfolioInShopify: ' . $e->getMessage(), 'error');

            return null;
        }
    }
}
