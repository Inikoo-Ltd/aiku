<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 03 Jan 2026 23:41:42 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Catalogue\ProductCategory;

use App\Actions\Catalogue\ProductCategoryTimeSeries\ProcessProductCategoryTimeSeriesRecords;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

trait WithRedoProductCategoryTimeSeries
{
    public function handle(ProductCategory $productCategory, array $frequencies, Command $command = null, bool $async = true): void
    {
        $type = ProductCategoryTypeEnum::from($this->restriction);

        if ($productCategory->type !== $type) {
            $command?->error("Can only process {$type->value}s, $productCategory->name is a $productCategory->type");

            return;
        }


        $from = null;

        $firstInvoicedDate = DB::table('invoice_transactions')->where("{$this->restriction}_id", $productCategory->id)->min('date');


        if ($firstInvoicedDate && ($firstInvoicedDate < $productCategory->created_at)) {
            $productCategory->update(['created_at' => $firstInvoicedDate]);
        }


        if ($productCategory->created_at) {
            $from = $productCategory->created_at->toDateString();
        }

        if ($productCategory->state == ProductCategoryStateEnum::IN_PROCESS) {
            return;
        }

        if ($productCategory->state == ProductCategoryStateEnum::ACTIVE || $productCategory->state == ProductCategoryStateEnum::DISCONTINUING) {
            $to = now()->toDateString();
        } elseif ($productCategory->state == ProductCategoryStateEnum::DISCONTINUED) {
            $to = $productCategory->discontinued_at;
            $lastInvoicedDate = DB::table('invoice_transactions')
                ->where("{$this->restriction}_id", $productCategory->id)
                ->max('date');
            if ($lastInvoicedDate && (!$to || $lastInvoicedDate > $to)) {
                $to = $lastInvoicedDate;
                $productCategory->update(['discontinued_at' => $to]);
            }
            $to = $to->toDateString();
        } else {
            $to = DB::table('invoice_transactions')
                ->where("{$this->restriction}_id", $productCategory->id)
                ->max('date');
            if (!$to) {
                return;
            }
            $to = $to->toDateString();
        }


        if ($from != null && $to != null) {
            foreach ($frequencies as $frequency) {
                if ($async) {
                    ProcessProductCategoryTimeSeriesRecords::dispatch($productCategory->id, $frequency, $from, $to)->onQueue('low-priority');
                } else {
                    ProcessProductCategoryTimeSeriesRecords::run($productCategory->id, $frequency, $from, $to);
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
            function (Collection $modelsData) use ($bar, $command, $frequencies) {
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
