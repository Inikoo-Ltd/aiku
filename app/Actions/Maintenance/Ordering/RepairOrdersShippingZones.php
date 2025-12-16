<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Sept 2025 16:30:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Ordering;

use App\Actions\Ordering\Order\UpdateOrderIsShippingTBC;
use App\Enums\Ordering\Order\OrderShippingEngineEnum;
use App\Models\Billables\ShippingZone;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairOrdersShippingZones
{
    use AsAction;

    public function handle(Order $order): void
    {
        $shippingTransaction = Transaction::where('order_id', $order->id)
            ->where('model_type', 'ShippingZone')
            ->whereNotNull('model_id')
            ->first();


        if ($order->shipping_engine == OrderShippingEngineEnum::TO_BE_CONFIRMED || $order->shipping_engine == OrderShippingEngineEnum::TO_BE_CONFIRMED_SET) {
            $order->update([
                'shipping_engine' => OrderShippingEngineEnum::AUTO
            ]);
        }

        if ($shippingTransaction) {
            /** @var ShippingZone $shippingZone */
            $shippingZone = $shippingTransaction->model;
            if ($shippingZone) {
                $order->update(
                    [
                        'shipping_zone_schema_id' => $shippingZone->shipping_zone_schema_id,
                        'shipping_zone_id'        => $shippingZone->id,
                    ]
                );

                $order = UpdateOrderIsShippingTBC::run($order);
                if ($order->is_shipping_tbc && $order->shipping_amount > 0) {
                    $order->update([
                        'shipping_tbc_amount' => $order->shipping_amount
                    ]);
                }
            }
        } else {
            $order->update(
                [
                    'shipping_zone_schema_id' => null,
                    'shipping_zone_id'        => null
                ]
            );
            UpdateOrderIsShippingTBC::run($order);
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
