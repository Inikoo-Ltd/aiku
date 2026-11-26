<?php

/*
 * Author Louis Perez
 * Created on 26-11-2026-10h-10m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Fulfilment;

use App\Actions\Fulfilment\Pallet\SetPalletBackToStoring;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Models\Fulfilment\Pallet;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class SetMobilePalletBackToStoring
{
    use AsController;
    use WithMobileStoredPallets;

    public function asController(Warehouse $warehouse, Pallet $pallet, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanChangePallet($user, $warehouse, $pallet, self::canEditFulfilmentWarehouse($user, $warehouse), __('You can not change pallet incidents in this warehouse.'));

        $this->underFulfilmentLock('pallet', $pallet->id, function () use ($pallet) {
            $pallet->refresh();
            if ($pallet->status !== PalletStatusEnum::INCIDENT) {
                $this->refuseState(__('Only a pallet with an incident can be set back to storing.'));
            }
            SetPalletBackToStoring::make()->action($pallet, []);
        });

        return $this->respondWithPallet($warehouse, $pallet->refresh(), $user, 'done', __('Pallet set back to storing.'));
    }
}
