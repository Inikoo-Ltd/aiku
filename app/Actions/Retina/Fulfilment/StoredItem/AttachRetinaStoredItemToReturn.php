<?php

/*
 * author Arya Permana - Kirin
 * created on 19-03-2025-09h-00m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Fulfilment\StoredItem;

use App\Actions\Fulfilment\StoredItem\AttachStoredItemToReturn;
use App\Actions\RetinaAction;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Fulfilment\PalletStoredItem;
use Lorisleiva\Actions\ActionRequest;

class AttachRetinaStoredItemToReturn extends RetinaAction
{
    private PalletStoredItem $palletStoredItem;

    public function handle(PalletReturn $palletReturn, PalletStoredItem $palletStoredItem, array $modelData)
    {
        AttachStoredItemToReturn::run($palletReturn, $palletStoredItem, $modelData);
    }

    public function rules(): array
    {
        return [
            'quantity_ordered' => ['required', 'numeric', 'min:0', 'max:'.$this->palletStoredItem->quantity],
        ];
    }

    public function asController(PalletReturn $palletReturn, PalletStoredItem $palletStoredItem, ActionRequest $request)
    {
        $this->palletStoredItem = $palletStoredItem;
        $this->initialisation($request);

        $this->handle($palletReturn, $palletStoredItem, $this->validatedData);
    }

    public function action(PalletReturn $palletReturn, PalletStoredItem $palletStoredItem, array $modelData, int $hydratorsDelay = 0)
    {
        $this->asAction = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->palletStoredItem = $palletStoredItem;
        $this->initialisationFulfilmentActions($palletReturn->fulfilmentCustomer, $modelData);

        $this->handle($palletReturn, $palletStoredItem, $this->validatedData);
    }
}
