<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 02 Apr 2025 17:03:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class GetShippingAmount
{
    use AsAction;

    public function handle(Order $order, $discount = false): array
    {

        $shippingZone = GetShippingZone::run($order, $discount);
        if (!$shippingZone) {
            return [null,0];
        }


        $pricing = $shippingZone->price;

        return match (Arr::get($pricing, 'type')) {
            'Step Order Items Net Amount' => [
                $shippingZone,
                $this->getShippingAmountFromStepAmount($order->goods_amount, $pricing)
            ],
            'Step Order Estimated Weight' => [
                $shippingZone,
                $this->getShippingAmountFromStepAmount($order->estimated_weight, $pricing)
            ],
            default => [$shippingZone, 0],
        };


    }

    private function getShippingAmountFromStepAmount($amount, array $pricing): float|int
    {
        $shippingAmount = 0;
        $steps = Arr::get($pricing, 'steps', []);

        foreach ($steps as $step) {
            $from = floatval($step['from']);
            $to = $step['to'] === 'INF' ? INF : floatval($step['to']);

            if ($amount >= $from && $amount < $to) {
                $shippingAmount = floatval($step['price']);
                break;
            }
        }

        return $shippingAmount;
    }


    //    public string $commandSignature = 'order:get-shipping-amount {order? : The ID of the order}';
    //
    //    public function commandProcess(Command $command, Order $order): void
    //    {
    //        list($shippingZone, $shippingAmount) = $this->handle($order);
    //
    //        $command->info('Shipping: '.$shippingAmount.' '.$shippingZone?->name);
    //
    //
    //    }
    //
    //    public function asCommand(Command $command): int
    //    {
    //        if ($command->argument('order')) {
    //            $orderId = $command->argument('order');
    //            $order   = Order::findOrFail($orderId);
    //            $this->commandProcess($command, $order);
    //        } else {
    //            $count = 0;
    //            $command->info('Processing all orders in chunks of 1000...');
    //
    //            Order::chunk(1000, function ($orders) use ($command, &$count) {
    //                foreach ($orders as $order) {
    //                    $this->commandProcess($command, $order);
    //                    $count++;
    //                }
    //                $command->info("Processed $count orders so far...");
    //            });
    //
    //            $command->info("Completed processing all $count orders.");
    //        }
    //
    //
    //        return 0;
    //    }

}
