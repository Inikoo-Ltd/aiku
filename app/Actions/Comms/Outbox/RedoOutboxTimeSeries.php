<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Comms\Outbox;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Actions\Traits\WithTimeSeriesRedo;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Comms\Outbox;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Telescope\Telescope;

class RedoOutboxTimeSeries implements ShouldBeUnique
{
    use WithHydrateCommand;
    use WithTimeSeriesRedo {
        WithTimeSeriesRedo::asCommand insteadof WithHydrateCommand;
    }

    public string $jobQueue         = 'long-low-priority';
    public string $commandSignature = 'outboxes:redo_time_series {--S|shop= : Shop slug} {--O|organisation= : Organisation slug} {--from= : Start date (Y-m-d)} {--to= : End date (Y-m-d)} {--a|async : Run asynchronously}';

    public function __construct()
    {
        $this->model = Outbox::class;
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

    public function handle(?int $outboxId, ?string $from = null, ?string $to = null, bool $async = false): void
    {
        if (!$outboxId) {
            return;
        }

        $outbox = Outbox::find($outboxId);

        if (!$outbox) {
            return;
        }

        if (!$from || !$to) {
            $dateRange = DB::connection('aiku_no_sticky')->table('dispatched_emails')
                ->where('outbox_id', $outbox->id)
                ->selectRaw('MIN(created_at) as first_date, MAX(created_at) as last_date')
                ->first();

            if (!$dateRange?->first_date) {
                return;
            }

            $from = $from ?? Carbon::parse($dateRange->first_date)->toDateString();
            $to   = $to ?? Carbon::parse($dateRange->last_date ?? now())->toDateString();
        }

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            if ($async) {
                ProcessOutboxTimeSeriesRecords::dispatch($outbox->id, $frequency, $from, $to)->onQueue('sales_slave_historic');
            } else {
                ProcessOutboxTimeSeriesRecords::run($outbox->id, $frequency, $from, $to);
            }
        }
    }
}
