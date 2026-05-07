<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 03 Jan 2026 23:41:42 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Catalogue\ProductCategory;

use App\Actions\Catalogue\ProductCategoryTimeSeries\ProcessProductCategoryTimeSeriesRecords;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Actions\Traits\WithTimeSeriesRedo;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

trait WithRedoProductCategoryTimeSeries
{
    use WithHydrateCommand;
    use WithTimeSeriesRedo {
        WithTimeSeriesRedo::asCommand insteadof WithHydrateCommand;
    }

    public function getJobUniqueId(string $from, string $to): string
    {
        return "{$from}_{$to}";
    }

    protected function modifyQuery(Builder $query): Builder
    {
        return $query->where('type', $this->categoryType->value);
    }

    public function handle(?int $productCategoryId, ?string $from = null, ?string $to = null, bool $async = false): void
    {
        if (!$productCategoryId) {
            return;
        }

        $productCategory = ProductCategory::find($productCategoryId);

        if (!$productCategory) {
            return;
        }

        if ($productCategory->state == ProductCategoryStateEnum::IN_PROCESS) {
            return;
        }

        if (!$from || !$to) {
            $firstInvoicedDate = DB::connection('aiku_no_sticky')->table('invoice_transactions')->where("{$this->categoryType->value}_id", $productCategory->id)->whereNull('deleted_at')->min('date');
            $lastInvoicedDate  = DB::connection('aiku_no_sticky')->table('invoice_transactions')->where("{$this->categoryType->value}_id", $productCategory->id)->whereNull('deleted_at')->max('date');

            if (!$firstInvoicedDate) {
                return;
            }

            $from = $from ?? Carbon::parse($firstInvoicedDate)->toDateString();
            $to   = $to ?? Carbon::parse($lastInvoicedDate ?? now())->toDateString();
        }

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            if ($async) {
                ProcessProductCategoryTimeSeriesRecords::dispatch($productCategory->id, $frequency, $from, $to);
            } else {
                ProcessProductCategoryTimeSeriesRecords::run($productCategory->id, $frequency, $from, $to);
            }
        }
    }

    public function asJob(string $from, string $to): void
    {
        $tableName = (new $this->model())->getTable();
        $query     = $this->modifyQuery(DB::table($tableName)->select('id')->orderBy('id', 'desc'));

        $query->chunk(1000, function (Collection $modelsData) use ($from, $to) {
            foreach ($modelsData as $modelId) {
                try {
                    $this->handle($modelId->id, $from, $to, false);
                } catch (Throwable $e) {
                    report($e);
                }
            }
        });
    }
}
