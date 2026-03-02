<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Friday, 30 Jan 2026 08:47:30 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\CRM\Prospect\Mailshots;

use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Enums\Comms\Mailshot\MailshotTypeEnum;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Services\QueryBuilder;
use App\Models\Comms\Mailshot;
use Illuminate\Support\Carbon;

class RunProspectMailshotScheduled
{
    use AsAction;
    public string $jobQueue = 'default-long';
    public string $commandSignature = 'run-prospect-mailshot-scheduled';

    public function handle(): void
    {
        $currentDateTime = Carbon::now()->utc();

        // use mailshot as main table
        $mailshotQuery = QueryBuilder::for(Mailshot::class);
        $mailshotQuery->where('type', MailshotTypeEnum::INVITE);
        $mailshotQuery->where('state', MailshotStateEnum::SCHEDULED);
        $mailshotQuery->whereNull('deleted_at');
        $mailshotQuery->whereNull('cancelled_at');
        $mailshotQuery->whereNull('stopped_at');
        $mailshotQuery->whereNull('sent_at');
        $mailshotQuery->whereNull('start_sending_at');
        $mailshotQuery->whereNull('source_id'); // to avoid resending newsletter that imported from Aurora
        $mailshotQuery->whereNull('source_alt_id'); // to avoid resending newsletter that imported from Aurora
        $mailshotQuery->whereNull('source_alt2_id'); // to avoid resending newsletter that imported from Aurora
        $mailshotQuery->whereRaw("scheduled_at AT TIME ZONE 'UTC' <= ?", [$currentDateTime]); // make sure have save time zone before compare

        foreach ($mailshotQuery->cursor() as $mailshot) {
            ProcessSendProspectMailshot::dispatch($mailshot);

            $mailshot->update([
                'state' => MailshotStateEnum::SENDING,
                'start_sending_at' => Carbon::now()->utc(),
            ]);
        }
    }

    public function asCommand(): void
    {
        $this->run();
    }
}
