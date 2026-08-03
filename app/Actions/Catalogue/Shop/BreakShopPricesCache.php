<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop;

use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Iris caches that carry a price are keyed on this generation instead of being enumerated and
 * forgotten one by one: bumping it strands every entry of the shop at once. Prices only move when
 * a product price or an offer is edited, so the churn is low enough for the cached rankings to
 * survive most of their ttl.
 */
class BreakShopPricesCache
{
    use AsObject;

    public function handle(?int $shopId): void
    {
        if (!$shopId) {
            return;
        }

        Cache::increment($this->getKey($shopId));
    }

    public function getGeneration(?int $shopId): int
    {
        if (!$shopId) {
            return 0;
        }

        return (int) Cache::get($this->getKey($shopId), 0);
    }

    private function getKey(int $shopId): string
    {
        return 'iris:shop:'.$shopId.':prices_generation';
    }
}
