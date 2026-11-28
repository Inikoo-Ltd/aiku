<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order\UpdateState;

use App\Actions\Dispatching\CrossDispatch\RouteOrderToOtherWarehouse;
use App\Actions\Traits\AsUniqueUntilProcessingAction;
use App\Enums\Ordering\Order\OrderPayStatusEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Order\OrderToBePaidByEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RerouteOrderHeldForOtherWarehouse implements ShouldBeUniqueUntilProcessing
{
    use AsUniqueUntilProcessingAction;

    public string $jobQueue = 'default';

    public function getJobUniqueId(Order $order): string
    {
        return $order->id;
    }

    /**
     * INI-485 7b. An order held for another warehouse is sent to the warehouse again when the stock of one of its
     * products moves, its shop's list shrinks, or at the nightly reconcile: home when home now covers it, otherwise as
     * the shop's settings say, or it stays held. Only a paid or cash-on-delivery order still held is sent, under its lock.
     */
    public function handle(Order $order): void
    {
        try {
            DB::transaction(function () use ($order) {
                $lockedOrder = RouteOrderToOtherWarehouse::lockStillHeld($order);
                if (!$lockedOrder || !($lockedOrder->pay_status === OrderPayStatusEnum::PAID || $lockedOrder->to_be_paid_by === OrderToBePaidByEnum::CASH_ON_DELIVERY)) {
                    return;
                }

                SendOrderToWarehouse::make()->action($lockedOrder, []);
            });
        } catch (ValidationException) {
            return;
        }
    }

    public static function dispatchForShop(Shop $shop): void
    {
        self::heldOrders()->where('shop_id', $shop->id)->get()->each(fn (Order $order) => self::dispatch($order));
    }

    public static function dispatchForAll(): int
    {
        $orders = self::heldOrders()->get();
        $orders->each(fn (Order $order) => self::dispatch($order));

        return $orders->count();
    }

    /**
     * @return Builder<Order>
     */
    private static function heldOrders(): Builder
    {
        return Order::where('state', OrderStateEnum::SUBMITTED)->whereNotNull('data->'.SendOrderToWarehouse::OTHER_WAREHOUSE_HOLD);
    }

    public static function dispatchForProduct(Product $product): void
    {
        self::heldOrders()
            ->where('shop_id', $product->shop_id)
            ->whereHas('transactions', fn ($query) => $query->where('model_type', 'Product')->where('model_id', $product->id))
            ->get()
            ->each(fn (Order $order) => self::dispatch($order));
    }
}
