<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-09h-40m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Fulfilment;

use App\Actions\Fulfilment\Pallet\UpdatePalletLocation;
use App\Actions\Inventory\Location\Hydrators\LocationHydratePallets;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Models\Fulfilment\Pallet;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class MoveMobilePallet
{
    use AsController;
    use WithMobileStoredPallets;

    public function asController(Warehouse $warehouse, Pallet $pallet, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanChangePallet($user, $warehouse, $pallet, self::canMovePallets($user, $warehouse), __('You can not move pallets in this warehouse.'));
        $data = Validator::make($request->all(), $this->palletLocationRules())->validate();

        [$status, $message] = $this->underFulfilmentLock('pallet', $pallet->id, function () use ($warehouse, $pallet, $data): array {
            $pallet->refresh();

            if ($pallet->status !== PalletStatusEnum::STORING) {
                $this->refuseState(__('Only a stored pallet can be moved.'));
            }

            $location = $this->palletLocation($warehouse, $data);

            if ($pallet->location_id === $location->id) {
                return ['nothing_changed', __('The pallet is already in :location.', ['location' => $location->code])];
            }

            $oldLocation = $pallet->location;

            UpdatePalletLocation::make()->handle($location, $pallet);

            if ($oldLocation) {
                LocationHydratePallets::dispatch($oldLocation);
            }
            LocationHydratePallets::dispatch($location);

            return ['done', __('Pallet moved to :location.', ['location' => $location->code])];
        });

        return $this->respondWithPallet($warehouse, $pallet, $user, $status, $message);
    }
}
