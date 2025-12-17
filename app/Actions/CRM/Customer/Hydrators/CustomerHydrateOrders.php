<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Mar 2023 01:58:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Ordering\Order\OrderHandingTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Order\OrderStatusEnum;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CustomerHydrateOrders implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(int $customerId): int
    {
        return $customerId;
    }

    public function handle(int|null $customerId): void
    {
        if ($customerId === null) {
            return;
        }

        $customer = Customer::find($customerId);

        if (!$customer) {
            return;
        }

        $stats = [
            'number_orders' => DB::table('orders')->where('customer_id', $customerId)->count(),
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'orders',
                field: 'state',
                enum: OrderStateEnum::class,
                models: Order::class,
                where: function ($q) use ($customerId) {
                    $q->where('customer_id', $customerId);
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
                where: function ($q) use ($customerId) {
                    $q->where('customer_id', $customerId);
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
                where: function ($q) use ($customerId) {
                    $q->where('customer_id', $customerId);
                }
            )
        );

        $customer->stats()->update($stats);
    }

}
