<?php

/*
 * Author Louis Perez
 * Created on 13-11-2026-14h-45m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Dispatching\Shipment\DeleteShipment;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Shipment;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class DeleteMobileShipment
{
    use AsController;
    use WithMobilePickingGuards;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function asController(DeliveryNote $deliveryNote, Shipment $shipment, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanShip($deliveryNote, $user);

        if ($deliveryNote->areShipmentsLocked()) {
            throw ValidationException::withMessages(['shipment' => __('This delivery note is already dispatched, so its shipment cannot change. Ask a dispatch supervisor to undispatch it first.')]);
        }

        if ($deliveryNote->state === DeliveryNoteStateEnum::CANCELLED) {
            throw ValidationException::withMessages(['shipment' => __('This delivery note is cancelled, so its shipments can not change.')]);
        }

        $this->ensureShipmentOfNote($deliveryNote, $shipment);

        DeleteShipment::run($shipment);

        return $this->respondWith($deliveryNote, $user, 'done', __('Shipment deleted. The carrier was not told: cancel the label with them if it was already booked.'));
    }
}
