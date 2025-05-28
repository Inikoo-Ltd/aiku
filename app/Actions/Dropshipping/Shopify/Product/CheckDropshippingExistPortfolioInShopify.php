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
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Arr;
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
    public function handle(ShopifyUser $shopifyUser, Portfolio $portfolio): PromiseInterface|null
    {
        try {
            $response = $shopifyUser->getShopifyClient()->request('GET', '/admin/api/2024-04/products.json', [
                'sku' => $portfolio->id,
                'limit' => 1
            ]);

            if (Arr::get($response, 'errors')) {
                throw new \Exception(Arr::get($response, 'errors'));
            }

            return Arr::get($response, 'body.products.0', []);
        } catch (\Exception $e) {
            \Sentry::captureMessage('Error in CheckExistPortfolioInShopify: ' . $e->getMessage(), 'error');

            return null;
        }
    }
}
