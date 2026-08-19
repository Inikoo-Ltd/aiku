<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Ordering\SalesChannel;

use App\Helpers\TimeSeriesPeriodCalculator;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Actions\Traits\WithTimeSeriesRedo;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Ordering\SalesChannel;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RedoSalesChannelTimeSeries implements ShouldBeUnique
{
    use WithHydrateCommand;
    use WithTimeSeriesRedo {
        WithTimeSeriesRedo::asCommand insteadof WithHydrateCommand;
    }

    public string $jobQueue = 'long-low-priority';
    public string $commandSignature = 'sales-channels:redo_time_series {--from= : Start date (Y-m-d)} {--to= : End date (Y-m-d)} {--a|async : Run asynchronously}';

    public function __construct()
    {
        $this->model = SalesChannel::class;
    }

    public function getJobUniqueId(?int $salesChannelId, string $from, string $to): string
    {
        if ($salesChannelId == null) {
            $salesChannelId = 'empty';
        }

        return $salesChannelId.":{$from}_$to";
    }

    protected function dateRangeSources(): array
    {
        return [
            [
                'query' => fn () => DB::connection('aiku_no_sticky')->table('invoices')->whereNull('deleted_at'),
                'key'   => 'sales_channel_id',
                'date'  => 'date',
            ],
        ];
    }

    public function handle(?int $salesChannelId, ?string $from = null, ?string $to = null, bool $async = false): void
    {
        if (!$salesChannelId) {
            return;
        }

        $salesChannel = SalesChannel::find($salesChannelId);

        if (!$salesChannel) {
            return;
        }

        if (!$from || !$to) {
            $dateRange = $this->getDateRange($salesChannel->id);

            if (!$dateRange['from']) {
                return;
            }

            $from = $from ?? Carbon::parse($dateRange['from'])->toDateString();
            $to   = $to ?? Carbon::parse($dateRange['to'] ?? now())->toDateString();
        }

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            [$periodFrom, $periodTo] = TimeSeriesPeriodCalculator::expandWindowToFullPeriods($frequency, $from, $to);

            if ($async) {
                ProcessSalesChannelTimeSeriesRecords::dispatch($salesChannel->id, $frequency, $periodFrom, $periodTo)->onQueue('sales_slave_historic');
            } else {
                ProcessSalesChannelTimeSeriesRecords::run($salesChannel->id, $frequency, $periodFrom, $periodTo);
            }
        }
    }


}
