<?php

/*
 * author Arya Permana - Kirin
 * created on 06-02-2025-11h-15m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Fulfilment\StoredItem;

use App\Actions\OrgAction;
use App\Enums\Fulfilment\StoredItem\StoredItemStateEnum;
use App\Models\Fulfilment\StoredItem;

class SetStoredItemQuantityFromPalletStoreItems extends OrgAction
{
    public function handle(StoredItem $storedItem): StoredItem
    {
        $quantity = 0;
        foreach ($storedItem->pallets as $pallet) {
            $quantity += $pallet->pivot->quantity;
        }

        if ($storedItem->state == StoredItemStateEnum::DISCONTINUING && $quantity === 0) {
            //todo: if state=Discontinuing and $quantity=0  call action to set state to Discontinued
        }

        $storedItem->update(
            [
                'total_quantity' => $quantity
            ]
        );

        return $storedItem;
    }

}
