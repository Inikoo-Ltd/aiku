<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 20 Jun 2024 10:36:39 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletReturnItem;

use App\Actions\Fulfilment\Pallet\UpdatePallet;
use App\Actions\Fulfilment\PickingSession\AutoFinishPickingFulfilmentPickingSession;
use App\Actions\Fulfilment\PickingSession\CalculateFulfilmentPickingSessionPicks;
use App\Actions\Fulfilment\PalletReturn\SetStoredItemReturnAutoServices;
use App\Actions\Fulfilment\PalletStoredItem\RunPalletStoredItemQuantity;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Enums\Fulfilment\PalletStoredItem\PalletStoredItemStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnItemStateEnum;
use App\Http\Resources\Fulfilment\PalletReturnItemUIResource;
use App\Models\Fulfilment\PalletReturnItem;
use App\Models\Fulfilment\StoredItemMovement;
use Lorisleiva\Actions\ActionRequest;
use App\Http\Resources\Fulfilment\MayaPalletReturnItemUIResource;

class UndoPickingPalletFromReturn extends OrgAction
{
    use WithActionUpdate;

    public function handle(PalletReturnItem $palletReturnItem): PalletReturnItem
    {
        if ($palletReturnItem->type == 'Pallet') {
            UpdatePallet::run($palletReturnItem->pallet, [
                'state'     => PalletStateEnum::PICKING,
                'status'    => PalletStatusEnum::STORING,
                'picked_at' => null,
            ]);

            $palletReturnItem = $this->update($palletReturnItem, [
                'quantity_picked' => 0,
                'state'           => PalletReturnItemStateEnum::PICKING
            ]);

            foreach ($palletReturnItem->pallet->palletStoredItems as $palletStoredItem) {
                $movements = StoredItemMovement::where('pallet_return_item_id', $palletReturnItem->id)
                    ->where('pallet_id', $palletStoredItem->pallet_id)
                    ->where('stored_item_id', $palletStoredItem->stored_item_id)
                    ->get();

                foreach ($movements as $movement) {
                    $movement->delete();
                }

                $palletStoredItem->update([
                    'state' => PalletStoredItemStateEnum::ACTIVE,
                ]);

                RunPalletStoredItemQuantity::run($palletStoredItem);
            }

            SetStoredItemReturnAutoServices::run($palletReturnItem->palletReturn, true);
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

        if ($palletReturnItem->picking_session_id && $palletReturnItem->pickingSession) {
            (new CalculateFulfilmentPickingSessionPicks())->action($palletReturnItem->pickingSession);
            (new AutoFinishPickingFulfilmentPickingSession())->action($palletReturnItem->pickingSession);
        }

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
