<?php

/*
 * Author Louis Perez
 * Created on 13-11-2026-14h-45m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNote;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class UpdateMobileDeliveryNoteParcels
{
    use AsController;
    use WithMobilePickingGuards;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanWork($deliveryNote, $user);
        $this->ensureState($deliveryNote, [DeliveryNoteStateEnum::HANDLING, DeliveryNoteStateEnum::PACKING, DeliveryNoteStateEnum::PACKED]);

        if ($deliveryNote->shipments()->exists()) {
            throw ValidationException::withMessages(['parcels' => __('The parcels can not change once a shipment exists. Delete the shipment first.')]);
        }

        $data = Validator::make($request->all(), [
            'parcels'                   => ['required', 'array', 'min:1', 'max:50'],
            'parcels.*.weight_kg'       => ['required', 'numeric', 'gt:0', 'max:1000'],
            'parcels.*.dimensions_cm'   => ['required', 'array', 'size:3'],
            'parcels.*.dimensions_cm.*' => ['required', 'numeric', 'gt:0', 'max:1000'],
            'parcels.*.boxes'           => ['sometimes', 'nullable', 'integer', 'min:1'],
        ])->validate();

        $parcels = collect($data['parcels'])
            ->map(fn (array $parcel) => array_filter([
                'weight'     => (float) $parcel['weight_kg'],
                'dimensions' => array_map(fn ($dimension) => (float) $dimension, array_values($parcel['dimensions_cm'])),
                'boxes'      => isset($parcel['boxes']) ? (int) $parcel['boxes'] : null,
            ], fn ($value) => $value !== null))
            ->values()
            ->all();

        UpdateDeliveryNote::make()->action($deliveryNote, ['parcels' => $parcels]);

        return $this->respondWith($deliveryNote, $user, 'done', __('Parcels saved.'));
    }
}
