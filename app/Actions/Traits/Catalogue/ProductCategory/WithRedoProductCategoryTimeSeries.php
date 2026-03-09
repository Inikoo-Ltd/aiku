<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 03 Jan 2026 23:41:42 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Catalogue\ProductCategory;

use App\Actions\Catalogue\ProductCategoryTimeSeries\ProcessProductCategoryTimeSeriesRecords;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

trait WithRedoProductCategoryTimeSeries
{
    public function getJobUniqueId(string $from, string $to): string
    {
        return "{$from}_{$to}";
    }

    public function handle(ProductCategory $productCategory, bool $async = false, ?string $from = null, ?string $to = null): void
    {
        if ($productCategory->state == ProductCategoryStateEnum::IN_PROCESS) {
            return;
        }

        if (!$from || !$to) {
            $firstInvoicedDate = DB::table('invoice_transactions')->where("{$this->categoryType->value}_id", $productCategory->id)->whereNull('deleted_at')->min('date');
            $lastInvoicedDate  = DB::table('invoice_transactions')->where("{$this->categoryType->value}_id", $productCategory->id)->whereNull('deleted_at')->max('date');

            if (!$firstInvoicedDate) {
                return;
            }

            $from = $from ?? Carbon::parse($firstInvoicedDate)->toDateString();
            $to   = $to ?? Carbon::parse($lastInvoicedDate ?? now())->toDateString();
        }

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            if ($async) {
                ProcessProductCategoryTimeSeriesRecords::dispatch($productCategory->id, $frequency, $from, $to)->onQueue('low-priority');
            } else {
                ProcessProductCategoryTimeSeriesRecords::run($productCategory->id, $frequency, $from, $to);
            }
        }
    }

    public function asJob(string $from, string $to): void
    {
        $tableName = (new $this->model())->getTable();
        $query     = DB::table($tableName)->select('id')->where('type', $this->categoryType->value)->orderBy('id', 'desc');

        $query->chunk(1000, function (\Illuminate\Support\Collection $modelsData) use ($from, $to) {
            foreach ($modelsData as $modelId) {
                $instance = ProductCategory::find($modelId->id);
                if (!$instance) {
                    continue;
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
        $query->where('type', $this->categoryType->value);
        $count = $query->count();
        $bar       = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        $query->chunk(1000, function (\Illuminate\Support\Collection $modelsData) use ($bar, $command) {
            foreach ($modelsData as $modelId) {
                $instance = ProductCategory::find($modelId->id);
                if (!$instance) {
                    $bar->advance();
                    continue;
                }

                try {
                    $this->handle($instance, (bool) $command->option('async'), $command->option('from'), $command->option('to'));
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
