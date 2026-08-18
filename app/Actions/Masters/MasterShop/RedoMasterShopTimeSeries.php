<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Masters\MasterShop;

use App\Helpers\TimeSeriesPeriodCalculator;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Actions\Traits\WithTimeSeriesRedo;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Masters\MasterShop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RedoMasterShopTimeSeries implements ShouldBeUnique
{
    use WithHydrateCommand;
    use WithTimeSeriesRedo {
        WithTimeSeriesRedo::asCommand insteadof WithHydrateCommand;
    }

    public string $jobQueue = 'long-low-priority';
    public string $commandSignature = 'master-shops:redo_time_series {--from= : Start date (Y-m-d)} {--to= : End date (Y-m-d)} {--a|async : Run asynchronously}';

    public function __construct()
    {
        $this->model = MasterShop::class;
    }

    public function getJobUniqueId(?int $masterShopId, ?string $from, ?string $to): string
    {
        if ($masterShopId === null) {
            return 'empty'.'_'.$from.'_'.$to;
        }

        return $masterShopId.'_'.$from.'_'.$to;
    }

    protected function dateRangeSources(): array
    {
        return [
            [
                'query' => fn () => DB::connection('aiku_no_sticky')->table('invoices')->whereNull('deleted_at'),
                'key'   => 'master_shop_id',
                'date'  => 'date',
            ],
            [
                'query' => fn () => DB::connection('aiku_no_sticky')->table('customers')->whereNull('deleted_at'),
                'key'   => 'master_shop_id',
                'date'  => 'registered_at',
            ],
        ];
    }

    public function handle(?int $masterShopId, ?string $from = null, ?string $to = null, bool $async = false): void
    {
        if (!$masterShopId) {
            return;
        }
        $masterShop = MasterShop::find($masterShopId);
        if (!$masterShop) {
            return;
        }

        if (!$from || !$to) {
            $dateRange = $this->getDateRange($masterShop->id);

            if (!$dateRange['from']) {
                return;
            }

            $from = $from ?? Carbon::parse($dateRange['from'])->toDateString();
            $to   = $to ?? Carbon::parse($dateRange['to'] ?? now())->toDateString();
        }

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            [$periodFrom, $periodTo] = TimeSeriesPeriodCalculator::expandWindowToFullPeriods($frequency, $from, $to);

            if ($async) {
                ProcessMasterShopTimeSeriesRecords::dispatch($masterShop->id, $frequency, $periodFrom, $periodTo)->onQueue('sales_slave');
            } else {
                ProcessMasterShopTimeSeriesRecords::run($masterShop->id, $frequency, $periodFrom, $periodTo);
            }
        }
    }


}
