<?php

/*
 * author Arya Permana - Kirin
 * created on 11-02-2025-14h-10m
 * GitHub: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Fulfilment\PalletStoredItem;

use App\Enums\Fulfilment\PalletStoredItem\PalletStoredItemStateEnum;
use App\Models\Fulfilment\PalletStoredItem;
use Lorisleiva\Actions\Concerns\AsObject;

class SetPalletStoredItemStateToReturned
{
    use AsObject;

    public function handle(PalletStoredItem $palletStoredItem): PalletStoredItem
    {
        $palletStoredItem->update(
            [
                'state' => PalletStoredItemStateEnum::RETURNED
            ]
        );


        return $palletStoredItem;
    }
}
