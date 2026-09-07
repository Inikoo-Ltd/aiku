<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Tiktok\Product;

use App\Models\Dropshipping\TiktokUser;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class GetTiktokListedSkus
{
    use AsAction;

    private const int PAGE_SIZE = 100;

    private const int MAX_PAGES = 200;

    /**
     * Every seller SKU in the shop, keyed by its lowercased form and pointing at the
     * TikTok product id a match has to be made with.
     *
     * @return array<string, string>
     */
    public function handle(TiktokUser $tiktokUser): array
    {
        $listedSkus = [];
        $pageToken  = null;

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $params = ['page_size' => self::PAGE_SIZE];

            if ($pageToken) {
                $params['page_token'] = $pageToken;
            }

            $response = $tiktokUser->getProducts(['status' => 'ACTIVATE'], $params);

            $products = Arr::get($response, 'data.products');

            if (!is_array($products)) {

                return $listedSkus;
            }

            foreach ($products as $product) {
                $productId = Arr::get($product, 'id');

                if (!$productId) {
                    continue;
                }

                foreach (Arr::get($product, 'skus', []) as $sku) {
                    $sellerSku = Arr::get($sku, 'seller_sku');

                    if ($sellerSku) {
                        $listedSkus[Str::lower($sellerSku)] = (string) $productId;
                    }
                }
            }

            $pageToken = Arr::get($response, 'data.next_page_token');

            if (!$pageToken) {
                return $listedSkus;
            }
        }

        return $listedSkus;
    }
}
