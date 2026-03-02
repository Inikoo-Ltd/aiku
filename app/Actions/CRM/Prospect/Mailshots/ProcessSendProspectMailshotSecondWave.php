<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Thursday, 5 Feb 2026 09:40:43 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\CRM\Prospect\Mailshots;

use App\Actions\Comms\DispatchedEmail\StoreDispatchedEmail;
use App\Actions\Comms\EmailDeliveryChannel\SendEmailDeliveryChannel;
use App\Actions\Comms\EmailDeliveryChannel\StoreEmailDeliveryChannel;
use App\Actions\Comms\EmailDeliveryChannel\UpdateEmailDeliveryChannel;
use App\Actions\Comms\Mailshot\Hydrators\MailshotHydrateDispatchedEmails;
use App\Actions\Comms\Mailshot\StoreMailshotRecipient;
use App\Actions\Comms\Mailshot\UpdateMailshot;
use App\Enums\Comms\DispatchedEmail\DispatchedEmailProviderEnum;
use App\Enums\Comms\DispatchedEmail\DispatchedEmailStateEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Comms\Mailshot;
use App\Models\CRM\Prospect;
use App\Services\QueryBuilder;
use Exception;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessSendProspectMailshotSecondWave
{
    use AsAction;

    public string $jobQueue = 'default-long';

    public function tags(): array
    {
        return ['send_prospect_mailshot_second_wave'];
    }

    public function handle(Mailshot $mailshot): void
    {
        $parentMailshots = $mailshot->parentMailshot;

        if (!$parentMailshots) {
            \Log::warning('Mailshot does not have parent mailshot, skipping second wave processing', [
                'mailshot_id' => $mailshot->id,
            ]);
            return;
        }

        $queryBuilder = QueryBuilder::for(Prospect::class);
        $queryBuilder->join('dispatched_emails', function ($join) {
            $join->on('prospects.id', '=', 'dispatched_emails.recipient_id')
                ->where('dispatched_emails.recipient_type', '=', class_basename(Prospect::class));
        });
        $queryBuilder->where('dispatched_emails.parent_type', class_basename(Mailshot::class));
        $queryBuilder->where('dispatched_emails.parent_id', $parentMailshots->id);
        $queryBuilder->where('dispatched_emails.state', DispatchedEmailStateEnum::SENT->value);
        $queryBuilder->whereNotNull('dispatched_emails.sent_at');

        $queryBuilder->where('prospects.shop_id', $mailshot->shop_id)
            ->whereNull('prospects.customer_id')
            ->where('prospects.can_contact_by_email', true)
            ->where('prospects.is_valid_email', true)
            ->whereNotNull('prospects.email');

        $queryBuilder->select('prospects.id', 'prospects.shop_id', 'prospects.name', 'prospects.email', 'prospects.slug');

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

        // TODO: need make sure any hydrator here
        MailshotHydrateDispatchedEmails::run($mailshot);
    }

    public string $commandSignature = 'prospect-mailshot-second-wave:send {mailshot}';

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
