<?php

/*
 * Author: Rifqi <rifqitaufiqurrohman1@gmail.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

namespace App\Actions\Dispatching\DeliveryNoteItem;

use App\Models\Dispatching\DeliveryNoteItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

trait WithScannedDeliveryNoteItemMatching
{
    /**
     * Items are matched on the org stock code and its own barcode first, which is what warehouse
     * labels carry, then on the EAN of any trade unit behind the org stock, which is what supplier
     * packaging carries.
     *
     * @param  Collection<int, DeliveryNoteItem>  $deliveryNoteItems
     *
     * @return Collection<int, DeliveryNoteItem>
     */
    protected function matchItems(Collection $deliveryNoteItems, string $scanned): Collection
    {
        if ($scanned === '') {
            return collect();
        }

        $matchedByCode = $deliveryNoteItems->filter(
            fn (DeliveryNoteItem $item) => strcasecmp(trim((string)$item->orgStock?->code), $scanned) === 0
                || strcasecmp(trim((string)$item->orgStock?->barcode), $scanned) === 0
        );

        if ($matchedByCode->isNotEmpty()) {
            return $matchedByCode->values();
        }

        $orgStockIds = $deliveryNoteItems->pluck('org_stock_id')->filter()->unique()->all();

        if (!$orgStockIds) {
            return collect();
        }

        $matchedOrgStockIds = DB::table('model_has_trade_units')
            ->join('trade_units', 'trade_units.id', '=', 'model_has_trade_units.trade_unit_id')
            ->leftJoin('barcodes', 'barcodes.id', '=', 'trade_units.barcode_id')
            ->leftJoin('model_has_barcodes', function ($join) {
                $join->on('model_has_barcodes.model_id', '=', 'trade_units.id')
                    ->where('model_has_barcodes.model_type', '=', 'TradeUnit');
            })
            ->leftJoin('barcodes as attached_barcodes', 'attached_barcodes.id', '=', 'model_has_barcodes.barcode_id')
            ->where('model_has_trade_units.model_type', 'OrgStock')
            ->whereIn('model_has_trade_units.model_id', $orgStockIds)
            ->where(function ($query) use ($scanned) {
                $query->where('barcodes.number', $scanned)
                    ->orWhere('attached_barcodes.number', $scanned);
            })
            ->pluck('model_has_trade_units.model_id')
            ->all();

        if (!$matchedOrgStockIds) {
            return collect();
        }

        return $deliveryNoteItems->whereIn('org_stock_id', $matchedOrgStockIds)->values();
    }
}
