<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 16 Dec 2025 10:09:24 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\OrgAction;
use App\Enums\Ordering\Order\OrderShippingEngineEnum;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateOrderIsShippingTBC extends OrgAction
{
    use AsAction;

    public string $commandSignature = 'order:is-shipping-tbc {--slug=}';

    public function handle(Order $order): Order
    {

        $order->update([
            'is_shipping_tbc' => $this->getIsShippingTBC($order),
        ]);

        return $order;

    }


    public function getIsShippingTBC(Order $order): bool
    {
        if ($order->shipping_engine == OrderShippingEngineEnum::AUTO) {
            $shippingZone = $order->shippingZone;
            if ($shippingZone) {
                return Arr::get($shippingZone->price, 'type') === 'TBC';
            }
        }

        return false;
    }

    public function asCommand(Command $command): int
    {
        $slug = $command->option('slug');

        if ($slug) {
            $order = Order::where('slug', $slug)->first();
            if (!$order) {
                $command->error('Order not found');
                return 1;
            }

            $this->handle($order);
            $command->line("Order {$order->reference} ({$order->slug}) updated: is_shipping_tbc=".(int)$order->fresh()->is_shipping_tbc);

            return 0;
        }

        $count = Order::count();
        $command->withProgressBar(Order::cursor(), function ($model) {
            if ($model instanceof Order) {
                $this->handle($model);
            }
        });
        if ($count > 0) {
            $command->info('');
        }
        $command->info('All orders processed');

        return 0;
    }
}
