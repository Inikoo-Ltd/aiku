<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 09 Sept 2025 19:31:16 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Ordering;

use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithFixedAddressActions;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RepairOrdersAddress
{
    use WithActionUpdate;
    use WithFixedAddressActions;

    public function handle(Order $order): void
    {
        if (!$order->billing_locked) {
            $billingAddress = $order->billingAddress;
            $this->createFixedAddress(
                $order,
                $billingAddress,
                'Ordering',
                'billing',
                'billing_address_id'
            );

            $order->update([
                'billing_locked' => true,
            ]);
        }

        if (!$order->delivery_locked) {
            $deliveryAddress = $order->deliveryAddress;
            $this->createFixedAddress(
                $order,
                $deliveryAddress,
                'Ordering',
                'delivery',
                'delivery_address_id'
            );

            $order->update([
                'delivery_locked' => true,
            ]);
        }
    }


    public string $commandSignature = 'orders:repair_address';

    public function asCommand(Command $command): void
    {
        $count = Order::whereNull('source_id')->count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        Order::whereNull('source_id')->orderBy('date', 'desc')
            ->chunk(1000, function (Collection $models) use ($bar) {
                foreach ($models as $model) {
                    $this->handle($model);
                    $bar->advance();
                }
            });
    }

}
