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

        // use mailshot second wave filter
        $secondWaveQuery = QueryBuilder::for(Mailshot::class);
        $secondWaveQuery->whereIn('type', [MailshotTypeEnum::NEWSLETTER, MailshotTypeEnum::MARKETING]);
        $secondWaveQuery->where('state', MailshotStateEnum::READY);
        $secondWaveQuery->where('is_second_wave', true);
        $secondWaveQuery->whereNull('deleted_at');
        $secondWaveQuery->whereNull('cancelled_at');
        $secondWaveQuery->whereNull('stopped_at');
        $secondWaveQuery->whereNull('sent_at');
        $secondWaveQuery->whereNull('start_sending_at');
        $secondWaveQuery->where('send_delay_hours', '>', 0);
        $secondWaveQuery->whereNull('source_id'); // to avoid resending newsletter that imported from Aurora
        $secondWaveQuery->whereNull('source_alt_id'); // to avoid resending newsletter that imported from Aurora
        $secondWaveQuery->whereNull('source_alt2_id'); // to avoid resending newsletter that imported from Aurora

        // Check parent mailshot conditions if parent_mailshot_id exists
        $secondWaveQuery->where(function ($query) use ($currentDateTime) {
            $query->whereHas('parentMailshot', function ($parentQuery) use ($currentDateTime) {
                $parentQuery->where('state', MailshotStateEnum::SENT)
                    ->where('is_second_wave_enabled', true)
                    ->whereNotNull('sent_at')
                    ->whereRaw("sent_at + (mailshots.send_delay_hours || ' hours')::interval <= ?", [$currentDateTime]);
            });
        });

        // NOTE: for debug the SQL query
        // \Log::info($secondWaveQuery->toRawSql());
        foreach ($secondWaveQuery->cursor() as $secondWave) {
            ProcessSendMailshotSecondWave::dispatch($secondWave);
            $secondWave->update([
                'state' => MailshotStateEnum::SENDING,
                'start_sending_at' => Carbon::now()->utc()
            ]);
        }
    }

    public function asCommand(): void
    {
        $this->run();
    }
}
