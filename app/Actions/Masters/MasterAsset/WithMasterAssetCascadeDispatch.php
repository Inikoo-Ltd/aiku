<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 04 Aug 2026 20:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset;

use App\Actions\Masters\MasterAsset\Hydrators\MasterAssetHydrateMismatch;
use App\Models\Masters\MasterAsset;
use Illuminate\Support\Facades\Bus;

/**
 * A fan-out over a master's children is only instant for a handful of products:
 * each one re-syncs stocks or prices, breaks its webpage cache and bans varnish.
 * Small masters run inline so the page comes back already correct, bigger ones are
 * queued and report progress over the master's broadcast channel instead of holding
 * the request open. Threshold shared with UpdateMasterAssetPrices.
 *
 * The mismatch hydration is part of the chain, never a separate call by the caller:
 * run eagerly it would read the products as they were before the queued fix touched
 * them and leave every flag stale, which reads as "the fix did nothing".
 */
trait WithMasterAssetCascadeDispatch
{
    protected function cascadeToChildren(MasterAsset $masterProduct, bool $tradeUnits, bool $prices): void
    {
        if ($this->fanOutRunsInline($masterProduct)) {
            if ($tradeUnits) {
                FixProductTradeUnitsFromMaster::run($masterProduct);
            }
            if ($prices) {
                CascadeMasterAssetPricesToChildren::run($masterProduct);
            }
            MasterAssetHydrateMismatch::run($masterProduct->refresh());

            return;
        }

        $jobs = [];
        if ($tradeUnits) {
            $jobs[] = FixProductTradeUnitsFromMaster::makeJob($masterProduct);
        }
        if ($prices) {
            $jobs[] = CascadeMasterAssetPricesToChildren::makeJob($masterProduct);
        }
        $jobs[] = MasterAssetHydrateMismatch::makeJob($masterProduct);

        Bus::chain($jobs)->dispatch();
    }

    private function fanOutRunsInline(MasterAsset $masterProduct): bool
    {
        return $masterProduct->products()->count() <= UpdateMasterAssetPrices::SYNC_CASCADE_MAX_PRODUCTS;
    }
}
