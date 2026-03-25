<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Thursday, 12 Mar 2026 15:52:23 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\Comms\DispatchedEmail\StoreDispatchedEmail;
use App\Actions\Comms\EmailDeliveryChannel\SendEmailDeliveryChannel;
use App\Actions\Comms\EmailDeliveryChannel\StoreEmailDeliveryChannel;
use App\Actions\Comms\EmailDeliveryChannel\UpdateEmailDeliveryChannel;
use App\Actions\Comms\Mailshot\Hydrators\MailshotHydrateDispatchedEmails;
use App\Models\Comms\Mailshot;
use App\Models\CRM\Customer;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessSendMailshot
{
    use AsAction;

    public string $jobQueue = 'ses';

    public function handle(?int $mailshotId, array $customerIds): void
    {
        if (!$mailshotId) {
            return;
        }
        $mailshot = Mailshot::find($mailshotId);
        if (!$mailshot) {
            return;
        }


        $outboxId = $mailshot->outbox_id;

        $emailDeliveryChannel = StoreEmailDeliveryChannel::run($mailshot);

        foreach ($customerIds as $customerId) {
            $customer = Customer::find($customerId);
            if (!$customer) {
                continue;
            }

            $recipientExists = $mailshot->recipients()
                ->where('recipient_id', $customer->id)
                ->where('recipient_type', class_basename($customer))
                ->exists();

            if (!$recipientExists) {
                $dispatchedEmail = StoreDispatchedEmail::run(
                    $mailshot,
                    $customer,
                    [
                        'outbox_id' => $outboxId,
                        'email_address' => $customer->email
                    ]
                );

                StoreMailshotRecipient::run(
                    $mailshot,
                    [
                        'dispatched_email_id' => $dispatchedEmail->id,
                        'recipient_type'      => class_basename($customer),
                        'recipient_id'        => $customer->id,
                        'recipient_name'      => $customer->name,
                        'channel'             => $emailDeliveryChannel->id,
                    ]
                );
            }
        }

        UpdateEmailDeliveryChannel::run(
            $emailDeliveryChannel,
            [
                'number_emails' => $mailshot->recipients()->where('channel', $emailDeliveryChannel->id)->count()
            ]
        );

        UpdateMailshotRecipientsStoredAt::run($mailshot);
        MailshotHydrateDispatchedEmails::dispatch($mailshot->id)->delay(now()->addSeconds());

        SendEmailDeliveryChannel::dispatch($emailDeliveryChannel);
    }
}
