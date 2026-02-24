<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Friday, 13 Feb 2026 08:31:59 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Traits;

use App\Actions\Comms\DispatchedEmail\StoreDispatchedEmail;
use App\Enums\Comms\DispatchedEmail\DispatchedEmailProviderEnum;
use App\Enums\Comms\Outbox\OutboxBuilderEnum;
use App\Models\Comms\DispatchedEmail;
use App\Models\Comms\ExternalEmailRecipient;
use App\Models\Comms\Outbox;
use Illuminate\Support\Arr;

trait WithSendExternalOutboxEmail
{
    use WithSendBulkEmails;

    protected function sendOutboxEmailToExternal(
        ExternalEmailRecipient $externalEmailRecipient,
        Outbox $outbox,
        array $additionalData = [],
    ): void {
        $recipient = $externalEmailRecipient;

        /** @var DispatchedEmail $dispatchedEmail */
        $dispatchedEmail = StoreDispatchedEmail::run($outbox->emailOngoingRun, $recipient, [
            'is_test'       => false,
            'outbox_id'     => $outbox->id,
            'email_address' => $recipient->email,
            'provider'      => DispatchedEmailProviderEnum::SES,
        ]);
        $dispatchedEmail->refresh();

        if ($outbox->builder == OutboxBuilderEnum::BLADE) {
            $emailHtmlBody = Arr::get($outbox->emailOngoingRun?->email?->liveSnapshot?->layout, 'blade_template');
        } else {
            $emailHtmlBody = $outbox->emailOngoingRun?->email?->liveSnapshot?->compiled_layout;
        }

        $this->sendEmailWithMergeTags(
            dispatchedEmail: $dispatchedEmail,
            sender: $outbox->emailOngoingRun->sender(),
            subject: $outbox->emailOngoingRun?->email?->subject,
            emailHtmlBody: $emailHtmlBody,
            additionalData: $additionalData,
            senderName: $outbox->emailOngoingRun->senderName(),
        );
    }
}
