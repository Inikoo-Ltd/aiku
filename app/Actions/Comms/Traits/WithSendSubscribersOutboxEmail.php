<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 28 Sept 2025 01:05:52 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

/*
 * Author: Junie (JetBrains Autonomous Programmer)
 * Created: Sun, 28 Sep 2025 01:06:00 Local Time
 */

namespace App\Actions\Comms\Traits;

use App\Actions\Comms\DispatchedEmail\StoreDispatchedEmail;
use App\Enums\Comms\DispatchedEmail\DispatchedEmailProviderEnum;
use App\Enums\Comms\Outbox\OutboxBuilderEnum;
use App\Models\Comms\DispatchedEmail;
use App\Models\Comms\Outbox;
use Illuminate\Support\Arr;

trait WithSendSubscribersOutboxEmail
{
    use WithSendBulkEmails;

    /**
     * Send an outbox email to all subscribed users of the given Outbox.
     *
     * This encapsulates the repeated pattern:
     * - iterate outbox->subscribedUsers
     * - resolve recipient (linked user vs subscriber record)
     * - create DispatchedEmail
     * - choose email HTML body depending on builder
     * - send using sendEmailWithMergeTags
     */
    protected function sendOutboxEmailToSubscribers(
        Outbox $outbox,
        array $additionalData = [],
        string $unsubscribeUrl = '',
        ?string $passwordToken = null,
        ?string $invoiceUrl = null
    ): void {
        $subscribedUsers = $outbox->subscribedUsers ?? [];

        foreach ($subscribedUsers as $subscribedUser) {
            $recipient = $subscribedUser->user ?: $subscribedUser;

            /** @var DispatchedEmail $dispatchedEmail */
            $dispatchedEmail = StoreDispatchedEmail::run($outbox->emailOngoingRun, $recipient, [
                'is_test'       => false,
                'outbox_id'     => $outbox->id,
                'email_address' => $recipient->email ?? $recipient->external_email,
                'provider'      => DispatchedEmailProviderEnum::SES,
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
                $unsubscribeUrl,
                $passwordToken,
                $invoiceUrl,
                $additionalData
            );
        }
    }
}
