<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Monday, 16 Mar 2026 11:14:33 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Traits;

use App\Actions\Comms\DispatchedEmail\StoreDispatchedEmail;
use App\Enums\Comms\Outbox\OutboxBuilderEnum;
use App\Models\Comms\DispatchedEmail;
use App\Models\Comms\ChatEmailRecipient;
use App\Models\Comms\Outbox;
use Illuminate\Support\Arr;

trait WithSendChatOutboxEmail
{
    use WithSendBulkEmails;

    protected function sendOutboxEmailToChatRecipient(
        ChatEmailRecipient $chatEmailRecipient,
        Outbox $outbox,
        array $additionalData = [],
    ): DispatchedEmail {
        /** @var DispatchedEmail $dispatchedEmail */
        $dispatchedEmail = StoreDispatchedEmail::run($outbox->emailOngoingRun, $chatEmailRecipient, [
            'outbox_id'     => $outbox->id,
            'email_address' => $chatEmailRecipient->email,
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

        return $dispatchedEmail;
    }
}
