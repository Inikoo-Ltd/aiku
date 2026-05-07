<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\CollectionTimeSeries;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Enums\Catalogue\Collection\CollectionStateEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Catalogue\Collection;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class RedoCollectionTimeSeries implements ShouldBeUnique
{
    use WithHydrateCommand;

    public string $jobQueue         = 'default-long-slave';
    public string $commandSignature = 'collections:redo_time_series {--from= : Start date (Y-m-d)} {--to= : End date (Y-m-d)} {--a|async : Run asynchronously}';

    public function __construct()
    {
        $this->model = Collection::class;
    }

    public function getJobUniqueId(string $from, string $to): string
    {
        return "{$from}_{$to}";
    }

    public function handle(Collection $collection, ?string $from = null, ?string $to = null, bool $async = false): void
    {
        if ($collection->state == CollectionStateEnum::IN_PROCESS || !$collection->source_id) {
            return;
        }

        if (!$from || !$to) {
            $assetsIDs = $collection->products->pluck('asset_id')->unique()->toArray();

            $firstInvoicedDate = DB::connection('aiku_no_sticky')->table('invoice_transactions')->whereIn('asset_id', $assetsIDs)->whereNull('deleted_at')->min('date');
            $lastInvoicedDate  = DB::connection('aiku_no_sticky')->table('invoice_transactions')->whereIn('asset_id', $assetsIDs)->whereNull('deleted_at')->max('date');

            if (!$firstInvoicedDate) {
                return;
            }

            $from = $from ?? Carbon::parse($firstInvoicedDate)->toDateString();
            $to   = $to ?? Carbon::parse($lastInvoicedDate ?? now())->toDateString();
        }

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            if ($async) {
                ProcessCollectionTimeSeriesRecords::dispatch($collection->id, $frequency, $from, $to);
            } else {
                ProcessCollectionTimeSeriesRecords::run($collection->id, $frequency, $from, $to);
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
                    $this->handle($instance, $from, $to, false);
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
                    $this->handle($instance, $command->option('from'), $command->option('to'), (bool) $command->option('async'));
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
