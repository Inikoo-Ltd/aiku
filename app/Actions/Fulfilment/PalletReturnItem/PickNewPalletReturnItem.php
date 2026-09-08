<?php

/*
 * author Arya Permana - Kirin
 * created on 10-02-2025-14h-39m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Fulfilment\PalletReturnItem;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithFulfilmentWarehouseEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Fulfilment\PalletReturnItem;
use App\Models\Fulfilment\PalletStoredItem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class PickNewPalletReturnItem extends OrgAction
{
    use WithFulfilmentWarehouseEditAuthorisation;
    use WithActionUpdate;

    /**
     * @throws \Throwable
     */
    public function handle(PalletReturn $palletReturn, PalletStoredItem $palletStoredItem, array $modelData): void
    {
        DB::transaction(function () use ($modelData, $palletReturn, $palletStoredItem) {
            $quantityOrdered = Arr::pull($modelData, 'quantity_ordered');

            $palletReturn->storedItems()->attach([
                $palletStoredItem->storedItem->id => [
                    'type'                 => 'StoredItem',
                    'pallet_id'            => $palletStoredItem->pallet_id,
                    'pallet_stored_item_id' => $palletStoredItem->id,
                    'quantity_ordered'      => $quantityOrdered
                ]
            ]);

            $palletReturnItem = PalletReturnItem::where([
                'pallet_return_id' => $palletReturn->id,
                'pallet_id'   => $palletStoredItem->pallet->id,
            ])->first();

            PickPalletReturnItemInPalletReturnWithStoredItem::run($palletReturnItem, [
                'quantity_picked' => $quantityOrdered
            ]);
        });
    }

    public function rules(): array
    {
        return [
            'quantity_ordered'       => ['sometimes', 'numeric', 'min:0'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(PalletReturn $palletReturn, PalletStoredItem $palletStoredItem, ActionRequest $request): void
    {
        $this->initialisationFromWarehouse($palletReturn->warehouse, $request);

        $this->handle($palletReturn, $palletStoredItem, $this->validatedData);
    }
}
