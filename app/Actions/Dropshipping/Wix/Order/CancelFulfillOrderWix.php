<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Wix\Order;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\WixUser;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Sentry;

class CancelFulfillOrderWix extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(Order $order): void
    {
        try {
            if (!$order->customerSalesChannel?->platform_status) {
                return;
            }

            /** @var WixUser $wixUser */
            $wixUser = $order->customerSalesChannel->user;

            if (!$wixUser instanceof WixUser || !$order->platform_order_id) {
                return;
            }

            $wixUser->cancelOrder($order->platform_order_id);
        } catch (\Exception $e) {
            Sentry::captureException($e);
        }
    }

    public string $commandSignature = 'wix:cancel_fulfill_order {order}';

    public function asCommand(Command $command): void
    {
        $order = Order::where('slug', $command->argument('order'))->firstOrFail();

        $this->handle($order);
    }
}
