<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-09h-40m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Fulfilment;

use App\Actions\Fulfilment\PalletDelivery\ReceivePalletDelivery;
use App\Enums\Fulfilment\PalletDelivery\PalletDeliveryStateEnum;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class ReceiveMobilePalletDelivery
{
    use AsController;
    use WithMobilePalletDeliveries;

    public function asController(Warehouse $warehouse, PalletDelivery $palletDelivery, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanEditPalletDeliveryAsShop($user, $warehouse, $palletDelivery);

        $this->underFulfilmentLock('pallet_delivery', $palletDelivery->id, function () use ($palletDelivery, $user) {
            $palletDelivery->refresh();

            if ($palletDelivery->state !== PalletDeliveryStateEnum::CONFIRMED) {
                $this->refusePalletDeliveryState($palletDelivery);
            }

            if (!$palletDelivery->fulfilmentCustomer->rentalAgreement) {
                $this->refuseState(__('This customer has no rental agreement; set it up on the web first.'));
            }

            ReceivePalletDelivery::make()->action($palletDelivery, $user->id);
        });

        return $this->respondWithPalletDelivery($warehouse, $palletDelivery->refresh(), $user, 'done', __('Delivery marked as received.'));
    }
}
