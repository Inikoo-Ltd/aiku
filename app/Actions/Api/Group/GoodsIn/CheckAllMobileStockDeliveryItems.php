<?php

/*
 * Author Louis Perez
 * Created on 16-11-2026-11h-33m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsIn;

use App\Actions\GoodsIn\StockDeliveryItem\SetStockDeliveryItemAsChecked;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class CheckAllMobileStockDeliveryItems
{
    use AsController;
    use WithMobileStockDeliveries;

    /**
     * @throws \Throwable
     */
    public function handle(StockDelivery $stockDelivery): int
    {
        return DB::transaction(function () use ($stockDelivery) {
            $uncounted = StockDeliveryItem::where('stock_delivery_id', $stockDelivery->id)
                ->where('state', StockDeliveryItemStateEnum::RECEIVED)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $uncounted->each(fn (StockDeliveryItem $stockDeliveryItem) => SetStockDeliveryItemAsChecked::make()->action($stockDeliveryItem));

            return $uncounted->count();
        });
    }

    /**
     * @throws \Throwable
     */
    public function asController(Warehouse $warehouse, StockDelivery $stockDelivery, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanBookIn($user, $warehouse, $stockDelivery);

        if (!$this->canBeChecked($stockDelivery)) {
            $this->refuseState(__('Items can only be checked while the delivery is being booked in'));
        }

        $checked = $this->handle($stockDelivery);

        return $checked === 0
            ? $this->respondWithStockDelivery($warehouse, $stockDelivery, $user, 'nothing_changed', __('Every item is already checked.'))
            : $this->respondWithStockDelivery($warehouse, $stockDelivery, $user, 'done', trans_choice('{1} :count item checked as expected.|[2,*] :count items checked as expected.', $checked, ['count' => $checked]));
    }
}
