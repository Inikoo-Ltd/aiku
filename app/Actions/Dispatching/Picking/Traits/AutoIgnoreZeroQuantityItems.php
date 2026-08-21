<?php

/*
 * Author Louis Perez
 * Created on 04-08-2026-17h-06m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Dispatching\Picking\Traits;

use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydrateWaitingItems;
use App\Actions\Dispatching\Picking\StoreNotPickPicking;
use App\Enums\Dispatching\Picking\PickingTypeEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\SysAdmin\User;

trait AutoIgnoreZeroQuantityItems
{
    public function ignoreZeroQuantityItems(DeliveryNote $deliveryNote, ?User $user = null): void
    {
        $zeroQuantityItems = $deliveryNote->deliveryNoteItems()
            ->where('quantity_required', '<=', 0)
            ->whereDoesntHave('pickings', function ($query) {
                $query->where('pickings.type', PickingTypeEnum::NOT_PICK);
            })->get();

        $waitingCleared = false;

        foreach ($zeroQuantityItems as $ignoredItem) {
            StoreNotPickPicking::run($ignoredItem, $user, [
                'quantity' => 0,
            ]);

            /*
             * Nothing is wanted, so nothing is being waited for. The waiting screens hide rows with
             * no quantity required, so flags left on here can never be cleared by a human and the
             * note stays blocked against a line that no longer asks for anything.
             */
            if ($ignoredItem->has_waiting_warehouse || $ignoredItem->has_waiting_crm) {
                $ignoredItem->update([
                    'has_waiting_warehouse'      => false,
                    'has_waiting_crm'            => false,
                    'quantity_waiting_warehouse' => 0,
                    'quantity_waiting_crm'       => 0,
                ]);
                $waitingCleared = true;
            }
        }

        if ($waitingCleared) {
            DeliveryNoteHydrateWaitingItems::run($deliveryNote->id);
        }
    }
}
