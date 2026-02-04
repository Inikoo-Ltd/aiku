<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Wednesday, 4 Feb 2026 16:53:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Enums\Comms\Mailshot\MailshotTypeEnum;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Services\QueryBuilder;
use App\Models\Comms\Mailshot;
use Illuminate\Support\Carbon;

class RunMailshotSecondWave
{
    use AsAction;
    public string $jobQueue = 'default-long';
    public string $commandSignature = 'run-mailshot-second-wave';

    public function handle(): void
    {
        $currentDateTime = Carbon::now()->utc();

        // use mailshot as main table
        $newsletterQuery = QueryBuilder::for(Mailshot::class);
        $newsletterQuery->whereIn('type', [MailshotTypeEnum::NEWSLETTER, MailshotTypeEnum::MARKETING]);
        $newsletterQuery->where('is_second_wave', true);
        $newsletterQuery->whereNull('deleted_at');
        $newsletterQuery->whereNull('cancelled_at');
        $newsletterQuery->whereNull('stopped_at');
        $newsletterQuery->whereNull('sent_at');
        $newsletterQuery->whereNull('start_sending_at');
        $newsletterQuery->whereNull('source_id'); // to avoid resending newsletter that imported from Aurora
        $newsletterQuery->whereNull('source_alt_id'); // to avoid resending newsletter that imported from Aurora
        $newsletterQuery->whereNull('source_alt2_id'); // to avoid resending newsletter that imported from Aurora
        $newsletterQuery->whereRaw("scheduled_at AT TIME ZONE 'UTC' <= ?", [$currentDateTime]); // make sure have save time zone before compare

        foreach ($newsletterQuery->cursor() as $newsletter) {
            // ProcessSendNewsletter::dispatch($newsletter);
            // //TODO: update the mailshot state to Sending
            // $newsletter->update([
            //     'state' => MailshotStateEnum::SENDING,
            //     'start_sending_at' => Carbon::now()->utc(), // maybe need to convert to local timezone
            // ]);
        }
    }

    public function asCommand(): void
    {
        $this->run();
    }
}
