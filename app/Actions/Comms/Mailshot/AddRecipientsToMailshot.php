<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Monday, 10 Mar 2026 15:27:51 UTC+08:00
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\Comms\DispatchedEmail\StoreDispatchedEmail;
use App\Actions\OrgAction;
use App\Enums\Comms\DispatchedEmail\DispatchedEmailProviderEnum;
use App\Models\Comms\Mailshot;
use App\Models\Comms\Outbox;

//NOTE: This function can be using by mailshot and newsletter
class AddRecipientsToMailshot extends OrgAction
{
    public function handle(Mailshot $mailshot, $recipients, $emailDeliveryChannel, Outbox $outbox): void
    {
        foreach ($recipients as $recipient) {

            $recipientExists = $mailshot->recipients()
                ->where('recipient_id', $recipient->id)
                ->where('recipient_type', class_basename($recipient))
                ->exists();

            if (!$recipientExists && filter_var($recipient->email, FILTER_VALIDATE_EMAIL)) {
                $dispatchedEmail = StoreDispatchedEmail::run(
                    $mailshot,
                    $recipient,
                    [
                        'is_test'       => false,
                        'outbox_id'     => $outbox->id,
                        'email_address' => $recipient->email,
                        'provider'      => DispatchedEmailProviderEnum::SES
                    ]
                );

                StoreMailshotRecipient::run(
                    $mailshot,
                    [
                        'dispatched_email_id' => $dispatchedEmail->id,
                        'recipient_type'      => class_basename($recipient),
                        'recipient_id'        => $recipient->id,
                        'channel'             => $emailDeliveryChannel->id,
                    ]
                );
            }
        }
    }
}
