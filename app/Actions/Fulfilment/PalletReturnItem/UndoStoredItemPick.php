<?php

/*
 * author Arya Permana - Kirin
 * created on 10-02-2025-13h-08m
 * GitHub: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Fulfilment\PalletReturnItem;

use App\Actions\Fulfilment\PickingSession\AutoFinishPickingFulfilmentPickingSession;
use App\Actions\Fulfilment\PickingSession\CalculateFulfilmentPickingSessionPicks;
use App\Actions\Fulfilment\PalletReturn\SetStoredItemReturnAutoServices;
use App\Actions\Fulfilment\PalletStoredItem\RunPalletStoredItemQuantity;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithFulfilmentWarehouseEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\PalletReturn\PalletReturnItemStateEnum;
use App\Enums\Fulfilment\PalletStoredItem\PalletStoredItemStateEnum;
use App\Models\Fulfilment\PalletReturnItem;
use App\Models\Fulfilment\StoredItemMovement;
use Lorisleiva\Actions\ActionRequest;
use App\Http\Resources\Fulfilment\PalletReturnItemResource;

class UndoStoredItemPick extends OrgAction
{
    use WithFulfilmentWarehouseEditAuthorisation;
    use WithActionUpdate;


    public function handle(PalletReturnItem $palletReturnItem): PalletReturnItem
    {
        $this->update($palletReturnItem, [
            'state'               => PalletReturnItemStateEnum::PICKING,
            'quantity_picked'     => 0,
            'quantity_not_picked' => 0
        ]);

        StoredItemMovement::where('pallet_return_item_id', $palletReturnItem->id)->delete();

        SetStoredItemReturnAutoServices::run($palletReturnItem->palletReturn, true);
        $palletStoredItem = RunPalletStoredItemQuantity::run($palletReturnItem->palletStoredItem);
        if ($palletStoredItem->state === PalletStoredItemStateEnum::RETURNED && (float)$palletStoredItem->quantity > 0) {
            $palletStoredItem->update(['state' => PalletStoredItemStateEnum::ACTIVE]);
        }

        if ($palletReturnItem->picking_session_id && $palletReturnItem->pickingSession) {
            new CalculateFulfilmentPickingSessionPicks()->action($palletReturnItem->pickingSession);
            new AutoFinishPickingFulfilmentPickingSession()->action($palletReturnItem->pickingSession);
        }

        return $palletReturnItem;
    }

    public function asController(PalletReturnItem $palletReturnItem, ActionRequest $request): PalletReturnItem
    {
        $this->initialisationFromWarehouse($palletReturnItem->palletReturn->warehouse, $request);

        return $this->handle($palletReturnItem);
    }

    public function jsonResponse(PalletReturnItem $palletReturnItem, ActionRequest $request): PalletReturnItem|PalletReturnItemResource
    {

        return $palletReturnItem;
    }
}
