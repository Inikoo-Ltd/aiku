<?php

/*
 * Author Louis Perez
 * Created on 04-08-2026-17h-06m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Dispatching\Picking\Traits;

use App\Actions\Dispatching\Picking\StoreNotPickPicking;
use App\Enums\Dispatching\Picking\PickingTypeEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\SysAdmin\User;

trait AutoIgnoreZeroQuantityItems
{
    public function ignoreZeroQuantityItems (DeliveryNote $deliveryNote, ?User $user = null): void
    {
        foreach (
            $deliveryNote->deliveryNoteItems()
                ->where('quantity_required', '<=', 0.000001)
                ->get() as $ignoredItem
            ) {

            if ($ignoredItem->pickings()->where('pickings.type', PickingTypeEnum::NOT_PICK)->exists()) continue;

            StoreNotPickPicking::run($ignoredItem, $user, [
                'quantity'  => 0,
            ]);
        }

    }
}
