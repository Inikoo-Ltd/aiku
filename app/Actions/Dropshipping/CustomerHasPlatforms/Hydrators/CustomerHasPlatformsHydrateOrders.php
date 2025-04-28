<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 28-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Dropshipping\CustomerHasPlatforms\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Ordering\Order\OrderHandingTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Order\OrderStatusEnum;
use App\Models\CRM\CustomerHasPlatform;
use App\Models\Ordering\Order;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class CustomerHasPlatformsHydrateOrders implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(CustomerHasPlatform $customerHasPlatform): string
    {
        return $customerHasPlatform->id;
    }

    public function handle(CustomerHasPlatform $customerHasPlatform): void
    {

        $stats = [];

        if ($customerHasPlatform->customer_id && $customerHasPlatform->platform_id) {
            $stats = array_merge(
                $stats,
                $this->getEnumStats(
                    model: 'orders',
                    field: 'state',
                    enum: OrderStateEnum::class,
                    models: Order::class,
                    where: function ($q) use ($customerHasPlatform) {
                        $q->where('customer_id', $customerHasPlatform->customer_id)
                        ->where('platform_id', $customerHasPlatform->platform_id);
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
                    where: function ($q) use ($customerHasPlatform) {
                        $q->where('customer_id', $customerHasPlatform->customer_id)
                        ->where('platform_id', $customerHasPlatform->platform_id);
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
                    where: function ($q) use ($customerHasPlatform) {
                        $q->where('customer_id', $customerHasPlatform->customer_id)
                        ->where('platform_id', $customerHasPlatform->platform_id);
                    }
                )
            );

            $stats['last_order_created_at'] = Order::where('customer_id', $customerHasPlatform->customer_id)
                ->where('platform_id', $customerHasPlatform->platform_id)
                ->latest('created_at')
                ->first()
                ?->created_at;
            $stats['last_order_submitted_at'] = Order::where('customer_id', $customerHasPlatform->customer_id)
                ->where('platform_id', $customerHasPlatform->platform_id)
                ->latest('submitted_at')
                ->first()
                ?->submitted_at;
            $stats['last_order_dispatched_at'] = Order::where('customer_id', $customerHasPlatform->customer_id)
                ->where('platform_id', $customerHasPlatform->platform_id)
                ->latest('dispatched_at')
                ->first()
                ?->dispatched_at;

        }

        $customerHasPlatform->update($stats);
    }

    public string $commandSignature = 'hydrate:customer_has_platforms_orders {customer_has_platform}';

    public function asCommand($command)
    {
        $customerHasPlatformId = $command->argument('customer_has_platform');
        $customerHasPlatform = CustomerHasPlatform::find($customerHasPlatformId);
        if (!$customerHasPlatform) {
            $command->error("CustomerHasPlatform with ID {$customerHasPlatformId} not found.");
            return;
        }
        $this->handle($customerHasPlatform);
    }

}
