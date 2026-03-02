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
use App\Actions\Comms\Mailshot\DeleteMailshotSecondWave;
use App\Actions\Comms\Mailshot\Hydrators\MailshotHydrateDispatchedEmails;
use App\Actions\Comms\Mailshot\StoreMailshotRecipient;
use App\Actions\Comms\Mailshot\UpdateMailshot;
use App\Enums\Comms\DispatchedEmail\DispatchedEmailProviderEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Comms\Mailshot;
use App\Models\CRM\Prospect;
use Exception;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessSendProspectMailshot
{
    use AsAction;

    public string $jobQueue = 'default-long';

    public function tags(): array
    {
        return ['send_mailshot'];
    }

    public function handle(Mailshot $mailshot): void
    {

        // NOTE: Ensure no second wave exists when the parent mailshot has second wave disabled
        // if ($mailshot->secondWave()->exists() && !$mailshot->is_second_wave_enabled) {
        //     DeleteMailshotSecondWave::run($mailshot->secondWave);
        // }

        $queryBuilder = Prospect::where('shop_id', $mailshot->shop_id)
            ->whereNull('customer_id')
            ->where('can_contact_by_email', true)
            ->where('is_valid_email', true)
            ->whereNotNull('email');

        // Process recipients in chunks of 250
        $queryBuilder->chunk(250, function ($recipients) use ($mailshot) {

            $emailDeliveryChannel = StoreEmailDeliveryChannel::run($mailshot);

            foreach ($recipients as $recipient) {

                $recipientExists = $mailshot->recipients()
                    ->where('recipient_id', $recipient->id)
                    ->where('recipient_type', class_basename($recipient))
                    ->exists();

                if (!$recipientExists && filter_var($recipient->email, FILTER_VALIDATE_EMAIL)) {

                    $outbox = $recipient->shop->outboxes()->where('code', OutboxCodeEnum::INVITE)->first();

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

            // After processing the chunk, update and dispatch the delivery channel
            UpdateEmailDeliveryChannel::run(
                $emailDeliveryChannel,
                [
                    'number_emails' => $mailshot->recipients()->where('channel', $emailDeliveryChannel->id)->count()
                ]
            );
            SendEmailDeliveryChannel::dispatch($emailDeliveryChannel);
        });

        UpdateMailshot::run(
            $mailshot,
            [
                'recipients_stored_at' => now()
            ]
        );

        // TODO: check another hydrator
        MailshotHydrateDispatchedEmails::run($mailshot);
    }

    public string $commandSignature = 'mailshot:send-prospects {mailshot}';

    public function asCommand(Command $command): int
    {
        try {
            $mailshot = Mailshot::where('slug', $command->argument('mailshot'))->firstOrFail();
        } catch (Exception $e) {
            $command->error($e->getMessage());

            return 1;
        }

        $this->handle($mailshot);

        return 0;
    }
}
