<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Dropshipping\Platform;

use App\Helpers\TimeSeriesPeriodCalculator;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Actions\Traits\WithTimeSeriesRedo;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Dropshipping\Platform;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RedoPlatformTimeSeries implements ShouldBeUnique
{
    use WithHydrateCommand;
    use WithTimeSeriesRedo {
        WithTimeSeriesRedo::asCommand insteadof WithHydrateCommand;
    }

    public string $jobQueue = 'long-low-priority';
    public string $commandSignature = 'platforms:redo_time_series {--from= : Start date (Y-m-d)} {--to= : End date (Y-m-d)} {--a|async : Run asynchronously}';

    public function __construct()
    {
        $this->model = Platform::class;
    }

    public function getJobUniqueId(?int $platformId, ?string $from, ?string $to): string
    {
        if ($platformId === null) {
            return 'empty'.'_'.$from.'_'.$to;
        }
        return $platformId.'_'.$from.'_'.$to;
    }

    protected function dateRangeSources(): array
    {
        return [
            [
                'query' => fn () => DB::connection('aiku_no_sticky')->table('invoices')->whereNull('deleted_at'),
                'key'   => ['platform_id', 'shop_id'],
                'date'  => 'date',
            ],
            [
                'query' => fn () => DB::connection('aiku_no_sticky')->table('customer_sales_channels'),
                'key'   => ['platform_id', 'shop_id'],
                'date'  => 'created_at',
            ],
            [
                'query' => fn () => DB::connection('aiku_no_sticky')->table('portfolios'),
                'key'   => ['platform_id', 'shop_id'],
                'date'  => 'created_at',
            ],
            [
                'query' => fn () => DB::connection('aiku_no_sticky')->table('customer_clients'),
                'key'   => ['platform_id', 'shop_id'],
                'date'  => 'created_at',
            ],
        ];
    }

    public function handle(?int $platformId, ?string $from = null, ?string $to = null, bool $async = false): void
    {
        if (!$platformId) {
            return;
        }

        $platform = Platform::find($platformId);

        if (!$platform) {
            return;
        }

        $shopIds = DB::connection('aiku_no_sticky')->table('invoices')->where('platform_id', $platform->id)->whereNull('deleted_at')->whereNotNull('shop_id')->distinct()->pluck('shop_id');

        foreach ($shopIds as $shopId) {
            if (!$from || !$to) {
                $dateRange = $this->getDateRange([$platform->id, $shopId]);

                if (!$dateRange['from']) {
                    continue;
                }

                $resolvedFrom = Carbon::parse($dateRange['from'])->toDateString();
                $resolvedTo   = Carbon::parse($dateRange['to'] ?? now())->toDateString();
            } else {
                $resolvedFrom = $from;
                $resolvedTo   = $to;
            }

            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                [$periodFrom, $periodTo] = TimeSeriesPeriodCalculator::expandWindowToFullPeriods($frequency, $resolvedFrom, $resolvedTo);

                if ($async) {
                    ProcessPlatformTimeSeriesRecords::dispatch($platform->id, $shopId, $frequency, $periodFrom, $periodTo);
                } else {
                    ProcessPlatformTimeSeriesRecords::run($platform->id, $shopId, $frequency, $periodFrom, $periodTo);
                }
            }
        }
    }
}
