<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Dropshipping\Platform;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Dropshipping\Platform;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class RedoPlatformTimeSeries implements ShouldBeUnique
{
    use WithHydrateCommand;

    public string $jobQueue         = 'default-long';
    public string $commandSignature = 'platforms:redo_time_series {--from= : Start date (Y-m-d)} {--to= : End date (Y-m-d)} {--a|async : Run asynchronously}';

    public function __construct()
    {
        $this->model = Platform::class;
    }

    public function getJobUniqueId(string $from, string $to): string
    {
        return "{$from}_{$to}";
    }

    public function handle(Platform $platform, bool $async = false, ?string $from = null, ?string $to = null): void
    {
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

    public function asJob(string $from, string $to): void
    {
        $tableName = (new $this->model())->getTable();
        $query     = DB::table($tableName)->select('id')->orderBy('id', 'desc');

        $query->chunk(1000, function (\Illuminate\Support\Collection $modelsData) use ($from, $to) {
            foreach ($modelsData as $modelId) {
                $model    = (new $this->model());
                $instance = $this->hasSoftDeletes($model)
                    ? $model->withTrashed()->find($modelId->id)
                    : $model->find($modelId->id);

                try {
                    $this->handle($instance, false, $from, $to);
                } catch (Throwable $e) {
                    report($e);
                }
            }
        });
    }

    public function asCommand(Command $command): int
    {
        $command->info($command->getName());
        $tableName = (new $this->model())->getTable();
        $query     = $this->prepareQuery($tableName, $command);
        $count     = $query->count();
        $bar       = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        $query->chunk(1000, function (\Illuminate\Support\Collection $modelsData) use ($bar, $command) {
            foreach ($modelsData as $modelId) {
                $model    = (new $this->model());
                $instance = $this->hasSoftDeletes($model)
                    ? $model->withTrashed()->find($modelId->id)
                    : $model->find($modelId->id);

                try {
                    $this->handle($instance, (bool) $command->option('async'), $command->option('from'), $command->option('to'));
                } catch (Throwable $e) {
                    $command->error($e->getMessage());
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $command->info('');

        return 0;
    }
}
