<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Dec 2023 09:24:16 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\Comms\DispatchedEmail\StoreDispatchedEmail;
use App\Actions\Comms\EmailDeliveryChannel\SendEmailDeliveryChannel;
use App\Actions\Comms\EmailDeliveryChannel\StoreEmailDeliveryChannel;
use App\Actions\Comms\EmailDeliveryChannel\UpdateEmailDeliveryChannel;
use App\Actions\Comms\Mailshot\Hydrators\MailshotHydrateDispatchedEmails;
use App\Enums\Comms\DispatchedEmail\DispatchedEmailProviderEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Comms\Mailshot;
use Exception;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessSendMailshot
{
    use AsAction;

    public string $jobQueue = 'default-long';

    public function tags(): array
    {
        return ['send_mailshot'];
    }

    public function handle(Mailshot $mailshot): void
    {
        $queryBuilder = GetMailshotRecipientsQueryBuilder::make()->handle($mailshot);

        // Process recipients in chunks of 250
        $queryBuilder->chunk(250, function ($recipients) use ($mailshot) {

            $emailDeliveryChannel = StoreEmailDeliveryChannel::run($mailshot);

            foreach ($recipients as $recipient) {

                $recipientExists = $mailshot->recipients()
                    ->where('recipient_id', $recipient->id)
                    ->where('recipient_type', class_basename($recipient))
                    ->exists();

                if (!$recipientExists && filter_var($recipient->email, FILTER_VALIDATE_EMAIL)) {

                    $outbox = $recipient->shop->outboxes()->where('code', OutboxCodeEnum::MARKETING)->first();

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

    public string $commandSignature = 'mailshot:send {mailshot}';

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
