<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-10h-05m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Fulfilment;

use App\Actions\Fulfilment\PalletReturn\PickedPalletReturn;
use App\Actions\Fulfilment\PalletReturn\SetPalletReturnAsPicked;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class FinishMobilePalletReturnPicking
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

        $this->changePalletReturn($palletReturn, function (PalletReturn $palletReturn) use ($user) {
            $this->ensureReturnState($palletReturn, [PalletReturnStateEnum::PICKING]);

            $blocked = $this->finishPickingBlocked($palletReturn, $this->returnLines($palletReturn));
            if ($blocked !== null) {
                $this->refuseState($blocked);
            }

            if ($palletReturn->type === PalletReturnTypeEnum::PALLET) {
                PickedPalletReturn::make()->handle($palletReturn, [], $user);
            } else {
                SetPalletReturnAsPicked::run($palletReturn);
            }
        });

        return $this->respondWithPalletReturn($warehouse, $palletReturn, $user, 'done', __('Return set as picked.'));
    }
}
