<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-09h-40m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Fulfilment;

use App\Actions\Fulfilment\Pallet\BookInPallet;
use App\Actions\Inventory\Location\Hydrators\LocationHydratePallets;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Models\Fulfilment\Pallet;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class BookInMobilePallet
{
    use AsController;
    use WithMobilePalletDeliveries;

    public function asController(Warehouse $warehouse, Pallet $pallet, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user           = $request->user();
        $palletDelivery = $this->ensureCanBookInPallet($user, $warehouse, $pallet);
        $data           = Validator::make($request->all(), $this->palletLocationRules())->validate();

        [$status, $message] = $this->underFulfilmentLock('pallet_delivery', $palletDelivery->id, function () use ($warehouse, $palletDelivery, $pallet, $data): array {
            $palletDelivery->refresh();
            $pallet->refresh();

            if (!$this->palletCanBeBookedIn($palletDelivery, $pallet)) {
                $this->refuseState(__('This pallet can not be booked in now.'));
            }

            $location = $this->palletLocation($warehouse, $data);

            if ($pallet->state === PalletStateEnum::BOOKED_IN && $pallet->location_id === $location->id) {
                return ['nothing_changed', __('The pallet is already booked in at :location.', ['location' => $location->code])];
            }

            $oldLocation = $pallet->location_id && $pallet->location_id !== $location->id ? $pallet->location : null;

            BookInPallet::make()->action($pallet, ['location_id' => $location->id]);

            if ($oldLocation) {
                LocationHydratePallets::dispatch($oldLocation);

                return ['done', __('Pallet moved to :location.', ['location' => $location->code])];
            }

            return ['done', __('Pallet booked in at :location.', ['location' => $location->code])];
        });

        return $this->respondWithPalletDelivery($warehouse, $palletDelivery->refresh(), $user, $status, $message);
    }
}
