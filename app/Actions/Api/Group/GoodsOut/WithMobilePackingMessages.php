<?php

/*
 * Author Louis Perez
 * Created on 13-11-2026-14h-45m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Dispatching\DeliveryNote\DeliveryNoteBoxPackingList;
use App\Actions\Dispatching\DeliveryNote\UpdateState\UpdateDeliveryNoteStatePacked;
use App\Actions\Dispatching\DeliveryNoteItem\UpdateDeliveryNoteItemPacking;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;

trait WithMobilePackingMessages
{
    protected function packedQuantity(DeliveryNoteItem $deliveryNoteItem): float
    {
        return round((float) $deliveryNoteItem->packings()->sum('quantity'), 3);
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function packedMessage(DeliveryNoteItem $deliveryNoteItem, float $packed, string $scanned): array
    {
        $code = $deliveryNoteItem->orgStock?->code ?? $scanned;

        if ($packed <= 0.000001) {
            return ['nothing_changed', __('Nothing was packed for :code.', ['code' => $code])];
        }

        $remaining = UpdateDeliveryNoteItemPacking::quantityLeftToPack($deliveryNoteItem);

        return ['packed', $remaining > 0
            ? __('Packed :quantity x :code, :remaining still to pack', [
                'quantity'  => $this->formatScanQuantity($deliveryNoteItem, $packed),
                'code'      => $code,
                'remaining' => $this->formatScanQuantity($deliveryNoteItem, $remaining),
            ])
            : __('Packed :quantity x :code', [
                'quantity' => $this->formatScanQuantity($deliveryNoteItem, $packed),
                'code'     => $code,
            ])];
    }

    protected function packedButNotPromotedReason(DeliveryNote $deliveryNote): ?string
    {
        $deliveryNote->refresh();

        if ($deliveryNote->state !== DeliveryNoteStateEnum::PACKING) {
            return null;
        }

        $everyLinePacked = $deliveryNote->deliveryNoteItems()->with('packings')->get()
            ->every(fn (DeliveryNoteItem $deliveryNoteItem) => UpdateDeliveryNoteItemPacking::isFullyPacked($deliveryNoteItem));

        if (!$everyLinePacked) {
            return null;
        }

        if ($deliveryNote->hasBlockingItems()) {
            return __('Cannot pack: some items are waiting for a replacement decision or warehouse release');
        }

        if (UpdateDeliveryNoteStatePacked::hasMissingParcelDimensions($deliveryNote)) {
            return __('Enter the dimensions of every parcel before setting as packed');
        }

        return DeliveryNoteBoxPackingList::make()->missingBoxesMessage($deliveryNote);
    }
}
