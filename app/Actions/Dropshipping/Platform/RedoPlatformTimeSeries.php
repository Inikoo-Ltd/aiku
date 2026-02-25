<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Dropshipping\Platform;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Dropshipping\Platform;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class RedoPlatformTimeSeries implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'default-long';
    public string $commandSignature = 'platforms:redo_time_series {--a|async : Run asynchronously}';

    public function getJobUniqueId(string $from, string $to): string
    {
        return "{$from}_{$to}";
    }

    public function handle(Platform $platform, bool $async = false): void
    {
        $shopIds = DB::table('invoices')->where('platform_id', $platform->id)->whereNull('deleted_at')->whereNotNull('shop_id')->distinct()->pluck('shop_id');

        foreach ($shopIds as $shopId) {
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

            $from = Carbon::parse($firstActivityDate)->toDateString();
            $to   = Carbon::parse($lastActivityDate ?? now())->toDateString();

            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                if ($async) {
                    ProcessPlatformTimeSeriesRecords::dispatch($platform->id, $shopId, $frequency, $from, $to)->onQueue('low-priority');
                } else {
                    ProcessPlatformTimeSeriesRecords::run($platform->id, $shopId, $frequency, $from, $to);
                }
            }
        }
    }

    public function asJob(string $from, string $to): void
    {
        Platform::all()->each(function (Platform $platform) use ($from, $to) {
            $shopIds = DB::table('invoices')->where('platform_id', $platform->id)->whereNull('deleted_at')->whereNotNull('shop_id')->distinct()->pluck('shop_id');

            foreach ($shopIds as $shopId) {
                foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                    ProcessPlatformTimeSeriesRecords::run($platform->id, $shopId, $frequency, $from, $to);
                }
            }
        });
    }

    public function asCommand(Command $command): int
    {
        $command->info($command->getName());

        $async = (bool) $command->option('async');

        $platforms = Platform::all();

        $bar = $command->getOutput()->createProgressBar($platforms->count());
        $bar->setFormat('debug');
        $bar->start();

        foreach ($platforms as $platform) {
            try {
                $this->handle($platform, $async);
            } catch (Throwable $e) {
                $command->error($e->getMessage());
            }
            $bar->advance();
        }

        $bar->finish();
        $command->info('');

        return 0;
    }
}
