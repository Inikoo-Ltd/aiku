<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-10h-05m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Fulfilment;

use App\Actions\Fulfilment\PalletReturn\DispatchPalletReturn;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class DispatchMobilePalletReturn
{
    use AsController;
    use WithMobilePalletReturns;

    /**
     * @throws \Throwable
     */
    public function asController(Warehouse $warehouse, PalletReturn $palletReturn, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanHandlePalletReturn($user, $warehouse, $palletReturn);

        $isCollection = $this->changePalletReturn($palletReturn, function (PalletReturn $palletReturn) {
            $this->ensureReturnState($palletReturn, [PalletReturnStateEnum::PICKED]);

            $blocked = $this->dispatchBlocked($palletReturn);
            if ($blocked !== null) {
                $this->refuseState($blocked);
            }

            DispatchPalletReturn::make()->action($palletReturn);

            return (bool) $palletReturn->is_collection;
        });

        return $this->respondWithPalletReturn($warehouse, $palletReturn, $user, 'done', $isCollection ? __('Return set as collected.') : __('Return dispatched.'));
    }
}
