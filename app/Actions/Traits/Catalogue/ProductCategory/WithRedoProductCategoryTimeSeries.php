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

    public function handle(ProductCategory $productCategory, bool $async = true): void
    {
        if ($productCategory->state == ProductCategoryStateEnum::IN_PROCESS) {
            return;
        }

        $firstInvoicedDate = DB::table('invoice_transactions')->where("{$this->restriction->value}_id", $productCategory->id)->whereNull('deleted_at')->min('date');
        $lastInvoicedDate  = DB::table('invoice_transactions')->where("{$this->restriction->value}_id", $productCategory->id)->whereNull('deleted_at')->max('date');

        if (!$firstInvoicedDate) {
            return;
        }

        $from = Carbon::parse($firstInvoicedDate)->toDateString();
        $to   = Carbon::parse($lastInvoicedDate ?? now())->toDateString();

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
        ProductCategory::where('type', $this->restriction)->get()->each(function (ProductCategory $productCategory) use ($from, $to) {
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                ProcessProductCategoryTimeSeriesRecords::run($productCategory->id, $frequency, $from, $to);
            }
        });
    }

    public function asCommand(Command $command): int
    {
        $command->info($command->getName());

        $async = (bool) $command->option('async');

        $productCategories = ProductCategory::where('type', $this->restriction)->get();

        $bar = $command->getOutput()->createProgressBar($productCategories->count());
        $bar->setFormat('debug');
        $bar->start();

        foreach ($productCategories as $productCategory) {
            try {
                $this->handle($productCategory, $async);
            } catch (Throwable $e) {
                $command->error($e->getMessage());
            }
            $bar->advance();
        }

        $bar->finish();
        $command->info('');

        return 0;
    }
}
