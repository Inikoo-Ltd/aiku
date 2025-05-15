<?php

/*
 * author Arya Permana - Kirin
 * created on 10-02-2025-13h-08m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Fulfilment\PalletReturnItem;

use App\Actions\Fulfilment\PalletReturn\AutomaticallySetPalletReturnAsPickedIfAllItemsPicked;
use App\Actions\Fulfilment\PalletReturn\SetStoredItemReturnAutoServices;
use App\Actions\Fulfilment\PalletStoredItem\SetPalletStoredItemStateToReturned;
use App\Actions\Fulfilment\StoredItemMovement\StoreStoredItemMovementFromPicking;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithFulfilmentShopAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Http\Resources\Fulfilment\PalletReturnItemResource;
use App\Http\Resources\Fulfilment\PalletReturnItemUIResource;
use App\Models\Fulfilment\PalletReturnItem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class PickPalletReturnItemInPalletReturnWithStoredItem extends OrgAction
{
    use WithFulfilmentShopAuthorisation;
    use WithActionUpdate;


    /**
     * @throws \Throwable
     */
    public function handle(PalletReturnItem $palletReturnItem, array $modelData): PalletReturnItem
    {
        return DB::transaction(function () use ($palletReturnItem, $modelData) {
            $quantity = Arr::get($modelData, 'quantity_picked');
            $palletStoredItemQuantity = $palletReturnItem->palletStoredItem->quantity;

            $this->update($palletReturnItem, $modelData);

            StoreStoredItemMovementFromPicking::run($palletReturnItem, [
                'quantity' => $quantity
            ]);

            if ($quantity == $palletStoredItemQuantity) {
                SetPalletStoredItemStateToReturned::run($palletReturnItem->palletStoredItem);
            }

            SetStoredItemReturnAutoServices::run($palletReturnItem->palletReturn, true);
            AutomaticallySetPalletReturnAsPickedIfAllItemsPicked::run($palletReturnItem->palletReturn);

            return $palletReturnItem;
        });
    }

    public function rules(): array
    {
        return [
            'quantity_picked'       => ['sometimes', 'numeric', 'min:0'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(PalletReturnItem $palletReturnItem, ActionRequest $request): PalletReturnItem
    {
        $this->initialisationFromFulfilment($palletReturnItem->palletReturn->fulfilment, $request);

        return $this->handle($palletReturnItem, $this->validatedData);
    }

    public function action(PalletReturnItem $palletReturnItem, array $modelData): PalletReturnItem
    {
        $this->asAction = true;
        $this->initialisationFromFulfilment($palletReturnItem->palletReturn->fulfilment, $modelData);

        return $this->handle($palletReturnItem, $this->validatedData);
    }

    public function jsonResponse(PalletReturnItem $palletReturnItem, ActionRequest $request): PalletReturnItemResource|PalletReturnItemUIResource
    {
        if ($request->hasHeader('Maya-Version')) {
            return PalletReturnItemResource::make($palletReturnItem);
        }
        return PalletReturnItemUIResource::make($palletReturnItem);
    }
}
