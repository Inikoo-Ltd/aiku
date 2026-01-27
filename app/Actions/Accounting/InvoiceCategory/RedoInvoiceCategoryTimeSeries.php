<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Accounting\InvoiceCategory;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Accounting\InvoiceCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class RedoInvoiceCategoryTimeSeries
{
    use WithHydrateCommand;

    public string $commandSignature = 'invoice-categories:redo_time_series {organisations?*} {--S|shop= shop slug} {--s|slug=} {--f|frequency=all : The frequency for time series (all, daily, weekly, monthly, quarterly, yearly)} {--a|async : Run synchronously}';
    public string $jobQueue = 'default-long';

    public function __construct()
    {
        $this->model = InvoiceCategory::class;
    }

    public function handle(InvoiceCategory $invoiceCategory, array $frequencies, ?Command $command = null, bool $async = true): void
    {
        $firstInvoicedDate = DB::table('invoices')->where('invoice_category_id', $invoiceCategory->id)->min('date');

        if ($firstInvoicedDate && ($firstInvoicedDate < $invoiceCategory->created_at)) {
            $invoiceCategory->update(['created_at' => $firstInvoicedDate]);
        }

        $from = $invoiceCategory->created_at->toDateString();

        $to = DB::table('invoices')->where('invoice_category_id', $invoiceCategory->id)->max('date');
        if (!$to) {
            $to = now();
        }
        $to = Carbon::parse($to)->toDateString();

        if ($from != null && $to != null) {
            foreach ($frequencies as $frequency) {
                if ($async) {
                    ProcessInvoiceCategoryTimeSeriesRecords::dispatch($invoiceCategory->id, $frequency, $from, $to)->onQueue('low-priority');
                } else {
                    ProcessInvoiceCategoryTimeSeriesRecords::run($invoiceCategory->id, $frequency, $from, $to);
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
                        $this->handle($instance, $frequencies, $command, $command->option('async'));
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
