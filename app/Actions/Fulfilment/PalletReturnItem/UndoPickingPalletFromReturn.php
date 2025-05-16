<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 20 Jun 2024 10:36:39 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletReturnItem;

use App\Actions\Fulfilment\Pallet\UpdatePallet;
use App\Actions\Fulfilment\PalletReturn\AutomaticallySetPalletReturnAsPickedIfAllItemsPicked;
use App\Actions\Fulfilment\PalletReturn\SetStoredItemReturnAutoServices;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnItemStateEnum;
use App\Http\Resources\Fulfilment\PalletReturnItemUIResource;
use App\Models\Fulfilment\PalletReturnItem;
use Lorisleiva\Actions\ActionRequest;
use App\Http\Resources\Fulfilment\MayaPalletReturnItemUIResource;

class UndoPickingPalletFromReturn extends OrgAction
{
    use WithActionUpdate;

    public function handle(PalletReturnItem $palletReturnItem): PalletReturnItem
    {
        if ($palletReturnItem->type == 'Pallet') {
            UpdatePallet::run($palletReturnItem->pallet, [
                'state' => PalletStateEnum::PICKING
            ]);

            $palletReturnItem = $this->update($palletReturnItem, [
                'quantity_picked' => 0,
                'state'           => PalletReturnItemStateEnum::PICKING
            ]);
        } else {
            $storedItems = PalletReturnItem::where('pallet_return_id', $palletReturnItem->pallet_return_id)->where('stored_item_id', $palletReturnItem->stored_item_id)->get();
            foreach ($storedItems as $storedItem) {
                $palletReturnItem = $this->update($storedItem, [
                    'quantity_picked' => 0,
                    'state'           => PalletReturnItemStateEnum::PICKING
                ]);
            }
            SetStoredItemReturnAutoServices::run($palletReturnItem->palletReturn, true);
        }

        AutomaticallySetPalletReturnAsPickedIfAllItemsPicked::run($palletReturnItem->palletReturn);

        return $palletReturnItem;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("fulfilment.{$this->warehouse->id}.edit");
    }

    public function asController(PalletReturnItem $palletReturnItem, ActionRequest $request): PalletReturnItem
    {
        $this->initialisationFromWarehouse($palletReturnItem->palletReturn->warehouse, $request);

        return $this->handle($palletReturnItem);
    }


    public function jsonResponse(PalletReturnItem $palletReturnItem, ActionRequest $request): PalletReturnItemUIResource|MayaPalletReturnItemUIResource
    {
        if ($request->hasHeader('Maya-Version')) {
            return MayaPalletReturnItemUIResource::make($palletReturnItem);
        }

        return new PalletReturnItemUIResource($palletReturnItem);
    }
}
