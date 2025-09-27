<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Sept 2025 16:30:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Ordering;

use App\Actions\Ordering\Order\UpdateOrder;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithFixedAddressActions;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RepairOrdersShippingZones
{
    use WithActionUpdate;
    use WithFixedAddressActions;

    public function handle(Order $order): void
    {
        $shippingTransaction = Transaction::where('order_id', $order->id)
            ->where('model_type', 'ShippingZone')
            ->whereNotNull('model_id')
            ->first();

        if ($shippingTransaction) {
            /** @var \App\Models\Billables\ShippingZone $shippingZone */
            $shippingZone = $shippingTransaction->model;
            if ($shippingZone) {
                UpdateOrder::make()->action(
                    order: $order,
                    modelData: [
                        'shipping_zone_schema_id' => $shippingZone->shipping_zone_schema_id,
                        'shipping_zone_id'        => $shippingZone->id,
                    ],
                    hydratorsDelay: 30
                );
            }
        }


    }


    public string $commandSignature = 'orders:shipping_zones';

    public function asCommand(Command $command): void
    {
        $count = Order::count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        Order::orderBy('date', 'desc')
            ->chunk(1000, function (Collection $models) use ($bar) {
                foreach ($models as $model) {
                    $this->handle($model);
                    $bar->advance();
                }
            });
    }

}
