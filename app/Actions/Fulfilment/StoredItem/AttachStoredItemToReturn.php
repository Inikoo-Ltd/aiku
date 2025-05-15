<?php

/*
 * author Arya Permana - Kirin
 * created on 07-02-2025-10h-24m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Fulfilment\StoredItem;

use App\Actions\Fulfilment\PalletReturn\SetStoredItemReturnAutoServices;
use App\Actions\OrgAction;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Fulfilment\PalletReturnItem;
use App\Models\Fulfilment\PalletStoredItem;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class AttachStoredItemToReturn extends OrgAction
{
    private PalletStoredItem $palletStoredItem;

    public function handle(PalletReturn $palletReturn, PalletStoredItem $palletStoredItem, array $modelData)
    {
        $quantityOrdered = Arr::pull($modelData, 'quantity_ordered');
        if ($quantityOrdered == 0) {
            $palletReturn->storedItems()->detach($palletStoredItem->storedItem->id);
        } else {

            if ($palletReturnItem = PalletReturnItem::where('pallet_return_id', $palletReturn->id)->where('pallet_stored_item_id', $palletStoredItem->id)->first()) {
                $palletReturnItem->update([
                    'quantity_ordered' => $quantityOrdered
                ]);

            } else {
                $palletReturn->storedItems()->attach(
                    [
                        $palletStoredItem->storedItem->id => [
                        'type'                 => 'StoredItem',
                        'pallet_id'            => $palletStoredItem->pallet_id,
                        'pallet_stored_item_id' => $palletStoredItem->id,
                        'quantity_ordered'      => $quantityOrdered,
                        'picking_location_id'   => $palletStoredItem->pallet->location_id
                        ]
                    ]
                );
            }
        }
        $palletReturn = SetStoredItemReturnAutoServices::run($palletReturn);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("fulfilment.{$this->fulfilment->id}.edit");
    }

    public function rules(): array
    {
        return [
            'quantity_ordered' => ['required', 'numeric', 'min:0', 'max:'.$this->palletStoredItem->quantity]
        ];
    }

    public function asController(PalletReturn $palletReturn, PalletStoredItem $palletStoredItem, ActionRequest $request)
    {
        $this->palletStoredItem = $palletStoredItem;
        $this->initialisationFromFulfilment($palletReturn->fulfilment, $request);

        $this->handle($palletReturn, $palletStoredItem, $this->validatedData);
    }

    public function action(PalletReturn $palletReturn, PalletStoredItem $palletStoredItem, array $modelData, int $hydratorsDelay = 0)
    {
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->palletStoredItem = $palletStoredItem;
        $this->initialisationFromFulfilment($palletReturn->fulfilment, $modelData);

        $this->handle($palletReturn, $palletStoredItem, $this->validatedData);
    }
}
