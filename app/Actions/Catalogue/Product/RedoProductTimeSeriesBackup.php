<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\Product;

use App\Actions\Catalogue\AssetTimeSeries\ProcessAssetTimeSeriesRecords;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Catalogue\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class RedoProductTimeSeriesBackup
{
    use WithHydrateCommand;

    public string $commandSignature = 'products:redo_time_series_backup {--from= : Start date (Y-m-d)} {--to= : End date (Y-m-d)} {--a|async : Run asynchronously}';

    public function __construct()
    {
        $this->model = Product::class;
    }

    public function handle(Product $product, bool $async = false, ?string $from = null, ?string $to = null): void
    {
        if ($product->state == ProductStateEnum::IN_PROCESS) {
            return;
        }

        if (!$from || !$to) {
            $firstInvoicedDate = DB::table('invoice_transactions')->where('asset_id', $product->asset_id)->whereNull('deleted_at')->min('date');
            $lastInvoicedDate  = DB::table('invoice_transactions')->where('asset_id', $product->asset_id)->whereNull('deleted_at')->max('date');

            if (!$firstInvoicedDate) {
                return;
            }

            $from = $from ?? Carbon::parse($firstInvoicedDate)->toDateString();
            $to   = $to ?? Carbon::parse($lastInvoicedDate ?? now())->toDateString();
        }

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            if ($async) {
                ProcessAssetTimeSeriesRecords::dispatch($product->asset_id, $frequency, $from, $to)->onQueue('low-priority');
            } else {
                ProcessAssetTimeSeriesRecords::run($product->asset_id, $frequency, $from, $to);
            }
        }
    }

    public function asJob(string $from, string $to): void
    {
        $tableName = (new $this->model())->getTable();
        $query     = DB::table($tableName)->select('id')->orderBy('id', 'desc');

        $query->chunk(1000, function (\Illuminate\Support\Collection $modelsData) use ($from, $to) {
            foreach ($modelsData as $modelId) {
                $model = (new $this->model());
                if ($this->hasSoftDeletes($model)) {
                    $instance = $model->withTrashed()->find($modelId->id);
                } else {
                    $instance = $model->find($modelId->id);
                }

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
                    $this->handle($instance, $command->option('async'), $command->option('from'), $command->option('to'));
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
