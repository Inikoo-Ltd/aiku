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

    public function handle(EbayUser $ebayUser): array
    {
        $skus = $this->getInventorySkus($ebayUser);

        if (blank($skus)) {
            return [];
        }

        $offersBySku = $ebayUser->getOffersForSkus($skus);

        $listedSkus = [];

        foreach ($skus as $sku) {
            if (blank($offersBySku[$sku] ?? null)) {
                continue;
            }

            $listedSkus[Str::lower($sku)] = $sku;
        }

        $skippedCount = count($skus) - count($listedSkus);

        if ($skippedCount > 0) {
            Log::info('eBay inventory items without an offer skipped for bulk matching', [
                'ebay_user_id' => $ebayUser->id,
                'skipped'      => $skippedCount
            ]);
        }

        return $listedSkus;
    }

    /**
     * @return array<int, string>
     *
     * @throws \Exception
     */
    private function getInventorySkus(EbayUser $ebayUser): array
    {
        $skus   = [];
        $offset = 0;

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $response = $ebayUser->getProducts(self::PAGE_SIZE, $offset);

            if (Arr::has($response, 'error') || Arr::has($response, 'errors')) {
                return $skus;
            }

            $inventoryItems = Arr::get($response, 'inventoryItems', []);

            foreach ($inventoryItems as $inventoryItem) {
                $sku = Arr::get($inventoryItem, 'sku');

                if ($sku) {
                    $skus[] = $sku;
                }
            }

            if (count($inventoryItems) < self::PAGE_SIZE) {
                return $skus;
            }

            $offset += self::PAGE_SIZE;
        }

        return $skus;
    }
}
