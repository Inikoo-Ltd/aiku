<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-10h-05m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Fulfilment;

use App\Actions\Fulfilment\PalletReturn\PickingPalletReturn;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class StartMobilePalletReturnPicking
{
    use AsController;
    use WithMobilePalletReturns;

    public function asController(Warehouse $warehouse, PalletReturn $palletReturn, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanHandlePalletReturn($user, $warehouse, $palletReturn);

        $this->changePalletReturn($palletReturn, function (PalletReturn $palletReturn) use ($user) {
            $this->ensureReturnState($palletReturn, [PalletReturnStateEnum::CONFIRMED]);

            PickingPalletReturn::make()->action($palletReturn, $user);
        });

        return $this->respondWithPalletReturn($warehouse, $palletReturn, $user, 'done', __('Picking started.'));
    }
}
