<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

trait WithPlatformListedProducts
{
    protected const int LISTED_PRODUCTS_CACHE_TTL = 300;

    protected const array LISTED_PRODUCTS_SEARCH_FIELDS = ['name', 'title', 'code', 'slug', 'reference'];

    /**
     * What a portfolio can be matched against is what the seller actually has listed, and none
     * of the platforms can both search and page that list the way the picker needs. So the whole
     * listing is read once, held briefly, and then searched and paged here.
     *
     * @param  callable(): array<int, array>  $fetchListedProducts
     * @return array<int, array>
     */
    protected function pickListedProducts(
        string $cacheKey,
        callable $fetchListedProducts,
        string $query = '',
        int $offset = 0,
        int $limit = 50
    ): array {
        $listedProducts = Cache::remember($cacheKey, self::LISTED_PRODUCTS_CACHE_TTL, $fetchListedProducts);

        if (filled(trim($query))) {
            $listedProducts = $this->filterListedProducts($listedProducts, $query);
        }

        return array_slice($listedProducts, max(0, $offset), max(1, $limit));
    }

    /**
     * @param  array<int, array>  $listedProducts
     * @return array<int, array>
     */
    private function filterListedProducts(array $listedProducts, string $query): array
    {
        $needle = Str::lower(trim($query));

        return array_values(array_filter(
            $listedProducts,
            fn ($listedProduct) => $this->listedProductMatches($listedProduct, $needle)
        ));
    }

    private function listedProductMatches(array $listedProduct, string $needle): bool
    {
        foreach (self::LISTED_PRODUCTS_SEARCH_FIELDS as $field) {
            if (Str::contains(Str::lower((string) Arr::get($listedProduct, $field)), $needle)) {
                return true;
            }
        }

        foreach (collect(Arr::get($listedProduct, 'sku_list', []))->all() as $sku) {
            if (Str::contains(Str::lower((string) $sku), $needle)) {
                return true;
            }
        }

        return false;
    }
}
