<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Masters\MasterShop;

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

    public string $jobQueue = 'default-long-slave';
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
            $dates = collect([
                DB::connection('aiku_no_sticky')->table('invoices')->where('master_shop_id', $masterShop->id)->whereNull('deleted_at')->selectRaw('MIN(date) as min_date, MAX(date) as max_date')->first(),
                DB::connection('aiku_no_sticky')->table('customers')->where('master_shop_id', $masterShop->id)->whereNull('deleted_at')->selectRaw('MIN(registered_at) as min_date, MAX(registered_at) as max_date')->first(),
            ]);

            $firstActivityDate = $dates->pluck('min_date')->filter()->min();
            $lastActivityDate  = $dates->pluck('max_date')->filter()->max();

            if (!$firstActivityDate) {
                return;
            }

            $from = $from ?? Carbon::parse($firstActivityDate)->toDateString();
            $to   = $to ?? Carbon::parse($lastActivityDate ?? now())->toDateString();
        }

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            if ($async) {
                ProcessMasterShopTimeSeriesRecords::dispatch($masterShop->id, $frequency, $from, $to)->onQueue('sales_slave');
            } else {
                ProcessMasterShopTimeSeriesRecords::run($masterShop->id, $frequency, $from, $to);
            }
        }
    }


}
