<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 28-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Dropshipping\CustomerSalesChannel\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Ordering\Order\OrderHandingTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Order\OrderStatusEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Ordering\Order;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class CustomerSalesChannelsHydrateOrders implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(CustomerSalesChannel $customerSalesChannel): string
    {
        return $customerSalesChannel->id . '-hydrate-orders';
    }

    public function handle(CustomerSalesChannel $customerSalesChannel): void
    {

        if ($customerSalesChannel->customer_id && $customerSalesChannel->platform_id) {
            $stats = [
                'number_orders' => Order::where('customer_sales_channel_id', $customerSalesChannel->id)->count()
            ];
            $stats = array_merge(
                $stats,
                $this->getEnumStats(
                    model: 'orders',
                    field: 'state',
                    enum: OrderStateEnum::class,
                    models: Order::class,
                    where: function ($q) use ($customerSalesChannel) {
                        $q->where('customer_sales_channel_id', $customerSalesChannel->id);
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
                    where: function ($q) use ($customerSalesChannel) {
                        $q->where('customer_sales_channel_id', $customerSalesChannel->id);
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
                    where: function ($q) use ($customerSalesChannel) {
                        $q->where('customer_sales_channel_id', $customerSalesChannel->id);
                    }
                )
            );

            $stats['last_order_created_at']    = Order::where('customer_sales_channel_id', $customerSalesChannel->id)
                ->latest('created_at')
                ->first()
                ?->created_at;
            $stats['last_order_submitted_at']  = Order::where('customer_sales_channel_id', $customerSalesChannel->id)
                ->latest('submitted_at')
                ->first()
                ?->submitted_at;
            $stats['last_order_dispatched_at'] = Order::where('customer_sales_channel_id', $customerSalesChannel->id)
                ->latest('dispatched_at')
                ->first()
                ?->dispatched_at;

            $customerSalesChannel->update($stats);
        }
    }
}
