<?php

/*
 * Author Louis Perez
 * Created on 13-11-2026-14h-45m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Dispatching\Shipment\StoreShipment;
use App\Models\Dispatching\DeliveryNote;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class StoreMobileShipment
{
    use AsController;
    use WithMobilePickingGuards;
    use WithMobileShipments;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanShip($deliveryNote, $user);
        $this->ensureActionAvailable($deliveryNote, $user, 'add_shipment');

        $data = Validator::make($request->all(), [
            'shipper_id'    => ['required', 'integer'],
            'tracking'      => ['sometimes', 'nullable', 'string', 'max:1000'],
            'cost'          => ['sometimes', 'nullable', 'numeric'],
            'sender_name'   => ['sometimes', 'nullable', 'string', 'max:255'],
            'override_lock' => ['sometimes', 'nullable', 'boolean'],
        ])->validate();

        $shipper     = $this->storableShipper($deliveryNote, (int) $data['shipper_id'], (bool) ($data['override_lock'] ?? false));
        $stateBefore = $deliveryNote->state;

        $this->passingShipmentRefusals(fn () => StoreShipment::run($deliveryNote, $shipper, Arr::only($data, ['shipper_id', 'tracking', 'cost', 'sender_name'])));

        return $this->respondWith($deliveryNote, $user, 'done', $this->shipmentAddedMessage($deliveryNote, $stateBefore));
    }
}
