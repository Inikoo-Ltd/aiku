<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Dec 2023 09:24:16 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailshot;

use App\Models\Comms\Mailshot;
use Exception;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class PrepareMailshotRecipients
{
    use AsAction;

    public string $jobQueue = 'default-long';

    public function tags(): array
    {
        return ['send_mailshot'];
    }

    /**
     * @throws \Exception
     */
    public function handle(Mailshot $mailshot): void
    {
        $chunkSize = 50;

        // NOTE: Ensure no second wave exists when the parent mailshot has the second wave disabled
        if ($mailshot->secondWave()->exists() && !$mailshot->is_second_wave_enabled) {
            DeleteMailshotSecondWave::run($mailshot->secondWave);
        }

        $queryBuilder = GetMailshotRecipientsQueryBuilder::make()->handle($mailshot);

        $mailshotId = $mailshot->id;

        $queryBuilder->orderBy('customers.id');

        $cloneQuery     = $queryBuilder->clone();
        $totalCustomers = $cloneQuery->count('customers.id');

        $queryBuilder->select('customers.id')->chunk($chunkSize, function ($customers) use ($mailshotId, $totalCustomers) {
            $customerIds = $customers->pluck('id')->toArray();
            ProcessSendMailshot::dispatch($mailshotId, $customerIds, $totalCustomers);
        });

        $mailshot->update([
            'recipients_prepared_at' => now()
        ]);
    }

    public string $commandSignature = 'mailshot:send {mailshot}';

    /**
     * @throws \Exception
     */
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
