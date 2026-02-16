<?php

/*
 * Author: Steven Wicca <stewicalf@gmail.com>
 * Created: Thu, 13 Feb 2026 16:32:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Accounting\IntrastatImportTimeSeries;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class RedoIntrastatImportTimeSeries
{
    use WithHydrateCommand;

    public string $commandSignature = 'intrastat-import:redo_time_series {organisations?*} {--s|slug=} {--f|frequency=all : The frequency for time series (all, daily, weekly, monthly, quarterly, yearly)} {--a|async : Run synchronously}';

    public function __construct()
    {
        $this->model = Organisation::class;
    }

    public function handle(Organisation $organisation, array $frequencies, bool $async = true): void
    {
        $dates = DB::table('stock_deliveries')
            ->where('organisation_id', $organisation->id)
            ->whereNotNull('checked_at')
            ->selectRaw('MIN(checked_at) as min_date, MAX(checked_at) as max_date')
            ->first();

        if (!$dates || !$dates->min_date || !$dates->max_date) {
            return;
        }

        $from = Carbon::parse($dates->min_date)->toDateString();
        $to = Carbon::parse($dates->max_date)->toDateString();

        foreach ($frequencies as $frequency) {
            if ($async) {
                ProcessIntrastatImportTimeSeriesRecords::dispatch($organisation->id, $frequency, $from, $to)->onQueue('low-priority');
            } else {
                ProcessIntrastatImportTimeSeriesRecords::run($organisation->id, $frequency, $from, $to);
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
                        $this->handle($instance, $frequencies, $command->option('async'));
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
