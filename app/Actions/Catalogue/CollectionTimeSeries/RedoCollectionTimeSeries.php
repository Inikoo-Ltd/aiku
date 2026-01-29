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
use Illuminate\Support\Facades\DB;
use Throwable;

class RedoCollectionTimeSeries
{
    use WithHydrateCommand;

    public string $commandSignature = 'collections:redo_time_series {organisations?*} {--S|shop= shop slug} {--s|slug=} {--f|frequency=all : The frequency for time series (all, daily, weekly, monthly, quarterly, yearly)} {--a|async : Run synchronously}';

    public function __construct()
    {
        $this->model = Collection::class;
    }

    public function handle(Collection $collection, array $frequencies, bool $async = true): void
    {
        $assetsIDs = null;

        if ($collection->state == CollectionStateEnum::IN_PROCESS) {
            return;
        }

        if ($collection->source_id) {
            $assetsIDs = $collection->products->pluck('asset_id')->unique()->toArray();
            $firstInvoicedDate = DB::table('invoice_transactions')->whereIn('asset_id', $assetsIDs)->whereNull('deleted_at')->min('date');

            if ($firstInvoicedDate && ($firstInvoicedDate < $collection->created_at)) {
                $collection->update(['created_at' => $firstInvoicedDate]);
            }
        }

        $from = $collection->created_at->toDateString();

        if ($collection->state == CollectionStateEnum::ACTIVE) {
            $to = now()->toDateString();
        } else {
            if (!$collection->inactivated_at) {
                if ($assetsIDs == null) {
                    $assetsIDs = $collection->products->pluck('asset_id')->unique()->toArray();
                }

                $lastDate = DB::table('invoice_transactions')->whereIn('asset_id', $assetsIDs)->whereNull('deleted_at')->max('date');

                if (!$lastDate) {
                    $lastDate = now();
                }

                $collection->update([
                    'inactivated_at' => $lastDate
                ]);
            }

            $to = $collection->inactivated_at->toDateString();
        }


        if ($from != null && $to != null) {
            foreach ($frequencies as $frequency) {
                if ($async) {
                    ProcessCollectionTimeSeriesRecords::dispatch($collection->id, $frequency, $from, $to)->onQueue('low-priority');
                } else {
                    ProcessCollectionTimeSeriesRecords::run($collection->id, $frequency, $from, $to);
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
                    TimeSeriesFrequencyEnum::from($frequencyOption)
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
                        $this->handle($instance, $frequencies, $command->option('async'));
                    } catch (Throwable $e) {
                        $command->error($e->getMessage());
                    }
                    $bar->advance();
                }
            }
        );

        $bar->finish();
        $command->info("");

        return 0;
    }
}
