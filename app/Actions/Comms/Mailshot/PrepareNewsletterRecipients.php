<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Tuesday, 6 Jan 2026 11:56:32 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Models\Comms\Mailshot;
use Exception;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Support\Facades\DB;

class PrepareNewsletterRecipients
{
    use AsAction;

    public string $jobQueue = 'default-long';
    protected int $countRecipients = 0;

    public function tags(): array
    {
        return ['send_newsletter'];
    }

    public function handle(Mailshot $mailshot): void
    {
        $chunkSize = 50;

        // NOTE: Ensure no second wave exists when the parent mailshot has the second wave disabled
        if ($mailshot->secondWave()->exists() && !$mailshot->is_second_wave_enabled) {
            DeleteMailshotSecondWave::run($mailshot->secondWave);
        }


        $baseQuery = DB::table('customers')
            ->join('customer_comms', 'customers.id', '=', 'customer_comms.customer_id')
            ->where('customers.shop_id', $mailshot->shop_id)
            ->where('customer_comms.is_subscribed_to_newsletter', true)
            ->whereNotNull('customers.email');


        $baseQuery->select('customers.id', 'customers.email')->orderBy('customers.id')
            ->chunk($chunkSize, function ($customers) use ($mailshot) {
                $customerIds    = [];
                $numValidEmails = 0;
                foreach ($customers as $customer) {
                    if (filter_var($customer->email, FILTER_VALIDATE_EMAIL)) {
                        $customerIds[] = $customer->id;
                        $numValidEmails++;
                    }
                }

                ProcessSendMailshot::dispatch($mailshot->id, $customerIds);
                $this->countRecipients += $numValidEmails;
            });

        $mailshot->update([
            'recipients_prepared_at' => now(),
            'recipients_count'       => $this->countRecipients,
        ]);
        UpdateMailshotRecipientsStoredAt::run($mailshot);
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
