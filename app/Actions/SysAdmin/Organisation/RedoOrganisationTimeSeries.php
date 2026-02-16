<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\SysAdmin\Organisation;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class RedoOrganisationTimeSeries
{
    use WithHydrateCommand;

    public string $commandSignature = 'organisations:redo_time_series {organisations?*} {--s|slug=} {--f|frequency=all : The frequency for time series (all, daily, weekly, monthly, quarterly, yearly)} {--a|async : Run synchronously}';

    public function __construct()
    {
        $this->model = Organisation::class;
    }

    public function handle(Organisation $organisation, array $frequencies, bool $async = true): void
    {
        $firstInvoicedDate = DB::table('invoices')->where('organisation_id', $organisation->id)->whereNull('deleted_at')->min('date');
        $firstOrderDate = DB::table('orders')->where('organisation_id', $organisation->id)->whereNull('deleted_at')->min('created_at');
        $firstDeliveryNoteDate = DB::table('delivery_notes')->where('organisation_id', $organisation->id)->whereNull('deleted_at')->min('date');
        $firstCustomerRegistrationDate = DB::table('customers')->where('organisation_id', $organisation->id)->whereNull('deleted_at')->min('registered_at');

        $firstActivityDate = collect([
            $firstInvoicedDate,
            $firstOrderDate,
            $firstDeliveryNoteDate,
            $firstCustomerRegistrationDate,
        ])->filter()->min();

        if ($firstActivityDate && ($firstActivityDate < $organisation->created_at)) {
            $organisation->update(['created_at' => $firstActivityDate]);
        }

        $from = $organisation->created_at->toDateString();

        $to = now()->toDateString();

        if ($from != null && $to != null) {
            foreach ($frequencies as $frequency) {
                if ($async) {
                    ProcessOrganisationTimeSeriesRecords::dispatch($organisation->id, $frequency, $from, $to)->onQueue('low-priority');
                } else {
                    ProcessOrganisationTimeSeriesRecords::run($organisation->id, $frequency, $from, $to);
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
