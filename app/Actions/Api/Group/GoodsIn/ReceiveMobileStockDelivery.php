<?php

/*
 * Author Louis Perez
 * Created on 16-11-2026-11h-33m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsIn;

use App\Actions\GoodsIn\StockDelivery\ReceiveStockDelivery;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class ReceiveMobileStockDelivery
{
    use AsController;
    use WithMobileStockDeliveries;

    public function asController(Warehouse $warehouse, StockDelivery $stockDelivery, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanBookIn($user, $warehouse, $stockDelivery);

        if ($this->isWaitingForPartner($stockDelivery)) {
            $this->refuseState(__('This delivery is managed by the partner until they dispatch it'));
        }

        ReceiveStockDelivery::make()->action($stockDelivery);

        return $this->respondWithStockDelivery($warehouse, $stockDelivery, $user, 'done', __('Marked as received.'));
    }
}
