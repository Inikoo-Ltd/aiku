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
use App\Actions\Comms\Traits\WithSendCustomerOutboxEmail;
use App\Actions\Traits\WithCheckCanContactByEmail;
use App\Models\Comms\Email;
use App\Models\Comms\Mailshot;
use App\Models\CRM\Customer;
use Exception;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Services\QueryBuilder;

class ProcessSendNewsletterNew
{
    use AsAction;
    use WithSendCustomerOutboxEmail;

    public string $jobQueue = 'default_long';

    public function tags(): array
    {
        return ['send_newsletter'];
    }

    public function handle(Mailshot $mailshot): void
    {
        $counter      = 0;
        $queryBuilder = $this->getNewsletterRecipientsQueryBuilder($mailshot);

        $emailDeliveryChannel = StoreEmailDeliveryChannel::run($mailshot);

        foreach ($queryBuilder->get() as $recipient) {
            if ($counter >= 250) {
                UpdateEmailDeliveryChannel::run(
                    $emailDeliveryChannel,
                    [
                        'number_emails' => $mailshot->recipients()->where('channel', $emailDeliveryChannel->id)->count()
                    ]
                );
                SendEmailDeliveryChannel::dispatch($emailDeliveryChannel);

                //  for what should store new delivery channel
                $emailDeliveryChannel = StoreEmailDeliveryChannel::run($mailshot);
                $counter             = 0;
            }

            //  make sure this section
            $recipientExists = $mailshot->recipients()->where('recipient_id', $recipient->id)->where('recipient_type', class_basename($recipient))->exists();
            if (!$recipientExists) {
                if (!app()->environment('production') and config('mail.devel.rewrite_mailshot_recipients_email', true)) {
                    $prefixes     = ['success' => 50, 'bounce' => 30, 'complaint' => 20];
                    $prefix       = $this->getRandomElementWithProbabilities($prefixes);
                    $emailAddress = "$prefix+$recipient->slug@simulator.amazonses.com";
                } else {
                    $emailAddress = $recipient->email;
                }

                $email = Email::firstOrCreate(['address' => $emailAddress]);

                $dispatchedEmail = StoreDispatchedEmail::run(
                    email: $email,
                    mailshot: $mailshot,
                    modelData: [
                        'recipient_type' => $recipient->getMorphClass(),
                        'recipient_id'   => $recipient->id
                    ]
                );

                StoreMailshotRecipient::run(
                    $mailshot,
                    $dispatchedEmail,
                    $recipient,
                    [
                        'channel' => $emailDeliveryChannel->id,
                    ]
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
        MailshotHydrateDispatchedEmails::run($mailshot);
    }

    private function getRandomElementWithProbabilities(array $prefixes): string
    {
        $total = array_sum($prefixes);
        $random = mt_rand(1, $total);

        $current = 0;
        foreach ($prefixes as $prefix => $weight) {
            $current += $weight;
            if ($random <= $current) {
                return $prefix;
            }
        }

        return array_key_first($prefixes);
    }

    private function getNewsletterRecipientsQueryBuilder(Mailshot $mailshot)
    {
        return QueryBuilder::for(Customer::class)
            ->join('customer_comms', 'customers.id', '=', 'customer_comms.customer_id')
            ->where('shop_id', $mailshot->shop_id)
            ->where('customer_comms.is_subscribed_to_newsletter', true)
            ->where('customers.email', '!=', null)
            ->select('customers.id', 'customers.shop_id', 'customers.name', 'customers.email', 'customers.slug');
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
