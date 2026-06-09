<?php

/*
 * author Arya Permana - Kirin
 * created on 06-02-2025-11h-15m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Fulfilment\StoredItem;

use App\Actions\Fulfilment\PalletStoredItem\RunPalletStoredItemQuantity;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\StoredItem\StoredItemStateEnum;
use App\Models\Fulfilment\PalletStoredItem;
use App\Models\Fulfilment\StoredItem;
use Illuminate\Console\Command;

class RunStoredItemQuantity extends OrgAction
{
    use WithActionUpdate;

    public function handle(StoredItem $storedItem, ?Command $command = null): StoredItem
    {
        $quantity = 0;
        foreach ($storedItem->pallets as $pallet) {
            $palletStoredItem         = PalletStoredItem::find($pallet->pivot->id);
            $palletStoredItem         = RunPalletStoredItemQuantity::run($palletStoredItem);
            $palletStoredItemQuantity = $palletStoredItem->quantity;
            $quantity                 = $quantity + $palletStoredItemQuantity;
            $command?->line(' >> '.$pallet->reference."\t\t".$palletStoredItemQuantity);
        }

        if ($storedItem->state == StoredItemStateEnum::DISCONTINUING && $quantity === 0) {
            //todo: if state=Discontinuing and $quantity=0  call action to set state to Discontinued
        }

        $storedItem = $this->update($storedItem, [
            'total_quantity' => $quantity
        ]);

        $command?->line($storedItem->reference.' '.$quantity);

        return $storedItem;
    }

    public string $commandSignature = 'stored_item:run_quantity {storedItem?}';

    public function asCommand(Command $command): int
    {
        $storedItem = $command->argument('storedItem');
        $storedItem = StoredItem::where('id', $storedItem)->first();

        if (!$storedItem) {
            $storedItems = StoredItem::all();
            foreach ($storedItems as $storedItem) {
                $this->handle($storedItem, $command);
            }

            return 0;
        }

        $this->handle($storedItem, $command);

        return 0;
    }
}
