<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Tiktok\Product;

use App\Actions\Dropshipping\WithPlatformListedProducts;
use App\Models\Dropshipping\TiktokUser;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class SearchTiktokProducts
{
    use AsAction;
    use WithAttributes;
    use WithPlatformListedProducts;

    public $commandSignature = 'dropshipping:tiktok:product:get {tiktokUser}';

    private const int PAGE_SIZE = 100;

    private const int MAX_PAGES = 20;

    /**
     * Only activated products are on sale, and TikTok can only be asked for whole seller SKUs,
     * so the activated listing is read in full and searched here.
     */
    public function handle(?TiktokUser $tiktokUser, $query = '', $offset = 0, $limit = 50): array
    {
        if (!$tiktokUser) {
            return [];
        }

        return $this->pickListedProducts(
            'tiktok-listed-products-'.$tiktokUser->id,
            fn () => $this->fetchListedProducts($tiktokUser),
            (string) $query,
            (int) $offset,
            (int) $limit
        );
    }

    /**
     * @return array<int, array>
     */
    private function fetchListedProducts(TiktokUser $tiktokUser): array
    {
        $listedProducts = [];
        $pageToken      = null;

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $params = ['page_size' => self::PAGE_SIZE];

            if ($pageToken) {
                $params['page_token'] = $pageToken;
            }

            $response = $tiktokUser->getProducts(['status' => 'ACTIVATE'], $params);

            $products = Arr::get($response, 'data.products');

            if (!is_array($products)) {
                return $listedProducts;
            }

            foreach ($products as $product) {
                $listedProducts[] = $this->transformToStandardFormat($product);
            }

            $pageToken = Arr::get($response, 'data.next_page_token');

            if (!$pageToken) {
                return $listedProducts;
            }
        }

        Log::warning('TikTok catalogue too large to read in full for the product picker', [
            'tiktok_user_id' => $tiktokUser->id,
            'products_read'  => count($listedProducts)
        ]);

        return $listedProducts;
    }

    private function transformToStandardFormat($product): array
    {
        return [
            'id'       => (string) Arr::get($product, 'id'),
            'name'     => Arr::get($product, 'title'),
            'slug'     => Arr::get($product, 'skus.0.seller_sku'),
            'code'     => Arr::get($product, 'skus.0.seller_sku'),
            'price'    => Arr::get($product, 'skus.0.price.amount'),
            'sku_list' => array_values(array_filter(
                array_map(fn ($sku) => Arr::get($sku, 'seller_sku'), Arr::get($product, 'skus', []))
            )),
            'images'   => Arr::get($product, 'images')
        ];
    }

    public function asController(TiktokUser $tiktokUser, ActionRequest $request): array
    {
        return $this->handle($tiktokUser);
    }
}
