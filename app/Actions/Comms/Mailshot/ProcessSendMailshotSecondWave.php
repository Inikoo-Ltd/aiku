<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Thursday, 5 Feb 2026 09:40:43 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateMailshots;
use App\Actions\Comms\DispatchedEmail\StoreDispatchedEmail;
use App\Actions\Comms\EmailDeliveryChannel\SendEmailDeliveryChannel;
use App\Actions\Comms\EmailDeliveryChannel\StoreEmailDeliveryChannel;
use App\Actions\Comms\EmailDeliveryChannel\UpdateEmailDeliveryChannel;
use App\Actions\Comms\Mailshot\Hydrators\MailshotHydrateDispatchedEmails;
use App\Actions\Comms\Outbox\Hydrators\OutboxHydrateMailshots;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateMailshots;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateMailshots;
use App\Enums\Comms\DispatchedEmail\DispatchedEmailProviderEnum;
use App\Enums\Comms\DispatchedEmail\DispatchedEmailStateEnum;
use App\Enums\Comms\Mailshot\MailshotTypeEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Comms\Mailshot;
use App\Models\CRM\Customer;
use Exception;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Services\QueryBuilder;
use Illuminate\Support\Facades\Log;

class ProcessSendMailshotSecondWave
{
    use AsAction;

    public string $jobQueue = 'default-long';

    public function tags(): array
    {
        return ['send_mailshot_second_wave'];
    }

    public function handle(Mailshot $mailshot): void
    {
        $parentMailshots = $mailshot->parentMailshot;

        if (!$parentMailshots) {
            Log::warning('Mailshot does not have parent mailshot, skipping second wave processing', [
                'mailshot_id' => $mailshot->id,
            ]);
            return;
        }

        $queryBuilder = QueryBuilder::for(Customer::class);
        $queryBuilder->join('customer_comms', 'customers.id', '=', 'customer_comms.customer_id');
        $queryBuilder->join('dispatched_emails', function ($join) {
            $join->on('customers.id', '=', 'dispatched_emails.recipient_id')
                ->where('dispatched_emails.recipient_type', '=', class_basename(Customer::class));
        });
        $queryBuilder->where('dispatched_emails.parent_type', class_basename(Mailshot::class));
        $queryBuilder->where('dispatched_emails.parent_id', $parentMailshots->id);
        $queryBuilder->where('dispatched_emails.state', DispatchedEmailStateEnum::SENT->value);
        $queryBuilder->whereNotNull('dispatched_emails.sent_at');

        $queryBuilder->where('customers.shop_id', $mailshot->shop_id);
        $queryBuilder->where('customers.email', '!=', null);

        switch ($mailshot->type) {
            case MailshotTypeEnum::NEWSLETTER:
                $queryBuilder->where('customer_comms.is_subscribed_to_newsletter', true);
                break;
            case MailshotTypeEnum::MARKETING:
                $queryBuilder->where('customer_comms.is_subscribed_to_marketing', true);
                break;
            default:
                // Return invalid query for unsupported types
                $queryBuilder->whereRaw('1 = 0');
                break;
        }

        $queryBuilder->select('customers.id', 'customers.shop_id', 'customers.name', 'customers.email', 'customers.slug');

        // NOTE: for debug the SQl query
        // \Log::info($queryBuilder->toRawSql());

        // Process recipients in chunks of 250
        $queryBuilder->chunk(250, function ($recipients) use ($mailshot) {

            $emailDeliveryChannel = StoreEmailDeliveryChannel::run($mailshot);

            foreach ($recipients as $recipient) {

                $recipientExists = $mailshot->recipients()
                    ->where('recipient_id', $recipient->id)
                    ->where('recipient_type', class_basename($recipient))
                    ->exists();

                if (!$recipientExists && filter_var($recipient->email, FILTER_VALIDATE_EMAIL)) {

                    $outboxCode = $mailshot->type === MailshotTypeEnum::NEWSLETTER ? OutboxCodeEnum::NEWSLETTER : OutboxCodeEnum::MARKETING;
                    $outbox = $recipient->shop->outboxes()->where('code', $outboxCode)->first();

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

        MailshotHydrateDispatchedEmails::run($mailshot);
        GroupHydrateMailshots::dispatch($mailshot->group);
        OrganisationHydrateMailshots::dispatch($mailshot->organisation);
        OutboxHydrateMailshots::dispatch($mailshot->outbox);
        ShopHydrateMailshots::dispatch($mailshot->shop);
    }

    public string $commandSignature = 'mailshot-second-wave:send {mailshot}';

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
