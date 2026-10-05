<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\PreOrder;

use App\Actions\Comms\Email\SendPreOrderUpdateEmail;
use App\Models\Ordering\PreOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Lorisleiva\Actions\Concerns\AsObject;
use OwenIt\Auditing\Events\AuditCustom;

/**
 * When the date slips the customer is told the new one, with their cancellation options. The change
 * is written to the order's history, by whoever or whatever made it.
 */
class UpdatePreOrderDispatchDates
{
    use AsObject;

    public function handle(PreOrder $preOrder, string $from, string $to, ?string $reason = null): PreOrder
    {
        $oldDates = $preOrder->estimated_dispatch_from?->format('d/m/Y').' – '.$preOrder->estimated_dispatch_to?->format('d/m/Y');

        $preOrder->update([
            'estimated_dispatch_from' => Carbon::parse($from)->toDateString(),
            'estimated_dispatch_to'   => Carbon::parse($to)->toDateString(),
        ]);

        $order                 = $preOrder->order;
        $order->auditEvent     = 'pre_order_dispatch_dates';
        $order->isCustomEvent  = true;
        $order->auditCustomOld = ['pre_order_dispatch' => $oldDates];
        $order->auditCustomNew = ['pre_order_dispatch' => $preOrder->estimated_dispatch_from?->format('d/m/Y').' – '.$preOrder->estimated_dispatch_to?->format('d/m/Y')];
        Event::dispatch(new AuditCustom($order));

        SendPreOrderUpdateEmail::dispatch($preOrder, SendPreOrderUpdateEmail::DISPATCH_DATE_CHANGED, ['reason' => $reason])->afterCommit();

        return $preOrder;
    }
}
