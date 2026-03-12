<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Dec 2023 09:24:16 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\Comms\Mailshot\Hydrators\MailshotHydrateDispatchedEmails;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
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

    public function handle(Mailshot $mailshot): void
    {
        $chunkSize = 100;

        // NOTE: Ensure no second wave exists when the parent mailshot has the second wave disabled
        if ($mailshot->secondWave()->exists() && !$mailshot->is_second_wave_enabled) {
            DeleteMailshotSecondWave::run($mailshot->secondWave);
        }

        $queryBuilder = GetMailshotRecipientsQueryBuilder::make()->handle($mailshot);

        $outboxId = $mailshot->shop->outboxes()->where('code', OutboxCodeEnum::NEWSLETTER)->value('id');

        $mailshotId = $mailshot->id;

        // Process recipients in chunks of 250
        $queryBuilder->chunk($chunkSize, function ($customers) use ($mailshotId, $outboxId) {
            ProcessSendMailshot::dispatch($mailshotId, $customers, $outboxId);
        });

        //  make hydrator later
        // UpdateMailshot::run(
        //     $mailshot,
        //     [
        //         'recipients_stored_at' => now()
        //     ]
        // );

        // // TODO: check another hydrator
        // MailshotHydrateDispatchedEmails::run($mailshot);
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
