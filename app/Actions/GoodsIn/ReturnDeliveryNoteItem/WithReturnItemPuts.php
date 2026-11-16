<?php

/*
 * Author Louis Perez
 * Created on 16-11-2026-15h-20m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\GoodsIn\ReturnDeliveryNoteItem;

use App\Actions\GoodsIn\Sowing\StoreSowing;
use App\Enums\GoodsIn\ReturnDeliveryNote\ReturnDeliveryNoteStateEnum;
use App\Enums\GoodsIn\Sowing\SowingTypeEnum;
use App\Models\GoodsIn\ReturnDeliveryNoteItem;
use App\Models\Inventory\LocationOrgStock;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

trait WithReturnItemPuts
{
    protected function ensureReturnIsBeingReturned(ReturnDeliveryNoteItem $returnDeliveryNoteItem): void
    {
        if ($this->asAction) {
            return;
        }

        if ($returnDeliveryNoteItem->returnDeliveryNote->state !== ReturnDeliveryNoteStateEnum::RETURNING) {
            throw ValidationException::withMessages([
                'message' => __('Items can only be handled while the return is being returned to locations.'),
            ]);
        }
    }

    protected function ensureLocationHoldsItem(ReturnDeliveryNoteItem $returnDeliveryNoteItem, mixed $locationOrgStockId): void
    {
        if (!$locationOrgStockId) {
            return;
        }

        $holdsItem = LocationOrgStock::whereKey($locationOrgStockId)
            ->where('org_stock_id', $returnDeliveryNoteItem->org_stock_id)
            ->where('warehouse_id', $returnDeliveryNoteItem->returnDeliveryNote->warehouse_id)
            ->exists();

        if (!$holdsItem) {
            throw ValidationException::withMessages([
                'message' => __('That location does not hold :code. Choose one of its locations.', ['code' => $returnDeliveryNoteItem->orgStock?->code]),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $modelData
     */
    protected function storeLockedPut(ReturnDeliveryNoteItem $returnDeliveryNoteItem, array $modelData, ?float $quantity, ?SowingTypeEnum $type = null): ReturnDeliveryNoteItem
    {
        return DB::transaction(function () use ($returnDeliveryNoteItem, $modelData, $quantity, $type) {
            $returnDeliveryNoteItem = ReturnDeliveryNoteItem::lockForUpdate()->findOrFail($returnDeliveryNoteItem->id);
            $remaining              = (float) $returnDeliveryNoteItem->total_expected_qty - (
                (float) $returnDeliveryNoteItem->total_item_damaged +
                (float) $returnDeliveryNoteItem->total_item_not_returned +
                (float) $returnDeliveryNoteItem->total_item_returned
            );

            $quantity ??= $remaining;
            if ($quantity - $remaining > 0.00005) {
                throw ValidationException::withMessages([
                    'message' => [
                        'quantity' => 'Invalid quantity were given (Exceed maximum possible amount)',
                    ],
                ]);
            }

            $user = auth()->user();

            data_set($modelData, 'quantity', min($quantity, $remaining));
            data_set($modelData, 'sower_user_id', $user->id);
            if ($type) {
                data_set($modelData, 'type', $type);
            }

            StoreSowing::make()->action($returnDeliveryNoteItem, $user, $modelData);
            CalculateReturnDeliveryNoteItemTotalSowed::make()->action($returnDeliveryNoteItem);

            return $returnDeliveryNoteItem;
        });
    }
}
