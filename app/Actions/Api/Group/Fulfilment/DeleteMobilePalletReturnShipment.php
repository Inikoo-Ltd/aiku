<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-10h-05m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Fulfilment;

use App\Actions\Dispatching\Shipment\DetachShipmentFromPalletReturn;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Models\Dispatching\Shipment;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class DeleteMobilePalletReturnShipment
{
    use AsController;
    use WithMobilePalletReturns;

    public function asController(Warehouse $warehouse, PalletReturn $palletReturn, Shipment $shipment, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanWorkPalletReturn($user, $warehouse, $palletReturn);
        $this->ensureShipmentOfReturn($palletReturn, $shipment);

        $this->changePalletReturn($palletReturn, function (PalletReturn $palletReturn) use ($shipment) {
            if ($this->shipmentsAreLocked($palletReturn)) {
                $this->refuseState(__('This return is :state, so its shipments can not change.', ['state' => PalletReturnStateEnum::labels()[$palletReturn->state->value]]));
            }

            $this->ensureShipmentOfReturn($palletReturn, $shipment);

            DetachShipmentFromPalletReturn::make()->handle($palletReturn, $shipment);
        });

        return $this->respondWithPalletReturn($warehouse, $palletReturn, $user, 'done', __('Shipment deleted. The carrier was not told: cancel the label with them if it was already booked.'));
    }
}
