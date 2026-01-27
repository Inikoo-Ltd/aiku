<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 Jan 2026 01:35:33 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategoryTimeSeries;

use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

trait WithRedoMasterProductCategoryTimeSeries
{
    public function handle(MasterProductCategory $masterProductCategory, array $frequencies, ?Command $command = null, bool $async = true): void
    {
        $type = MasterProductCategoryTypeEnum::from($this->restriction);

        if ($masterProductCategory->type !== $type) {
            $command?->error("Can only process {$type->value}s, $masterProductCategory->name is a $masterProductCategory->type");

            return;
        }


        $from = null;

        $firstInvoicedDate = DB::table('invoice_transactions')->where("master_{$this->restriction}_id", $masterProductCategory->id)->min('date');


        if ($firstInvoicedDate && ($firstInvoicedDate < $masterProductCategory->created_at)) {
            $masterProductCategory->update(['created_at' => $firstInvoicedDate]);
        }


        if ($masterProductCategory->created_at) {
            $from = $masterProductCategory->created_at->toDateString();
        }


        if ($masterProductCategory->status) {
            $to = now()->toDateString();
        } else {
            $to = $masterProductCategory->discontinued_at;

            $lastInvoicedDate = DB::table('invoice_transactions')
                ->where("master_{$this->restriction}_id", $masterProductCategory->id)
                ->max('date');

            if (!$to && !$lastInvoicedDate) {
                return;
            }

            if ($lastInvoicedDate) {
                $lastInvoicedDate = Carbon::parse($lastInvoicedDate);
            }

            if ($lastInvoicedDate && (!$to || $lastInvoicedDate->greaterThan($to))) {
                $to = $lastInvoicedDate;
                $masterProductCategory->update(['discontinued_at' => $to]);
            }

            if (!$to) {
                return;
            }

            $to = $to->toDateString();
        }


        if ($from != null && $to != null) {
            foreach ($frequencies as $frequency) {
                if ($async) {
                    ProcessMasterProductCategoryTimeSeriesRecords::dispatch($masterProductCategory->id, $frequency, $from, $to)->onQueue('low-priority');
                } else {
                    ProcessMasterProductCategoryTimeSeriesRecords::run($masterProductCategory->id, $frequency, $from, $to);
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
