<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Wednesday, 27 Feb 2025 15:28:17 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, eka yudinata
 */

namespace App\Actions\CRM\Prospect\Mailshots;

use App\Actions\Comms\DispatchedEmail\StoreDispatchedEmail;
use App\Actions\Comms\EmailDeliveryChannel\SendEmailDeliveryChannel;
use App\Actions\Comms\EmailDeliveryChannel\StoreEmailDeliveryChannel;
use App\Actions\Comms\EmailDeliveryChannel\UpdateEmailDeliveryChannel;
use App\Actions\Comms\Mailshot\Hydrators\MailshotHydrateDispatchedEmails;
use App\Actions\Comms\Mailshot\StoreMailshotRecipient;
use App\Actions\Comms\Mailshot\UpdateMailshotRecipientsStoredAt;
use App\Actions\Comms\Traits\WithDispatchedEmailEncryption;
use App\Models\Comms\Mailshot;
use App\Models\CRM\Prospect;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessSendProspectMailshot
{
    use AsAction;
    use WithDispatchedEmailEncryption;

    public string $jobQueue = 'ses';

    public function tags(): array
    {
        return ['send_prospect_mailshot'];
    }

    public function handle(?int $mailshotId, array $prospectIds): void
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

        foreach ($prospectIds as $prospectId) {
            $prospect = Prospect::find($prospectId);
            if (!$prospect) {
                continue;
            }

            $recipientExists = $mailshot->recipients()
                ->where('recipient_id', $prospect->id)
                ->where('recipient_type', class_basename($prospect))
                ->exists();

            if (!$recipientExists) {
                $dispatchedEmail = StoreDispatchedEmail::run(
                    $mailshot,
                    $prospect,
                    [
                        'outbox_id'     => $outboxId,
                        'email_address' => $prospect->email,
                        'data->additional_data' => [
                            'prospect_name' => $prospect->contact_name ?? $prospect->name ?? " ",
                            'prospect_email' => $prospect->email,
                            'prospect_phone' => $prospect->phone ?? " ",
                            'prospect_company_name' => $prospect->company_name ?? " ",
                        ]
                    ]
                );

                $this->encryptAndStoreDispatchedEmailId($dispatchedEmail);

                StoreMailshotRecipient::run(
                    $mailshot,
                    [
                        'dispatched_email_id' => $dispatchedEmail->id,
                        'recipient_type'      => class_basename($prospect),
                        'recipient_id'        => $prospect->id,
                        'recipient_name'      => $prospect->contact_name ?? $prospect->name ?? " ",
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
        MailshotHydrateDispatchedEmails::dispatch($mailshot->id)->delay(now()->addSeconds(5));

        SendEmailDeliveryChannel::dispatch($emailDeliveryChannel);
    }
}
