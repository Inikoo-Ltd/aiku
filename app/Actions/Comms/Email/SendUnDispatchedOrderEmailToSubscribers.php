<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Thursday, 12 Mar 2026 11:22:48 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Email;

use App\Actions\Comms\Traits\WithSendBulkEmails;
use App\Actions\Comms\Traits\WithSendSubscribersOutboxEmail;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Comms\Outbox;
use App\Models\Ordering\Order;

class SendUnDispatchedOrderEmailToSubscribers extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithSendBulkEmails;
    use WithSendSubscribersOutboxEmail;

    public function handle(Order $order): void
    {
        if ($order->shop->type === ShopTypeEnum::EXTERNAL) {
            return;
        }

        /** @var Outbox $outbox */
        $outbox = $order->shop->outboxes()->where('code', OutboxCodeEnum::DELIVERY_NOTE_UNDISPATCHED->value)->first();

        $customer = $order->customer;
        $deliveryNote = $order->deliveryNotes->first();

        $this->sendOutboxEmailToSubscribers(
            $outbox,
            additionalData: [
                'customer_name' => $customer->name,
                'order_reference' => $order->reference,
                'undispatched_date' => now()->format('F jS, Y'),
                'order_link' => route('grp.org.shops.show.crm.customers.show.orders.show', [
                    $order->organisation->slug,
                    $order->shop->slug,
                    $order->customer->slug,
                    $order->slug
                ]),
                'customer_link' => route('grp.org.shops.show.crm.customers.show', [
                    $order->organisation->slug,
                    $order->shop->slug,
                    $customer->slug
                ]),
                'invoice_link' => route('grp.org.accounting.invoices.show', [
                    $order->organisation->slug,
                    $order->invoices->first()->slug
                ]),
                'invoice_reference' => $order->invoices->first()->reference,
                'delivery_note_reference' => $deliveryNote->reference,
                'delivery_note_link' => route('grp.org.shops.show.crm.customers.show.orders.show.delivery-note.show', [
                    $order->organisation->slug,
                    $order->shop->slug,
                    $order->customer->slug,
                    $order->slug,
                    $deliveryNote->slug
                ]),
            ]
        );
    }
}
