<?php

/*
 * Author Louis Perez
 * Created on 16-11-2026-11h-33m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsIn;

use App\Actions\GoodsIn\StockDelivery\UnreceiveStockDelivery;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class UnreceiveMobileStockDelivery
{
    use AsController;
    use WithMobileStockDeliveries;

    public function asController(Warehouse $warehouse, StockDelivery $stockDelivery, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanViewStockDelivery($user, $warehouse, $stockDelivery);
        abort_unless(self::canSuperviseStockDeliveries($user, $warehouse), 403, __('Only a goods in supervisor can unmark a delivery as received.'));

        UnreceiveStockDelivery::make()->action($stockDelivery);

        return $this->respondWithStockDelivery($warehouse, $stockDelivery, $user, 'done', __('No longer marked as received.'));
    }
}
