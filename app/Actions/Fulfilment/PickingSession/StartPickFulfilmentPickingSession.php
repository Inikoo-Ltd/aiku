<?php

namespace App\Actions\Fulfilment\PickingSession;

use App\Actions\Fulfilment\PalletReturn\PickingPalletReturn;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydratePickingSessions;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Models\Inventory\PickingSession;
use Lorisleiva\Actions\ActionRequest;
use App\Models\Inventory\Warehouse;

class StartPickFulfilmentPickingSession extends OrgAction
{
    use WithActionUpdate;

    /**
     * @throws \Throwable
     */
    public function handle(PickingSession $pickingSession, array $modelData): PickingSession
    {
        data_set($modelData, 'state', PickingSessionStateEnum::HANDLING);
        data_set($modelData, 'start_at', now());

        $palletReturns = $pickingSession->palletReturns;

        foreach ($palletReturns as $palletReturn) {
            $state = $palletReturn->state;
            if (in_array($state, [
                PalletReturnStateEnum::CONFIRMED,
                PalletReturnStateEnum::SUBMITTED
            ])) {
                PickingPalletReturn::make()->action($palletReturn);
            }
        }

        $pickingSession = $this->update($pickingSession, $modelData);

        WarehouseHydratePickingSessions::dispatch($pickingSession->warehouse);

        return $pickingSession;
    }

    /**
     * @throws \Throwable
     */
    public function asController(Warehouse $warehouse, PickingSession $pickingSession, ActionRequest $request): PickingSession
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($pickingSession, $this->validatedData);
    }
}
