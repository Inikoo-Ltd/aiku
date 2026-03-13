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
use App\Enums\Comms\DispatchedEmail\DispatchedEmailProviderEnum;
use App\Models\Comms\Mailshot;
use App\Models\CRM\Customer;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessSendMailshot
{
    use AsAction;

    public string $jobQueue = 'ses';

    public function handle($mailshotId, $customerIds, $outboxId)
    {
        $mailshot = Mailshot::find($mailshotId);
        $emailDeliveryChannel = StoreEmailDeliveryChannel::run($mailshot);

        foreach ($customerIds as $customerId) {

            $customer = Customer::find($customerId);

            $recipientExists = $mailshot->recipients()
                ->where('recipient_id', $customer->id)
                ->where('recipient_type', class_basename($customer))
                ->exists();

            if (!$recipientExists && filter_var($customer->email, FILTER_VALIDATE_EMAIL)) {
                $dispatchedEmail = StoreDispatchedEmail::run(
                    $mailshot,
                    $customer,
                    [
                        'is_test'       => false,
                        'outbox_id'     => $outboxId,
                        'email_address' => $customer->email,
                        'provider'      => DispatchedEmailProviderEnum::SES
                    ]
                );

                StoreMailshotRecipient::run(
                    $mailshot,
                    [
                        'dispatched_email_id' => $dispatchedEmail->id,
                        'recipient_type'      => class_basename($customer),
                        'recipient_id'        => $customer->id,
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

        // TODO: think about this one later
        // UpdateMailshot::run(
        //     $mailshot,
        //     [
        //         'recipients_stored_at' => now()
        //     ]
        // );

        SendEmailDeliveryChannel::dispatch($emailDeliveryChannel);
    }
}
