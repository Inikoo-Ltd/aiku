<?php

/*
 * author Arya Permana - Kirin
 * created on 06-02-2025-10h-56m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Fulfilment\PalletStoredItem;

use App\Actions\Fulfilment\StoredItem\SetStoredItemQuantityFromPalletStoreItems;
use App\Enums\Fulfilment\StoredItemAuditDelta\StoredItemAuditDeltaStateEnum;
use App\Enums\Fulfilment\StoredItemMovement\StoredItemMovementTypeEnum;
use App\Models\Fulfilment\PalletStoredItem;
use App\Models\Fulfilment\StoredItemAuditDelta;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class RunPalletStoredItemQuantity
{
    use AsAction;

    public string $commandSignature = 'pallet_stored_item:run_quantity {palletStoredItem?}';

    public function handle(PalletStoredItem $palletStoredItem): PalletStoredItem
    {
        $quantity = $this->processQuantity($palletStoredItem);
        $palletStoredItem->update(
            [
                'quantity' => $quantity
            ]
        );

        $palletStoredItem->refresh();

        SetStoredItemQuantityFromPalletStoreItems::run($palletStoredItem->storedItem);

        return $palletStoredItem;
    }

    public function processQuantity(PalletStoredItem $palletStoredItem)
    {


        if ($palletStoredItem->in_process) {
            return 0;
        }

        $delta = StoredItemAuditDelta::where('pallet_id', $palletStoredItem->pallet_id)
            ->where('stored_item_id', $palletStoredItem->stored_item_id)
            ->where('state', StoredItemAuditDeltaStateEnum::COMPLETED)
            ->latest()
            ->first();

        if (!$delta) {
            return 0;
        }

        $auditedQuantity = $delta->audited_quantity;



        $quantityMovements = DB::table('stored_item_movements')->where('pallet_id', $palletStoredItem->pallet_id)
            ->where('stored_item_id', $palletStoredItem->stored_item_id)
            ->whereNotIn('type', [StoredItemMovementTypeEnum::AUDIT_ADDITION, StoredItemMovementTypeEnum::AUDIT_SUBTRACTION])
            ->where('moved_at', '>=', $delta->audited_at)
            ->sum('quantity');

        return $auditedQuantity + $quantityMovements;
    }

    public function asCommand(Command $command): int
    {
        $palletStoredItem = $command->argument('palletStoredItem');
        $palletStoredItem = PalletStoredItem::where('id', $palletStoredItem)->first();

        if (!$palletStoredItem) {
            $palletStoredItems = PalletStoredItem::all();
            foreach ($palletStoredItems as $palletStoredItem) {
                $this->handle($palletStoredItem);
            }

            return 0;
        }
        $this->handle($palletStoredItem);

        return 0;
    }
}
