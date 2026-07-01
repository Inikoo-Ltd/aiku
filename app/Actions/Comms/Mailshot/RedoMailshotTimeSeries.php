<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\Comms\MailshotTimeSeries\ProcessMailshotTimeSeriesRecords;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Actions\Traits\WithTimeSeriesRedo;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Comms\Mailshot;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Telescope\Telescope;

class RedoMailshotTimeSeries implements ShouldBeUnique
{
    use WithHydrateCommand;
    use WithTimeSeriesRedo {
        WithTimeSeriesRedo::asCommand insteadof WithHydrateCommand;
    }

    public string $jobQueue         = 'long-low-priority';
    public string $commandSignature = 'mailshots:redo_time_series {--from= : Start date (Y-m-d)} {--to= : End date (Y-m-d)} {--a|async : Run asynchronously}';

    public function __construct()
    {
        $this->model = Mailshot::class;
    }

    public function getJobUniqueId(string $from, string $to): string
    {
        return "{$from}_$to";
    }

    protected function beforeCommand(Command $command): void
    {
        if (class_exists(Telescope::class)) {
            Telescope::stopRecording();
        }
    }

    public function handle(?int $mailshotId, ?string $from = null, ?string $to = null, bool $async = false): void
    {
        if (!$mailshotId) {
            return;
        }

        $mailshot = Mailshot::find($mailshotId);

        if (!$mailshot) {
            return;
        }

        if (!$from || !$to) {
            $dateRange = DB::table('email_tracking_events')
                ->join('dispatched_emails', 'email_tracking_events.dispatched_email_id', '=', 'dispatched_emails.id')
                ->join('mailshot_has_dispatched_emails', 'dispatched_emails.id', '=', 'mailshot_has_dispatched_emails.dispatched_email_id')
                ->where('mailshot_has_dispatched_emails.mailshot_id', $mailshot->id)
                ->selectRaw('MIN(email_tracking_events.created_at) as first_date, MAX(email_tracking_events.created_at) as last_date')
                ->first();

            if (!$dateRange?->first_date) {
                return;
            }

            $from = $from ?? Carbon::parse($dateRange->first_date)->toDateString();
            $to   = $to ?? Carbon::parse($dateRange->last_date ?? now())->toDateString();
        }

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            if ($async) {
                ProcessMailshotTimeSeriesRecords::dispatch($mailshot->id, $frequency, $from, $to)->onQueue('sales_slave_historic');
            } else {
                ProcessMailshotTimeSeriesRecords::run($mailshot->id, $frequency, $from, $to);
            }
        }
    }
}
