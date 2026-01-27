<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 Jan 2026 00:54:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAssetTimeSeries;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Masters\MasterAsset;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class RedoMasterAssetTimeSeries
{
    use WithHydrateCommand;


    public string $commandSignature = 'master_assets:redo_time_series {--s|slug=} {--f|frequency=all : The frequency for time series (all, daily, weekly, monthly, quarterly, yearly)} {--a|async : Run synchronously}';
    public string $jobQueue = 'default-long';

    public function __construct()
    {
        $this->model = MasterAsset::class;
    }

    public function handle(MasterAsset $masterAsset, array $frequencies, bool $async = true): void
    {
        $from = null;
        $firstInvoicedDate = DB::table('invoice_transactions')->where('master_asset_id', $masterAsset->id)->min('date');

        if ($firstInvoicedDate && ($firstInvoicedDate < $masterAsset->created_at)) {
            $masterAsset->update(['created_at' => $firstInvoicedDate]);
        }


        if ($masterAsset->created_at) {
            $from = $masterAsset->created_at->toDateString();
        }


        if ($masterAsset->status) {
            $to = now()->toDateString();
        } else {
            $to = $masterAsset->discontinued_at;

            $lastInvoicedDate = DB::table('invoice_transactions')
                ->where('master_asset_id', $masterAsset->id)
                ->max('date');

            if (!$to && !$lastInvoicedDate) {
                return;
            }

            if ($lastInvoicedDate) {
                $lastInvoicedDate = Carbon::parse($lastInvoicedDate);
            }

            if ($lastInvoicedDate && (!$to || $lastInvoicedDate->greaterThan($to))) {
                $to = $lastInvoicedDate;
                $masterAsset->update(['discontinued_at' => $to]);
            }

            if (!$to) {
                return;
            }

            $to = $to->toDateString();
        }

        if ($from != null && $to != null) {
            foreach ($frequencies as $frequency) {
                if ($async) {
                    ProcessMasterAssetTimeSeriesRecords::dispatch($masterAsset->id, $frequency, $from, $to)->onQueue('low-priority');
                } else {
                    ProcessMasterAssetTimeSeriesRecords::run($masterAsset->id, $frequency, $from, $to);
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
