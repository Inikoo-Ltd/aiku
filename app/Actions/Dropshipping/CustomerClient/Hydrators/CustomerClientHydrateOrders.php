<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 05-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Dropshipping\CustomerClient\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Ordering\Order\OrderHandingTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Order\OrderStatusEnum;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Ordering\Order;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class CustomerClientHydrateOrders implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(CustomerClient $customerClient): string
    {
        return $customerClient->id;
    }

    public function handle(CustomerClient $customerClient): void
    {
        $stats = [
            'number_orders' => $customerClient->orders->count(),
            'orders_amount' => $customerClient->orders->sum('total_amount'),
            'orders_amount_state_dispatched' => $customerClient->orders->where('state', OrderStateEnum::DISPATCHED->value)->sum('total_amount'),
            'number_current_orders' => $customerClient->orders->whereIn('state', [
                OrderStateEnum::SUBMITTED,
                OrderStateEnum::IN_WAREHOUSE,
                OrderStateEnum::HANDLING,
                OrderStateEnum::HANDLING_BLOCKED,
                OrderStateEnum::PACKED,
                OrderStateEnum::FINALISED,
            ])->count(),
            'current_orders_amount' => $customerClient->orders->whereIn('state', [
                OrderStateEnum::SUBMITTED,
                OrderStateEnum::IN_WAREHOUSE,
                OrderStateEnum::HANDLING,
                OrderStateEnum::HANDLING_BLOCKED,
                OrderStateEnum::PACKED,
                OrderStateEnum::FINALISED,
            ])->sum('total_amount'),
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'orders',
                field: 'state',
                enum: OrderStateEnum::class,
                models: Order::class,
                where: function ($q) use ($customerClient) {
                    $q->where('customer_client_id', $customerClient->id);
                }
            )
        );

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'orders',
                field: 'status',
                enum: OrderStatusEnum::class,
                models: Order::class,
                where: function ($q) use ($customerClient) {
                    $q->where('customer_client_id', $customerClient->id);
                }
            )
        );

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'orders',
                field: 'handing_type',
                enum: OrderHandingTypeEnum::class,
                models: Order::class,
                where: function ($q) use ($customerClient) {
                    $q->where('customer_client_id', $customerClient->id);
                }
            )
        );

        $customerClient->stats()->update($stats);
    }
}
