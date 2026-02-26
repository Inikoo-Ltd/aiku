<?php

/*
 * author Arya Permana - Kirin
 * created on 25-06-2025-12h-32m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\Tiktok\Order;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\TiktokUser;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Sentry;

class CancelFulfillOrderTiktok extends OrgAction
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

            /** @var TiktokUser $tiktokUser */
            $tiktokUser = $order->customerSalesChannel->user;

            $tiktokUser->cancelFulfilOrder($fulfillOrderId);
        } catch (\Exception $e) {
            Sentry::captureException($e);
        }
    }

    public $commandSignature = 'tiktok:cancel_fulfill_order {order}';

    public function asCommand(Command $command): void
    {
        $order = Order::where('slug', $command->argument('order'))->firstOrFail();
        $this->handle($order);
    }
}
