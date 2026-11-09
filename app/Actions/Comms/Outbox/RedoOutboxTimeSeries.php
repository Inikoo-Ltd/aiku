<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Comms\Outbox;

use App\Helpers\TimeSeriesPeriodCalculator;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Actions\Traits\WithTimeSeriesRedo;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Comms\Outbox;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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

    public function getJobUniqueId(int $outboxId, string $from, string $to): string
    {
        return "{$outboxId}_{$from}_$to";
    }

    protected function dateRangeSources(): array
    {
        return [
            [
                'query' => fn () => DB::connection('aiku_no_sticky')->table('dispatched_emails'),
                'key'   => 'outbox_id',
                'date'  => 'created_at',
            ],
        ];
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
            $dateRange = $this->getDateRange($outbox->id);

            if (!$dateRange['from']) {
                return;
            }

            $from = $from ?? Carbon::parse($dateRange['from'])->toDateString();
            $to   = $to ?? Carbon::parse($dateRange['to'] ?? now())->toDateString();
        }

        /**
         * The weekly to yearly records are summed from the daily ones, so they run after the daily
         * records in this one job. Queued as separate jobs they would read the dailies before the
         * daily job rewrites them.
         */
        if ($async) {
            static::dispatch($outbox->id, $from, $to)->onQueue('sales_slave_historic');

            return;
        }

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            [$periodFrom, $periodTo] = TimeSeriesPeriodCalculator::expandWindowToFullPeriods($frequency, $from, $to);

            ProcessOutboxTimeSeriesRecords::run($outbox->id, $frequency, $periodFrom, $periodTo);
        }
    }
}
