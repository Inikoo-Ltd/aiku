<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-09h-40m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Fulfilment;

use App\Models\Fulfilment\PalletDelivery;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class ShowMobilePalletDelivery
{
    use AsController;
    use WithMobilePalletDeliveries;

    public function asController(Warehouse $warehouse, PalletDelivery $palletDelivery, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanViewPalletDelivery($user, $warehouse, $palletDelivery);

        return response()->json(['data' => $this->palletDeliveryDetail($warehouse, $palletDelivery, $user)]);
    }
}
