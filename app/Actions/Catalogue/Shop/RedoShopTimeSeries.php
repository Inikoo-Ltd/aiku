<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\Shop;

use App\Helpers\TimeSeriesPeriodCalculator;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Actions\Traits\WithTimeSeriesRedo;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RedoShopTimeSeries implements ShouldBeUnique
{
    use WithHydrateCommand;
    use WithTimeSeriesRedo {
        WithTimeSeriesRedo::asCommand insteadof WithHydrateCommand;
    }

    public string $jobQueue = 'long-low-priority';
    public string $commandSignature = 'shops:redo_time_series {--O|organisation= : Organisation slug} {--from= : Start date (Y-m-d)} {--to= : End date (Y-m-d)} {--a|async : Run asynchronously}';

    public function __construct()
    {
        $this->model = Shop::class;
    }

    public function getJobUniqueId(?int $shopId, ?string $from, ?string $to): string
    {
        if ($shopId === null) {
            return 'empty'.'_'.$from.'_'.$to;
        }

        return $shopId.'_'.$from.'_'.$to;
    }

    protected function dateRangeSources(): array
    {
        return [
            [
                'query' => fn () => DB::connection('aiku_no_sticky')->table('invoices')->whereNull('deleted_at'),
                'key'   => 'shop_id',
                'date'  => 'date',
            ],
            [
                'query' => fn () => DB::connection('aiku_no_sticky')->table('customers')->whereNull('deleted_at'),
                'key'   => 'shop_id',
                'date'  => 'registered_at',
            ],
        ];
    }

    public function handle(?int $shopId, ?string $from, ?string $to, bool $async = false): void
    {
        if (!$shopId) {
            return;
        }
        $shop = Shop::find($shopId);
        if (!$shop) {
            return;
        }

        if (!$from || !$to) {
            $dateRange = $this->getDateRange($shop->id);

            if (!$dateRange['from']) {
                return;
            }

            $from = $from ?? Carbon::parse($dateRange['from'])->toDateString();
            $to   = $to ?? Carbon::parse($dateRange['to'] ?? now())->toDateString();
        }

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            [$periodFrom, $periodTo] = TimeSeriesPeriodCalculator::expandWindowToFullPeriods($frequency, $from, $to);

            if ($async) {
                ProcessShopTimeSeriesRecords::dispatch($shop->id, $frequency, $periodFrom, $periodTo);
            } else {
                ProcessShopTimeSeriesRecords::run($shop->id, $frequency, $periodFrom, $periodTo);
            }
        }
    }
}
