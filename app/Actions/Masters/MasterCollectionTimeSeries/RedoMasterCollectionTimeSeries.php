<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 Jan 2026 02:52:11 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterCollectionTimeSeries;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Masters\MasterCollection;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class RedoMasterCollectionTimeSeries
{
    use WithHydrateCommand;

    public string $commandSignature = 'master_collections:redo_time_series {--s|slug=} {--f|frequency=all : The frequency for time series (all, daily, weekly, monthly, quarterly, yearly)} {--a|async : Run synchronously}';
    public string $jobQueue = 'default-long';

    public function __construct()
    {
        $this->model = MasterCollection::class;
    }

    public function handle(MasterCollection $masterCollection, array $frequencies, bool $async = true): void
    {
        $assetsIDs = null;


        $from = $masterCollection->created_at->toDateString();

        if ($masterCollection->status) {
            $to = now()->toDateString();
        } else {
            if (!$masterCollection->inactivated_at) {
                if ($assetsIDs == null) {
                    $assetsIDs = $masterCollection->masterProducts()->pluck('master_asset_id')->unique()->toArray();
                }
                $lastDate = DB::table('invoice_transactions')->whereIn('master_asset_id', $assetsIDs)->max('date');
                if (!$lastDate) {
                    $lastDate = now();
                }

                $masterCollection->update([
                    'inactivated_at' => $lastDate
                ]);
            }
            $to = $masterCollection->inactivated_at->toDateString();
        }


        if ($from != null && $to != null) {
            foreach ($frequencies as $frequency) {
                if ($async) {
                    ProcessMasterCollectionTimeSeriesRecords::dispatch($masterCollection->id, $frequency, $from, $to)->onQueue('low-priority');
                } else {
                    ProcessMasterCollectionTimeSeriesRecords::run($masterCollection->id, $frequency, $from, $to);
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
