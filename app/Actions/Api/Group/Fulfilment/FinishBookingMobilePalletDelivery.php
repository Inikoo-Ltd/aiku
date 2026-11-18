<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-09h-40m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Fulfilment;

use App\Actions\Fulfilment\PalletDelivery\SetPalletDeliveryAsBookedIn;
use App\Enums\Fulfilment\PalletDelivery\PalletDeliveryStateEnum;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class FinishBookingMobilePalletDelivery
{
    use AsController;
    use WithMobilePalletDeliveries;

    public function asController(Warehouse $warehouse, PalletDelivery $palletDelivery, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanEditPalletDeliveryAsShop($user, $warehouse, $palletDelivery);

        $this->underFulfilmentLock('pallet_delivery', $palletDelivery->id, function () use ($palletDelivery) {
            $palletDelivery->refresh();

            if ($palletDelivery->state !== PalletDeliveryStateEnum::BOOKING_IN) {
                $this->refusePalletDeliveryState($palletDelivery);
            }

            if ($blockedBy = $this->finishBookingBlockedReason($palletDelivery->pallets()->get())) {
                $this->refuseState($blockedBy);
            }

            SetPalletDeliveryAsBookedIn::make()->action($palletDelivery);
        });

        return $this->respondWithPalletDelivery($warehouse, $palletDelivery->refresh(), $user, 'done', __('Delivery booked in.'));
    }
}
