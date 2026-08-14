<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Ebay\Product;

use App\Models\Dropshipping\EbayUser;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class GetEbayListedSkus
{
    use AsAction;

    private const int PAGE_SIZE = 50;

    private const int MAX_PAGES = 400;

    /**
     * Every SKU the seller has on eBay, keyed by its lowercased form so the comparison
     * survives the casing drifting between Aiku and what was typed into eBay.
     *
     * eBay offers are looked up by SKU rather than by id, so the SKU is what a match needs.
     *
     * @return array<string, string>
     *
     * @throws \Exception
     */
    public function handle(EbayUser $ebayUser): array
    {
        $listedSkus = [];
        $offset     = 0;

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $response = $ebayUser->getProducts(self::PAGE_SIZE, $offset);

            if (Arr::has($response, 'error') || Arr::has($response, 'errors')) {
                return $listedSkus;
            }

            $inventoryItems = Arr::get($response, 'inventoryItems', []);

            foreach ($inventoryItems as $inventoryItem) {
                $sku = Arr::get($inventoryItem, 'sku');

                if ($sku) {
                    $listedSkus[Str::lower($sku)] = $sku;
                }
            }

            if (count($inventoryItems) < self::PAGE_SIZE) {
                return $listedSkus;
            }

            $offset += self::PAGE_SIZE;
        }

        Log::warning('eBay inventory too large to read in full for bulk matching', [
            'ebay_user_id' => $ebayUser->id,
            'skus_read'    => count($listedSkus)
        ]);

        return $listedSkus;
    }
}
