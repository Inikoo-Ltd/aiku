<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-10h-05m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Fulfilment;

use App\Actions\Dispatching\Shipment\StoreShipment;
use App\Models\Dispatching\Shipper;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class StoreMobilePalletReturnShipment
{
    use AsController;
    use WithMobilePalletReturns;

    /**
     * @throws ValidationException
     */
    public function asController(Warehouse $warehouse, PalletReturn $palletReturn, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanWorkPalletReturn($user, $warehouse, $palletReturn);

        $data = Validator::make($request->all(), [
            'shipper_id' => ['required', 'integer', 'min:1', 'max:'.self::MAX_ID],
            'tracking'   => ['sometimes', 'nullable', 'string', 'max:1000'],
        ])->validate();

        $this->changePalletReturn($palletReturn, function (PalletReturn $palletReturn) use ($data) {
            $this->ensureShipmentCanBeAdded($palletReturn);

            $shipper = Shipper::where('organisation_id', $palletReturn->organisation_id)->where('status', true)->find($data['shipper_id']);
            if (!$shipper || $shipper->api_shipper === IndexMobilePalletReturnShippers::SHIPPER_TAKING_DELIVERY_NOTES_ONLY) {
                throw ValidationException::withMessages(['shipper_id' => __('Choose an active shipper of this organisation that can ship pallet returns.')]);
            }

            if ($shipper->api_shipper && !$this->parcelRows($palletReturn)) {
                throw ValidationException::withMessages(['parcels' => __('Add the parcels first.')]);
            }

            try {
                StoreShipment::run($palletReturn, $shipper, Arr::only($data, ['shipper_id', 'tracking']));
            } catch (ValidationException $exception) {
                throw ValidationException::withMessages(['shipper_id' => collect($exception->errors())->flatten()->filter()->values()->all()]);
            }
        });

        return $this->respondWithPalletReturn($warehouse, $palletReturn, $user, 'done', __('Shipment added.'));
    }
}
