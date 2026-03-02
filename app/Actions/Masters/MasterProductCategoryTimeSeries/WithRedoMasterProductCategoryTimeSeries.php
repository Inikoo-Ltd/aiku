<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 Jan 2026 01:35:33 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategoryTimeSeries;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

trait WithRedoMasterProductCategoryTimeSeries
{
    public function getJobUniqueId(string $from, string $to): string
    {
        return "{$from}_{$to}";
    }

    public function handle(MasterProductCategory $masterProductCategory, bool $async = false): void
    {
        $firstInvoicedDate = DB::table('invoice_transactions')->where("master_{$this->restriction->value}_id", $masterProductCategory->id)->whereNull('deleted_at')->min('date');
        $lastInvoicedDate  = DB::table('invoice_transactions')->where("master_{$this->restriction->value}_id", $masterProductCategory->id)->whereNull('deleted_at')->max('date');

        if (!$firstInvoicedDate) {
            return;
        }

        $from = Carbon::parse($firstInvoicedDate)->toDateString();
        $to   = Carbon::parse($lastInvoicedDate ?? now())->toDateString();

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            if ($async) {
                ProcessMasterProductCategoryTimeSeriesRecords::dispatch($masterProductCategory->id, $frequency, $from, $to)->onQueue('low-priority');
            } else {
                ProcessMasterProductCategoryTimeSeriesRecords::run($masterProductCategory->id, $frequency, $from, $to);
            }
        }
    }

    public function asJob(string $from, string $to): void
    {
        MasterProductCategory::where('type', $this->restriction)->get()->each(function (MasterProductCategory $masterProductCategory) use ($from, $to) {
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                ProcessMasterProductCategoryTimeSeriesRecords::run($masterProductCategory->id, $frequency, $from, $to);
            }
        });
    }

    public function asCommand(Command $command): int
    {
        $command->info($command->getName());

        $async = (bool) $command->option('async');

        $masterProductCategories = MasterProductCategory::where('type', $this->restriction)->get();

        $bar = $command->getOutput()->createProgressBar($masterProductCategories->count());
        $bar->setFormat('debug');
        $bar->start();

        foreach ($masterProductCategories as $masterProductCategory) {
            try {
                $this->handle($masterProductCategory, $async);
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
