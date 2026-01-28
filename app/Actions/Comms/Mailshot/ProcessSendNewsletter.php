<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Tuesday, 6 Jan 2026 11:56:32 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
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
use App\Models\CRM\Customer;
use Exception;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Services\QueryBuilder;

class ProcessSendNewsletter
{
    use AsAction;

    public string $jobQueue = 'default-long';

    public function tags(): array
    {
        return ['send_newsletter'];
    }

    public function handle(Mailshot $mailshot): void
    {
        $counter      = 0;
        $queryBuilder = QueryBuilder::for(Customer::class)
            ->join('customer_comms', 'customers.id', '=', 'customer_comms.customer_id')
            ->where('shop_id', $mailshot->shop_id)
            ->where('customer_comms.is_subscribed_to_newsletter', true)
            ->where('customers.email', '!=', null)
            ->select('customers.id', 'customers.shop_id', 'customers.name', 'customers.email', 'customers.slug');

        $emailDeliveryChannel = StoreEmailDeliveryChannel::run($mailshot);

        // TODO: update this section using cursor() instead of get()
        foreach ($queryBuilder->cursor() as $recipient) {
            if ($counter >= 250) {
                UpdateEmailDeliveryChannel::run(
                    $emailDeliveryChannel,
                    [
                        'number_emails' => $mailshot->recipients()->where('channel', $emailDeliveryChannel->id)->count()
                    ]
                );
                SendEmailDeliveryChannel::dispatch($emailDeliveryChannel);

                // Note: create new delivery channel for next batch
                $emailDeliveryChannel = StoreEmailDeliveryChannel::run($mailshot);
                $counter             = 0;
            }

            //  make sure this section
            $recipientExists = $mailshot->recipients()->where('recipient_id', $recipient->id)->where('recipient_type', class_basename($recipient))->exists();
            if (!$recipientExists && filter_var($recipient->email, FILTER_VALIDATE_EMAIL)) {

                $outbox = $recipient->shop->outboxes()->where('code', OutboxCodeEnum::NEWSLETTER)->first();
                $dispatchedEmail = StoreDispatchedEmail::run(
                    $mailshot,
                    $recipient,
                    [
                        'is_test'       => false,
                        'outbox_id'     => $outbox->id,
                        'email_address' => $recipient->email,
                        'provider'      => DispatchedEmailProviderEnum::SES,
                    ]
                );

                $modelData = [
                    'dispatched_email_id' => $dispatchedEmail->id,
                    'recipient_type' => class_basename($recipient),
                    'recipient_id' => $recipient->id,
                    'channel' => $emailDeliveryChannel->id,
                ];
                StoreMailshotRecipient::run(
                    $mailshot,
                    $modelData
                );
            }

            $counter++;
        }

        // Handle the final batch (if any recipients were processed)
        if ($counter > 0) {
            UpdateEmailDeliveryChannel::run(
                $emailDeliveryChannel,
                [
                    'number_emails' => $mailshot->recipients()->where('channel', $emailDeliveryChannel->id)->count()
                ]
            );
            SendEmailDeliveryChannel::dispatch($emailDeliveryChannel);
        }

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
