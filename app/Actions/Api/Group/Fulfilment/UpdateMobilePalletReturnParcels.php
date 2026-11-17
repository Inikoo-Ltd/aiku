<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-10h-05m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Fulfilment;

use App\Actions\Fulfilment\PalletReturn\UpdatePalletReturn;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class UpdateMobilePalletReturnParcels
{
    use AsController;
    use WithMobilePalletReturns;

    public function asController(Warehouse $warehouse, PalletReturn $palletReturn, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanWorkPalletReturn($user, $warehouse, $palletReturn);

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

        $this->changePalletReturn($palletReturn, function (PalletReturn $palletReturn) use ($parcels) {
            $this->ensureReturnState($palletReturn, [PalletReturnStateEnum::PICKING, PalletReturnStateEnum::PICKED]);

            UpdatePalletReturn::make()->action($palletReturn, ['parcels' => $parcels]);
        });

        return $this->respondWithPalletReturn($warehouse, $palletReturn, $user, 'done', __('Parcels saved.'));
    }
}
