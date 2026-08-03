<?php

/*
 * author Arya Permana - Kirin
 * created on 25-06-2025-12h-32m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\Allegro\Order;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\AllegroUser;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Sentry;

class CancelFulfillOrderAllegro extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(Order $order): void
    {
        try {
            $fulfillOrderId = $order->platform_order_id;

            if (! $order->customerSalesChannel->platform_status) {
                return;
            }

            /** @var AllegroUser $allegroUser */
            $allegroUser = $order->customerSalesChannel->user;

            $allegroUser->setOrderCancelled($fulfillOrderId);
        } catch (\Exception $e) {
            Sentry::captureException($e);
        }
    }

    public $commandSignature = 'allegro:cancel_fulfill_order {order}';

    public function asCommand(Command $command): void
    {
        $order = Order::where('slug', $command->argument('order'))->firstOrFail();
        $this->handle($order);
    }
}
