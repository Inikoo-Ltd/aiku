<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Wednesday, 4 Mar 2026 15:42:13 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Traits;

use App\Actions\Comms\DispatchedEmail\StoreDispatchedEmail;
use App\Enums\Comms\DispatchedEmail\DispatchedEmailProviderEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Comms\DispatchedEmail;
use App\Models\Comms\Outbox;
use App\Models\CRM\WebUser;
use Sentry;

trait WithSendWebUserOutboxEmail
{
    use WithSendBulkEmails;

    /**
     * Common helper to send an outbox email to a WebUser using a specific outbox code.
     */
    protected function sendWebUserOutboxEmail(
        WebUser $webUser,
        OutboxCodeEnum $code,
        array $additionalData = [],
        string $unsubscribeUrl = '',
    ): DispatchedEmail|null {
        /** @var Outbox $outbox */
        $outbox = $webUser->website->shop->outboxes()->where('code', $code->value)->first();

        $emailHtmlBody = $outbox->emailOngoingRun->email->liveSnapshot->compiled_layout;
        if ($emailHtmlBody === null) {
            Sentry::captureMessage('Email live snapshot not found for outbox code: ' . $code->value . ' outbox id: ' . $outbox->id . '');
            return null;
        }



        $recipient = $webUser;
        $dispatchedEmail = StoreDispatchedEmail::run($outbox->emailOngoingRun, $recipient, [
            'is_test'       => false,
            'outbox_id'     => $outbox->id,
            'email_address' => $recipient->email,
            'provider'      => DispatchedEmailProviderEnum::SES
        ]);
        $dispatchedEmail->refresh();

        return $this->sendEmailWithMergeTags(
            dispatchedEmail: $dispatchedEmail,
            sender: $outbox->emailOngoingRun->sender(),
            subject: $outbox->emailOngoingRun?->email?->subject,
            emailHtmlBody: $emailHtmlBody,
            unsubscribeUrl: $unsubscribeUrl,
            additionalData: $additionalData,
            senderName: $outbox->emailOngoingRun->senderName()
        );
    }
}
