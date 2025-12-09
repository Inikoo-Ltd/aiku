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

class RepairOrdersCountryData
{
    use WithActionUpdate;
    use WithFixedAddressActions;

    public function handle(Order $order): void
    {
        $billingAddress = $order->billingAddress;
        if ($billingAddress && $billingAddress->country_id) {
            $order->update([
                'billing_country_id' => $billingAddress->country_id
            ]);
        }

        if ($order->collection_address_id && $order->collectionAddress->country_id) {
            $order->update([
                'delivery_country_id' => $order->collectionAddress->country_id
            ]);
        } elseif ($order->delivery_address_id && $order->deliveryAddress->country_id) {
            $order->update([
                'delivery_country_id' => $order->deliveryAddress->country_id,
            ]);
        }
    }


    public string $commandSignature = 'orders:repair_address';

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
