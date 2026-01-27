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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class RedoPlatformTimeSeries
{
    use WithHydrateCommand;

    public string $commandSignature = 'platforms:redo_time_series {organisations?*} {--P|platform= platform slug} {--s|slug=} {--f|frequency=all : The frequency for time series (all, daily, weekly, monthly, quarterly, yearly)} {--a|async : Run synchronously}';
    public string $jobQueue = 'default-long';

    public function __construct()
    {
        $this->model = Platform::class;
    }

    public function handle(Platform $platform, array $frequencies, Command $command = null, bool $async = true): void
    {
        $shopIds = DB::table('invoices')
            ->where('platform_id', $platform->id)
            ->whereNotNull('shop_id')
            ->distinct()
            ->pluck('shop_id');

        foreach ($shopIds as $shopId) {
            $dates = collect([
                DB::table('invoices')
                    ->where('platform_id', $platform->id)
                    ->where('shop_id', $shopId)
                    ->selectRaw('MIN(date) as min_date, MAX(date) as max_date')
                    ->first(),

                DB::table('customer_sales_channels')
                    ->where('platform_id', $platform->id)
                    ->where('shop_id', $shopId)
                    ->selectRaw('MIN(created_at) as min_date, MAX(created_at) as max_date')
                    ->first(),

                DB::table('portfolios')
                    ->where('platform_id', $platform->id)
                    ->where('shop_id', $shopId)
                    ->selectRaw('MIN(created_at) as min_date, MAX(created_at) as max_date')
                    ->first(),

                DB::table('customer_clients')
                    ->where('platform_id', $platform->id)
                    ->where('shop_id', $shopId)
                    ->selectRaw('MIN(created_at) as min_date, MAX(created_at) as max_date')
                    ->first(),
            ]);

            $from = $dates->pluck('min_date')->filter()->min();
            $to   = $dates->pluck('max_date')->filter()->max();

            if (! $from || ! $to) {
                continue;
            }

            $from = Carbon::parse($from)->toDateString();
            $to   = Carbon::parse($to)->toDateString();

            if ($from != null && $to != null) {
                foreach ($frequencies as $frequency) {
                    if ($async) {
                        ProcessPlatformTimeSeriesRecords::dispatch($platform->id, $shopId, $frequency, $from, $to)->onQueue('low-priority');
                    } else {
                        ProcessPlatformTimeSeriesRecords::run($platform->id, $shopId, $frequency, $from, $to);
                    }
                }
            }
        }
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

        try {
            $frequencyOption = $command->option('frequency');

            if ($frequencyOption === 'all') {
                $frequencies = TimeSeriesFrequencyEnum::cases();
            } else {
                $frequencies = [
                    TimeSeriesFrequencyEnum::from($frequencyOption),
                ];
            }
        } catch (Throwable $e) {
            $command->error($e->getMessage());

            return 1;
        }

        $query->chunk(
            1000,
            function (\Illuminate\Support\Collection $modelsData) use ($bar, $command, $frequencies) {
                foreach ($modelsData as $modelId) {
                    if ($this->modelAsHandleArg) {
                        $model = (new $this->model());
                        if ($this->hasSoftDeletes($model)) {
                            $instance = $model->withTrashed()->find($modelId->id);
                        } else {
                            $instance = $model->find($modelId->id);
                        }
                    } else {
                        $instance = $modelId->id;
                    }

                    try {
                        $this->handle($instance, $frequencies, $command, $command->option('async'));
                    } catch (Throwable $e) {
                        $command->error($e->getMessage());
                    }

                    $bar->advance();
                }
            }
        );

        $bar->finish();
        $command->info('');

        return 0;
    }
}
