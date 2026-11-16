<?php

/*
 * Author Louis Perez
 * Created on 16-11-2026-11h-33m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsIn;

use App\Models\GoodsIn\ReturnDeliveryNote;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class ShowMobileReturn
{
    use AsController;
    use WithMobileReturns;

    public function asController(Warehouse $warehouse, ReturnDeliveryNote $returnDeliveryNote, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanViewReturn($user, $warehouse, $returnDeliveryNote);

        return response()->json(['data' => $this->returnDetail($warehouse, $returnDeliveryNote, $user)]);
    }
}
