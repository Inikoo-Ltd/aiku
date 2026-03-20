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
            ->whereNotNull('customers.email')
            ->whereRaw("customers.email COLLATE \"C\" ~* '^[a-z0-9._%+\\-]+@[a-z0-9.\\-]+\\.[a-z]{2,}$'"); // to replace FILTER_VALIDATE_EMAIL in the model level


        // Clone the query and get last
        $totalCustomer = $baseQuery->count('customers.id');

        $baseQuery->select('customers.id')->orderBy('customers.id')
            ->chunk($chunkSize, function ($customers) use ($mailshot, $totalCustomer) {
                $customerIds = $customers->pluck('id')->toArray();
                ProcessSendMailshot::dispatch($mailshot->id, $customerIds, $totalCustomer);
            });

        $mailshot->update([
            'recipients_prepared_at' => now()
        ]);

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
