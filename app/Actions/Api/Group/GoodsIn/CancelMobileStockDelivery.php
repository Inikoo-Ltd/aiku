<?php

/*
 * Author Louis Perez
 * Created on 26-11-2026-09h-30m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsIn;

use App\Actions\GoodsIn\StockDelivery\CancelStockDelivery;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class CancelMobileStockDelivery
{
    use AsController;
    use WithMobileStockDeliveries;

    public function asController(Warehouse $warehouse, StockDelivery $stockDelivery, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanViewStockDelivery($user, $warehouse, $stockDelivery);
        abort_unless(self::canCancelStockDeliveries($user, $stockDelivery), 403, __('Only procurement can cancel a stock delivery.'));

        if ($stockDelivery->isManagedByPartner()) {
            throw ValidationException::withMessages(['state' => __('This delivery is managed by the partner until you receive it')]);
        }

        CancelStockDelivery::make()->action($stockDelivery, $user);

        return $this->respondWithStockDelivery($warehouse, $stockDelivery->refresh(), $user, 'done', __('Stock delivery cancelled.'));
    }
}
