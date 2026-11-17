<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-09h-40m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Fulfilment;

use App\Actions\Fulfilment\PalletDelivery\StartBookingPalletDelivery;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\PalletDelivery\PalletDeliveryStateEnum;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class StartBookingMobilePalletDelivery
{
    use AsController;
    use WithMobilePalletDeliveries;

    public function asController(Warehouse $warehouse, PalletDelivery $palletDelivery, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanEditPalletDeliveryAsShop($user, $warehouse, $palletDelivery);

        $numberReset = $this->underFulfilmentLock('pallet_delivery', $palletDelivery->id, function () use ($palletDelivery): int {
            $palletDelivery->refresh();

            if ($palletDelivery->state !== PalletDeliveryStateEnum::RECEIVED) {
                $this->refusePalletDeliveryState($palletDelivery);
            }

            $numberNotReceived = $palletDelivery->pallets()->where('state', PalletStateEnum::NOT_RECEIVED)->count();
            StartBookingPalletDelivery::make()->action($palletDelivery);

            return $numberNotReceived;
        });

        $message = __('Booking in started.');
        if ($numberReset > 0) {
            $message .= ' '.trans_choice(':count pallet marked not received was reset to booking in.|:count pallets marked not received were reset to booking in.', $numberReset);
        }

        return $this->respondWithPalletDelivery($warehouse, $palletDelivery->refresh(), $user, 'done', $message);
    }
}
