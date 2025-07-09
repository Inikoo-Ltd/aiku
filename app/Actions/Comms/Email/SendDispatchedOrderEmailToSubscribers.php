<?php

/*
 * author Arya Permana - Kirin
 * created on 08-07-2025-18h-04m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Comms\Email;

use App\Actions\Comms\DispatchedEmail\StoreDispatchedEmail;
use App\Actions\Comms\Traits\WithSendBulkEmails;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Comms\DispatchedEmail\DispatchedEmailProviderEnum;
use App\Enums\Comms\Outbox\OutboxBuilderEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Comms\Outbox;
use App\Models\Comms\Email;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;

class SendDispatchedOrderEmailToSubscribers extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithSendBulkEmails;

    private Email $email;

    public function handle(Order $order): void
    {
        /** @var Outbox $outbox */
        $outbox = $order->shop->outboxes()->where('code', OutboxCodeEnum::DELIVERY_NOTE_DISPATCHED->value)->first();

        $customer = $order->customer;
        $subscribedUsers = $outbox->subscribedUsers ?? [];
        foreach ($subscribedUsers as $subscribedUser) {
            if ($subscribedUser->user) {
                $recipient = $subscribedUser->user;
            } else {
                $recipient = $subscribedUser;
            }
            $dispatchedEmail = StoreDispatchedEmail::run($outbox->emailOngoingRun, $recipient, [
                'is_test'       => false,
                'outbox_id'     => $outbox->id,
                'email_address' => $recipient->email ?? $recipient->external_email,
                'provider'      => DispatchedEmailProviderEnum::SES
            ]);
            $dispatchedEmail->refresh();

            if ($outbox->builder == OutboxBuilderEnum::BLADE) {
                $emailHtmlBody = Arr::get($outbox->emailOngoingRun?->email?->liveSnapshot?->layout, 'blade_template');
            } else {
                $emailHtmlBody = $outbox->emailOngoingRun?->email?->liveSnapshot?->compiled_layout;
            }

            $this->sendEmailWithMergeTags(
                $dispatchedEmail,
                $outbox->emailOngoingRun->sender(),
                $outbox->emailOngoingRun?->email?->subject,
                $emailHtmlBody,
                '',
                additionalData: [
                    'customer_name' => $customer->name,
                    'order_reference' => $order->reference,
                    'date' => $order->created_at->format('F jS, Y'),
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
                ]
            );
        }
    }
}
