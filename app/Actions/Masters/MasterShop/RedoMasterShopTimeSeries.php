<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Masters\MasterShop;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Masters\MasterShop;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class RedoMasterShopTimeSeries
{
    use WithHydrateCommand;

    public string $commandSignature = 'master-shops:redo_time_series {master-shops?*} {--s|slug=} {--f|frequency=all : The frequency for time series (all, daily, weekly, monthly, quarterly, yearly)} {--a|async : Run synchronously}';

    public function __construct()
    {
        $this->model = MasterShop::class;
    }

    public function handle(MasterShop $masterShop, array $frequencies, bool $async = true): void
    {
        $firstInvoicedDate = DB::table('invoices')->where('master_shop_id', $masterShop->id)->whereNull('deleted_at')->min('date');

        if ($firstInvoicedDate && ($firstInvoicedDate < $masterShop->created_at)) {
            $masterShop->update(['created_at' => $firstInvoicedDate]);
        }

        $from = $masterShop->created_at->toDateString();

        $to = DB::table('invoices')->where('master_shop_id', $masterShop->id)->whereNull('deleted_at')->max('date');

        if (!$to) {
            $to = now();
        }

        $to = Carbon::parse($to)->toDateString();

        if ($from != null && $to != null) {
            foreach ($frequencies as $frequency) {
                if ($async) {
                    ProcessMasterShopTimeSeriesRecords::dispatch($masterShop->id, $frequency, $from, $to)->onQueue('low-priority');
                } else {
                    ProcessMasterShopTimeSeriesRecords::run($masterShop->id, $frequency, $from, $to);
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
