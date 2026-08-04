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
    public function ignoreZeroQuantityItems(DeliveryNote $deliveryNote, ?User $user = null): void
    {
        $zeroQuantityItems = $deliveryNote->deliveryNoteItems()
            ->where('quantity_required', '<=', 0)
            ->whereDoesntHave('pickings', function ($query) {
                $query->where('pickings.type', PickingTypeEnum::NOT_PICK);
            })->get();

        foreach ($zeroQuantityItems as $ignoredItem) {
            StoreNotPickPicking::run($ignoredItem, $user, [
                'quantity' => 0,
            ]);
        }
    }
}
