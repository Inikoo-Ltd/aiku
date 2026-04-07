<?php

/*
 * author Arya Permana - Kirin
 * created on 08-07-2025-18h-04m
 * github: https://github.com/KirinZero0
 * copyright 2025
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

class SendDispatchedOrderEmailToSubscribers extends OrgAction
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
        $outbox = $order->shop->outboxes()->where('code', OutboxCodeEnum::DELIVERY_NOTE_DISPATCHED->value)->first();

        $customer = $order->customer;
        $deliveryNote = $order->deliveryNotes->first();
        $invoice = $order->invoices?->first() ?? null;
        $invoiceLink = '#';
        $invoiceReference = '';

        if ($invoice) {
            $invoiceLink = route('grp.org.accounting.invoices.show', [
                $order->organisation->slug,
                $invoice->slug
            ]);

            $invoiceReference = $invoice->reference;
        }

        $this->sendOutboxEmailToSubscribers(
            $outbox,
            additionalData: [
                'customer_name' => $customer->name,
                'order_reference' => $order->reference,
                'dispatched_date' => $order->dispatched_at->format('F jS, Y'),
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
                'invoice_link' => $invoiceLink,
                'invoice_reference' => $invoiceReference,
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
