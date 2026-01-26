<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Sept 2025 23:50:39 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Traits;

use App\Actions\Comms\DispatchedEmail\StoreDispatchedEmail;
use App\Enums\Comms\DispatchedEmail\DispatchedEmailProviderEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Comms\DispatchedEmail;
use App\Models\Comms\Outbox;
use App\Models\CRM\Customer;
use App\Models\Comms\EmailBulkRun;
use App\Models\Comms\EmailOngoingRun;
use App\Models\Comms\Mailshot;
use Sentry;

trait WithSendCustomerOutboxEmail
{
    use WithSendBulkEmails;

    /**
     * Common helper to send an outbox email to a Customer using a specific outbox code.
     */
    protected function sendCustomerOutboxEmail(
        Customer $customer,
        OutboxCodeEnum $code,
        array $additionalData = [],
        string $unsubscribeUrl = '',
        ?string $passwordToken = null,
        ?string $invoiceUrl = null,
        EmailOngoingRun|EmailBulkRun|Mailshot|null $parent = null
    ): DispatchedEmail|null {
        /** @var Outbox $outbox */
        $outbox = $customer->shop->outboxes()->where('code', $code->value)->first();

        $emailHtmlBody = $outbox->emailOngoingRun->email->liveSnapshot->compiled_layout;
        if($emailHtmlBody === null) {
            Sentry::captureMessage('Email live snapshot not found for outbox code: ' . $code->value.' outobox id: '.$outbox->id.'');
            return null;
        }



        $recipient = $customer;
        $dispatchedEmail = StoreDispatchedEmail::run($parent ?? $outbox->emailOngoingRun, $recipient, [
            'is_test'       => false,
            'outbox_id'     => $outbox->id,
            'email_address' => $recipient->email,
            'provider'      => DispatchedEmailProviderEnum::SES
        ]);
        $dispatchedEmail->refresh();


        return $this->sendEmailWithMergeTags(
            $dispatchedEmail,
            $outbox->emailOngoingRun->sender(),
            $outbox->emailOngoingRun?->email?->subject,
            $emailHtmlBody,
            $unsubscribeUrl,
            $passwordToken,
            $invoiceUrl,
            $additionalData,
            senderName: $outbox->emailOngoingRun->senderName()
        );
    }
}
