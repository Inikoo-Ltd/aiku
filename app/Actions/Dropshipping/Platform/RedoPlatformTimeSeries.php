<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Dropshipping\Platform;

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

    public string $jobQueue = 'default-long';
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

    public function handle(?int $platformId, ?string $from = null, ?string $to = null, bool $async = false): void
    {
        if (!$platformId) {
            return;
        }
        $platform = Platform::find($platformId);
        if (!$platform) {
            return;
        }

        $shopIds = DB::table('invoices')->where('platform_id', $platform->id)->whereNull('deleted_at')->whereNotNull('shop_id')->distinct()->pluck('shop_id');

        foreach ($shopIds as $shopId) {
            if (!$from || !$to) {
                $dates = collect([
                    DB::table('invoices')->where('platform_id', $platform->id)->where('shop_id', $shopId)->whereNull('deleted_at')->selectRaw('MIN(date) as min_date, MAX(date) as max_date')->first(),
                    DB::table('customer_sales_channels')->where('platform_id', $platform->id)->where('shop_id', $shopId)->selectRaw('MIN(created_at) as min_date, MAX(created_at) as max_date')->first(),
                    DB::table('portfolios')->where('platform_id', $platform->id)->where('shop_id', $shopId)->selectRaw('MIN(created_at) as min_date, MAX(created_at) as max_date')->first(),
                    DB::table('customer_clients')->where('platform_id', $platform->id)->where('shop_id', $shopId)->selectRaw('MIN(created_at) as min_date, MAX(created_at) as max_date')->first(),
                ]);

                $firstActivityDate = $dates->pluck('min_date')->filter()->min();
                $lastActivityDate  = $dates->pluck('max_date')->filter()->max();

                if (!$firstActivityDate) {
                    continue;
                }

                $resolvedFrom = Carbon::parse($firstActivityDate)->toDateString();
                $resolvedTo   = Carbon::parse($lastActivityDate ?? now())->toDateString();
            } else {
                $resolvedFrom = $from;
                $resolvedTo   = $to;
            }

            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                if ($async) {
                    ProcessPlatformTimeSeriesRecords::dispatch($platform->id, $shopId, $frequency, $resolvedFrom, $resolvedTo)->onQueue('low-priority');
                } else {
                    ProcessPlatformTimeSeriesRecords::run($platform->id, $shopId, $frequency, $resolvedFrom, $resolvedTo);
                }
            }
        }
    }

}
