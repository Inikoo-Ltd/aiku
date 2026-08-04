<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 04 Aug 2026 22:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset;

use App\Models\Catalogue\Product;
use OwenIt\Auditing\Events\AuditCustom;
use Illuminate\Support\Facades\Event;

/**
 * Clearing a not_follow_master_* flag changes what a shop sells and at what price,
 * so it is audited per product rather than updated quietly: the flag is the whole
 * decision and "who un-rebelled this shop" has to be answerable later.
 */
trait WithKillMasterAssetRebels
{
    protected function killRebelFlags(Product $product, bool $killTradeUnits, bool $killPrices): bool
    {
        $changes = [];
        if ($killTradeUnits && $product->not_follow_master_trade_units) {
            $changes['not_follow_master_trade_units'] = false;
        }
        if ($killPrices && $product->not_follow_master_prices) {
            $changes['not_follow_master_prices'] = false;
        }

        if (!$changes) {
            return false;
        }

        $old = array_map(fn ($field) => true, $changes);
        $product->updateQuietly($changes);

        $product->auditEvent    = 'killed_rebel';
        $product->isCustomEvent = true;
        $product->auditCustomOld = $old;
        $product->auditCustomNew = $changes;
        Event::dispatch(new AuditCustom($product));

        return true;
    }
}
