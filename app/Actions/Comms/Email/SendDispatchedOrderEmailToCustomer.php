<?php
/*
 * author Arya Permana - Kirin
 * created on 08-07-2025-18h-02m
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
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\Outbox\OutboxTypeEnum;
use App\Models\Comms\DispatchedEmail;
use App\Models\Comms\Email;
use App\Models\Comms\Outbox;
use App\Models\Ordering\Order;

class SendDispatchedOrderEmailToCustomer extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithSendBulkEmails;

    private Email $email;

    public function handle(Order $order): ?DispatchedEmail
    {
        $customer = $order->customer;
        $recipient       = $customer;
        if (!$recipient->email) {
            return null;
        }

        /** @var Outbox $outbox */
        $outbox = $customer->shop->outboxes()->where('code', OutboxCodeEnum::DELIVERY_CONFIRMATION->value)->first();
        $outboxDispatch = $customer->shop->outboxes()->where('type', OutboxTypeEnum::CUSTOMER_NOTIFICATION)->first();


        $dispatchedEmail = StoreDispatchedEmail::run($outbox->emailOngoingRun, $recipient, [
            'is_test'       => false,
            'outbox_id'     => $outboxDispatch->id,
            'email_address' => $recipient->email,
            'provider'      => DispatchedEmailProviderEnum::SES
        ]);
        $dispatchedEmail->refresh();

        $emailHtmlBody = $outbox->emailOngoingRun->email->liveSnapshot->compiled_layout;
        if (!$emailHtmlBody) {
            return null;
        }

        $baseUrl = 'https://ds.test';
        if (app()->isProduction()) {
            $baseUrl = 'https://'.$order->shop->website->domain;
        }

        $orderUrl = $baseUrl.'/app/dropshipping/channels/'.$order->customerSalesChannel->slug.'/orders/'.$order->slug;
        $invoiceUrl = $baseUrl.'/app/dropshipping/invoices/'.$order->invoices->first()->slug;
        return $this->sendEmailWithMergeTags(
                $dispatchedEmail,
                $outbox->emailOngoingRun->sender(),
                $outbox->emailOngoingRun?->email?->subject,
                $emailHtmlBody,
                '',
                additionalData: [
                    'customer_name' => $customer->name,
                    'order_reference' => $order->reference,
                    'date' => $order->created_at->format('F jS, Y'),
                    'order_link' => $orderUrl,
                    'invoice_link' => $invoiceUrl,
                ]
            );
    }
}
