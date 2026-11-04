<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 04 Nov 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping;

use App\Actions\Ordering\Order\UpdateOrder;
use App\Actions\Tasks\GetAikuAssistant;
use App\Actions\Tasks\StoreStaffTask;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Ordering\Order;
use Illuminate\Support\Str;
use Throwable;

/**
 * A dispatched order whose shipping update the customer's sales channel refused on every attempt:
 * the order says so, and customer services get a task to tell the customer.
 */
trait WithPlatformFulfilmentFailure
{
    public function jobFailed(Throwable $e, Order|PalletReturn $order): void
    {
        if (!$order instanceof Order) {
            return;
        }

        $order->refresh();
        $platform = $order->platform?->name ?? __('the sales channel');

        $note = __('This order was dispatched but we could not mark it as shipped on :platform. Please mark it as shipped there.', ['platform' => $platform], $order->shop->language?->code);

        UpdateOrder::run($order, [
            'shipping_notes' => trim($order->shipping_notes."\n\n".$note),
        ]);

        StoreStaffTask::run(GetAikuAssistant::run($order->group_id), [
            'subject'     => __('Tell the customer to mark :order as shipped on :platform', ['order' => $order->reference, 'platform' => $platform]),
            'description' => __(':platform refused the shipping update of dispatched order :order (:customer): :error. Ask the customer to mark it as shipped in their :platform store with the tracking on the order.', [
                'platform' => $platform,
                'order'    => $order->reference,
                'customer' => $order->customer?->name,
                'error'    => Str::limit($e->getMessage(), 300),
            ]),
            'department'  => 'customer-services',
            'model_type'  => 'Order',
            'model_id'    => $order->id,
            'data'        => ['organisation_id' => $order->organisation_id],
        ]);
    }
}
