<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Wix\Product;

use App\Models\Dropshipping\WixUser;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class GetWixListedSkus
{
    use AsAction;

    private const int PAGE_SIZE = 100;

    private const int MAX_PAGES = 59;

    /**
     * @return array<string, string> lowercased sku => wix product id
     */
    public function handle(WixUser $wixUser): array
    {
        $listedSkus = [];

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $response = $wixUser->queryProducts([
                'paging' => [
                    'limit'  => self::PAGE_SIZE,
                    'offset' => $page * self::PAGE_SIZE
                ]
            ]);

            $products = Arr::get($response, 'products');

            if (!is_array($products)) {
                return $listedSkus;
            }

            foreach ($products as $product) {
                $productId = Arr::get($product, 'id');

                if (!$productId) {
                    continue;
                }

                foreach ($this->skusOf($product) as $sku) {
                    $listedSkus[Str::lower($sku)] = (string) $productId;
                }
            }

            if (count($products) < self::PAGE_SIZE) {
                return $listedSkus;
            }
        }

        Log::warning('Wix products too many to read in full for bulk matching', [
            'wix_user_id' => $wixUser->id,
            'skus_read'   => count($listedSkus)
        ]);

        return $listedSkus;
    }

    /**
     * A Wix product carries a SKU on the product itself when it has no options, and one per
     * variant when it does.
     *
     * @return array<int, string>
     */
    private function skusOf(array $product): array
    {
        $skus = array_filter([Arr::get($product, 'sku')]);

        foreach (Arr::get($product, 'variants', []) as $variant) {
            $variantSku = Arr::get($variant, 'variant.sku');

            if ($variantSku) {
                $skus[] = $variantSku;
            }
        }

        return $skus;
    }
}
