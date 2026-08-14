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
     * A scan counts one physical thing taken off the shelf, and which thing that is depends on the
     * barcode that was read. Delivery note quantities are written in outers/SKOs, so a unit EAN is
     * worth 1/packed_in of one while the outer barcode and the plain code are worth a whole one.
     * Null passes through untouched: it means 'whatever is left on the line', not a count of things.
     */
    protected function scannedQuantityInStockUnits(DeliveryNoteItem $item, string $scanned, ?float $scannedQuantity): ?float
    {
        if ($scannedQuantity === null) {
            return null;
        }

        $packedIn = (float)($item->orgStock?->packed_in ?? 1);

        if ($packedIn <= 1 || $this->matchedKind($item, $scanned) !== 'unit') {
            return $scannedQuantity;
        }

        return round($scannedQuantity / $packedIn, 6);
    }

    /**
     * Quantities of an item that comes in a pack read as units over that pack, the same 5/12 the
     * picking table shows, because 0.416667 tells a picker nothing about what to take off the shelf.
     * Anything past a full pack carries out as a whole number first: 31 units of a 16-pack is one
     * outer and 15 loose, which is what the picker walks to the shelf holding, not 31/16 of one.
     */
    protected function formatScanQuantity(DeliveryNoteItem $item, float $quantity): string
    {
        $packedIn = (int)($item->orgStock?->packed_in ?? 1);

        if ($packedIn <= 1) {
            return (string)($quantity + 0);
        }

        $units     = (int)round($quantity * $packedIn);
        $wholePack = intdiv($units, $packedIn);
        $loose     = $units % $packedIn;

        if ($loose === 0) {
            return (string)$wholePack;
        }

        if ($wholePack === 0) {
            return $loose.'/'.$packedIn;
        }

        return $wholePack.' '.$loose.'/'.$packedIn;
    }

    /**
     * The same split as formatScanQuantity, handed over as the [quotient, [dividend, divisor]] tuple
     * FractionDisplay draws, so the scan panel can set 15/16 in real fraction type instead of the
     * flat string a sentence placeholder is limited to. Null for an item that has no pack to divide.
     *
     * @return array{0: int, 1: array{0: int, 1: int}}|null
     */
    protected function scanQuantityFraction(DeliveryNoteItem $item, float $quantity): ?array
    {
        $packedIn = (int)($item->orgStock?->packed_in ?? 1);

        if ($packedIn <= 1) {
            return null;
        }

        $units = (int)round($quantity * $packedIn);

        return [intdiv($units, $packedIn), [$units % $packedIn, $packedIn]];
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
