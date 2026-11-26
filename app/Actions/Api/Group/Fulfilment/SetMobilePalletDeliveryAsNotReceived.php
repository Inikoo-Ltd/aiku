<?php

/*
 * Author Louis Perez
 * Created on 26-11-2026-10h-10m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Fulfilment;

use App\Actions\Fulfilment\Pallet\SetPalletAsNotReceived;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;
use Throwable;

class SetMobilePalletDeliveryAsNotReceived
{
    use AsController;
    use WithMobilePalletDeliveries;

    /**
     * @throws Throwable
     */
    public function asController(Warehouse $warehouse, PalletDelivery $palletDelivery, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanViewPalletDelivery($user, $warehouse, $palletDelivery);
        abort_unless(static::canEditFulfilmentWarehouse($user, $warehouse), 403, __('You can not book in pallets in this warehouse.'));

        $count = 0;
        $this->underFulfilmentLock('pallet_delivery', $palletDelivery->id, function () use ($palletDelivery, &$count) {
            $palletDelivery->refresh();
            $pallets = $this->palletsThatCanBeSetAsNotReceived($palletDelivery);
            if ($pallets->isEmpty()) {
                $this->refuseState(__('No pallet of this delivery can be set as not received now.'));
            }

            DB::transaction(function () use ($pallets) {
                $pallets->each(fn (Pallet $pallet) => SetPalletAsNotReceived::make()->action($pallet));
            });
            $count = $pallets->count();
        });

        return $this->respondWithPalletDelivery(
            $warehouse,
            $palletDelivery->refresh(),
            $user,
            'done',
            trans_choice(':count pallet set as not received.|:count pallets set as not received.', $count, ['count' => $count])
        );
    }
}
