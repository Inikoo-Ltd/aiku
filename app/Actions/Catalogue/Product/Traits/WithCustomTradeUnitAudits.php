<?php

/*
 * Author Louis Perez
 * Created on 23-07-2026-14h-58m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Catalogue\Product\Traits;

use App\Models\Catalogue\Product;
use App\Models\Masters\MasterAsset;
use Illuminate\Support\Facades\Event;
use OwenIt\Auditing\Events\AuditCustom;

trait WithCustomTradeUnitAudits
{
    public function dispatchCustomAuditTradeUnit(MasterAsset|Product $parent, $oldTradeUnits)
    {
        $oldTradeUnits = $oldTradeUnits->mapWithKeys(function ($item) {
            return [
                "{$item->code}" => trimDecimalZeros(round(floatval($item->pivot->quantity), 2)) . ' ' . $item->type
            ];
        });

        $newTradeUnits = $parent->tradeUnits->mapWithKeys(function ($item) {
            return [
                "{$item->code}" => trimDecimalZeros(round(floatval($item->pivot->quantity), 2)) . ' ' . $item->type
            ];
        });

        $parent->auditEvent = 'update_trade_units';
        $parent->isCustomEvent = true;

        $parent->auditCustomOld = $oldTradeUnits->toArray();
        $parent->auditCustomNew = $newTradeUnits->toArray();

        Event::dispatch(new AuditCustom($parent));
    }
}
