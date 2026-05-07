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
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class RedoInvoiceCategoryTimeSeries implements ShouldBeUnique
{
    use WithHydrateCommand;

    public string $jobQueue         = 'default-long-slave';
    public string $commandSignature = 'invoice-categories:redo_time_series {--from= : Start date (Y-m-d)} {--to= : End date (Y-m-d)} {--a|async : Run asynchronously}';

    public function __construct()
    {
        $this->model = InvoiceCategory::class;
    }

    public function getJobUniqueId(string $from, string $to): string
    {
        return "{$from}_{$to}";
    }

    public function handle(InvoiceCategory $invoiceCategory, ?string $from = null, ?string $to = null, bool $async = false): void
    {
        if (!$from || !$to) {
            $firstInvoicedDate = DB::connection('aiku_no_sticky')->table('invoices')->where('invoice_category_id', $invoiceCategory->id)->whereNull('deleted_at')->min('date');
            $lastInvoicedDate  = DB::connection('aiku_no_sticky')->table('invoices')->where('invoice_category_id', $invoiceCategory->id)->whereNull('deleted_at')->max('date');

            if (!$firstInvoicedDate) {
                return;
            }

            $from = $from ?? Carbon::parse($firstInvoicedDate)->toDateString();
            $to   = $to ?? Carbon::parse($lastInvoicedDate ?? now())->toDateString();
        }

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            if ($async) {
                ProcessInvoiceCategoryTimeSeriesRecords::dispatch($invoiceCategory->id, $frequency, $from, $to);
            } else {
                ProcessInvoiceCategoryTimeSeriesRecords::run($invoiceCategory->id, $frequency, $from, $to);
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
