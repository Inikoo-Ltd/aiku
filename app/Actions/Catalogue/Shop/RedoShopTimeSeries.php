<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\Shop;

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

    public string $jobQueue = 'default-long';
    public string $commandSignature = 'shops:redo_time_series {--from= : Start date (Y-m-d)} {--to= : End date (Y-m-d)} {--a|async : Run asynchronously}';

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
            $dates = collect([
                DB::table('invoices')->where('shop_id', $shop->id)->whereNull('deleted_at')->selectRaw('MIN(date) as min_date, MAX(date) as max_date')->first(),
                DB::table('customers')->where('shop_id', $shop->id)->whereNull('deleted_at')->selectRaw('MIN(registered_at) as min_date, MAX(registered_at) as max_date')->first(),
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
                ProcessShopTimeSeriesRecords::dispatch($shop->id, $frequency, $from, $to)->onQueue('sales_slave_historic');
            } else {
                ProcessShopTimeSeriesRecords::run($shop->id, $frequency, $from, $to);
            }
        }
    }


}
