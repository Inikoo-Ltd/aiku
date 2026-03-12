<?php

namespace App\Actions\Fulfilment\PickingSession;

use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydratePickingSessions;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Models\Inventory\PickingSession;

class AutoFinishPackingFulfilmentPickingSession extends OrgAction
{
    use WithActionUpdate;

    public function handle(PickingSession $pickingSession): PickingSession
    {
        $states = $pickingSession->palletReturns()
            ->toBase()
            ->select('pallet_returns.state')
            ->pluck('state')
            ->all();

        if (count($states) === 0) {
            return $pickingSession;
        }

        $allDone = collect($states)->every(function ($state) {
            return in_array($state, [
                PalletReturnStateEnum::DISPATCHED->value,
                PalletReturnStateEnum::CANCEL->value,
            ], true);
        });

        if ($allDone) {
            $this->update($pickingSession, [
                'state'  => PickingSessionStateEnum::PACKING_FINISHED,
                'end_at' => now(),
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
