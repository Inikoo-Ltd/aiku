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
     * Items are matched on the org stock code, its outer/SKO CODE 128 barcode (what's printed on
     * the external packing) and its unit_barcode (the EAN13 of the individual unit) first, which is
     * what warehouse labels and supplier packaging carry, then on the EAN of any trade unit behind
     * the org stock. A scan can't tell which of the two barcodes it read, so both are checked.
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
                || strcasecmp(trim((string)$item->orgStock?->unit_barcode), $scanned) === 0
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

    /**
     * Tells the caller which of the two physical barcodes the scan actually read, so it can warn a
     * picker who grabbed a loose unit instead of an outer (or the reverse) without blocking the pick:
     * the item resolves fine either way, only the physical thing in hand might be wrong.
     */
    protected function matchedKind(DeliveryNoteItem $item, string $scanned): string
    {
        if (strcasecmp(trim((string)$item->orgStock?->barcode), $scanned) === 0) {
            return 'sko';
        }

        if (strcasecmp(trim((string)$item->orgStock?->unit_barcode), $scanned) === 0) {
            return 'unit';
        }

        if (strcasecmp(trim((string)$item->orgStock?->code), $scanned) === 0) {
            return 'code';
        }

        foreach ($item->orgStock?->tradeUnits ?? [] as $tradeUnit) {
            if (strcasecmp(trim((string)$tradeUnit->barcode), $scanned) === 0) {
                return 'unit';
            }
        }

        return 'code';
    }

    /**
     * Dropshipping ships loose units to the end customer, everything else ships outers/SKOs picked
     * as a whole. The warning only fires when the scan kind disagrees with what the shop expects,
     * and never when the SKO holds a single unit: there the outer and the unit are the same thing
     * in hand, so whichever of the two barcodes was read, the picker took the right item.
     */
    protected function scanKindWarning(DeliveryNoteItem $item, string $matchedKind): ?string
    {
        if ((int)($item->orgStock?->packed_in ?? 1) <= 1) {
            return null;
        }

        $isDropshipping = $item->shop?->type === \App\Enums\Catalogue\Shop\ShopTypeEnum::DROPSHIPPING;

        if ($isDropshipping && $matchedKind === 'sko') {
            return __('You scanned the outer packing barcode — this order ships individual units');
        }

        if (!$isDropshipping && $matchedKind === 'unit') {
            $warning = __('You scanned a unit barcode, not the outer — check you are not picking loose units');

            if ($item->orgStock?->packed_in) {
                $warning .= ' (' . __('1 SKO = :packed_in units', ['packed_in' => $item->orgStock->packed_in]) . ')';
            }

            return $warning;
        }

        return null;
    }
}
