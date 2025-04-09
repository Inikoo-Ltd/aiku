<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 06-01-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Catalogue\Asset\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Ordering\Order\OrderHandingTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Order\OrderStatusEnum;
use App\Models\Catalogue\Asset;
use App\Models\Ordering\Order;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class AssetHydrateOrdersStats implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public string $jobQueue = 'sales';

    public function getJobUniqueId(Asset $asset): string
    {
        return $asset->id;
    }

    public function handle(Asset $asset): void
    {
        $orders = $asset->transactions()
            ->with('order')
            ->get()
            ->pluck('order')
            ->filter()
            ->unique('id');

        $stats = [
            'number_orders' => $orders->count(),
            'last_order_created_at'    => $orders->max('created_at'),
            'last_order_submitted_at' => $orders->max('submitted_at'),
            'last_order_dispatched_at' => $orders->max('dispatched_at'),
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'orders',
                field: 'state',
                enum: OrderStateEnum::class,
                models: Order::class,
                where: function ($q) use ($orders) {
                    $q->whereIn('id', $orders->pluck('id'));
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
                where: function ($q) use ($orders) {
                    $q->whereIn('id', $orders->pluck('id'));
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
                where: function ($q) use ($orders) {
                    $q->whereIn('id', $orders->pluck('id'));
                }
            )
        );

        $asset->orderingStats()->update($stats);
    }
}
