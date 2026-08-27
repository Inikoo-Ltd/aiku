<?php

/*
 * Author: Rifqi <rifqitaufiqurrohman1@gmail.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

namespace App\Actions\Dispatching\DeliveryNoteItem;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Dispatching\DeliveryNoteItem;
use Illuminate\Support\Collection;

trait WithScannedDeliveryNoteItemMatching
{
    /**
     * Items are matched on the org stock code (a manual keyboard fallback) and its outer/SKO
     * CODE 128 barcode only. The unit EAN13 and the trade unit barcodes behind the org stock are
     * deliberately NOT matched: having two scannable barcodes per item made pickers grab loose
     * units when an outer was owed (and vice versa), so the SKO barcode is the only one the
     * warehouse follows. The known cost is that dropshipping notes, whose shelf items carry only
     * the unit EAN, can no longer be picked or packed by scan.
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

        return $deliveryNoteItems->filter(
            fn (DeliveryNoteItem $item) => strcasecmp(trim((string)$item->orgStock?->code), $scanned) === 0
                || strcasecmp(trim((string)$item->orgStock?->barcode), $scanned) === 0
        )->values();
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
     * Dropshipping ships loose units to the end customer while a scan now always means one whole
     * outer/SKO, so a dropshipping picker who scanned the outer barcode is warned the order ships
     * units. Never fires when the SKO holds a single unit: there the outer and the unit are the
     * same thing in hand, nor when the org stock code was typed rather than a barcode scanned.
     */
    protected function scanKindWarning(DeliveryNoteItem $item, string $scanned): ?string
    {
        if ((int)($item->orgStock?->packed_in ?? 1) <= 1) {
            return null;
        }

        if (strcasecmp(trim((string)$item->orgStock?->barcode), $scanned) !== 0) {
            return null;
        }

        if ($item->shop?->type === ShopTypeEnum::DROPSHIPPING) {
            return __('You scanned the outer packing barcode — this order ships individual units');
        }

        return null;
    }
}
