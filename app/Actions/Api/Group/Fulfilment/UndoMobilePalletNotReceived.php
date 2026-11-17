<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-09h-40m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Fulfilment;

use App\Actions\Fulfilment\Pallet\UndoNotReceivedPallet;
use App\Models\Fulfilment\Pallet;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class UndoMobilePalletNotReceived
{
    use AsController;
    use WithMobilePalletDeliveries;

    public function asController(Warehouse $warehouse, Pallet $pallet, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user           = $request->user();
        $palletDelivery = $this->ensureCanBookInPallet($user, $warehouse, $pallet);

        $this->underFulfilmentLock('pallet_delivery', $palletDelivery->id, function () use ($palletDelivery, $pallet) {
            $palletDelivery->refresh();
            $pallet->refresh();

            if (!$this->palletNotReceivedCanBeUndone($palletDelivery, $pallet)) {
                $this->refuseState(__('This pallet can not be set back to received now.'));
            }

            UndoNotReceivedPallet::make()->action($pallet);
        });

        return $this->respondWithPalletDelivery($warehouse, $palletDelivery->refresh(), $user, 'done', __('Pallet set back to received.'));
    }
}
