<?php

namespace App\Actions\Fulfilment\PickingSession;

use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydratePickingSessions;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnItemStateEnum;
use App\Models\Fulfilment\PalletReturnItem;
use App\Models\Inventory\PickingSession;

class AutoFinishPickingFulfilmentPickingSession extends OrgAction
{
    use WithActionUpdate;

    public function handle(PickingSession $pickingSession): PickingSession
    {
        // Total items that are not cancelled
        $numberItems = PalletReturnItem::where('picking_session_id', $pickingSession->id)
            ->where('state', '!=', PalletReturnItemStateEnum::CANCEL)
            ->count();

        // Items considered "handled" (picked, not_picked, or dispatched)
        // Adjust these states based on business logic. Usually 'picked' means done picking.
        $numberHandled = PalletReturnItem::where('picking_session_id', $pickingSession->id)
            ->whereIn('state', [
                PalletReturnItemStateEnum::PICKED,
                PalletReturnItemStateEnum::NOT_PICKED, // Treated as handled (failed pick)
                PalletReturnItemStateEnum::DISPATCHED
            ])
            ->count();

        if ($numberItems > 0 && $numberHandled == $numberItems) {
            $this->update($pickingSession, [
                'state' => PickingSessionStateEnum::PICKING_FINISHED
            ]);
            WarehouseHydratePickingSessions::dispatch($pickingSession->warehouse);
        }

        return $pickingSession;
    }

    public function action(PickingSession $pickingSession): PickingSession
    {
        $this->initialisationFromWarehouse($pickingSession->warehouse, []);

        return $this->handle($pickingSession);
    }
}
