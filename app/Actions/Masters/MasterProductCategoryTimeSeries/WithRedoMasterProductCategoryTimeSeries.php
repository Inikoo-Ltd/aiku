<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 Jan 2026 01:35:33 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategoryTimeSeries;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Actions\Traits\WithTimeSeriesRedo;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

trait WithRedoMasterProductCategoryTimeSeries
{
    use WithHydrateCommand;
    use WithTimeSeriesRedo {
        WithTimeSeriesRedo::asCommand insteadof WithHydrateCommand;
    }

    public function getJobUniqueId(?int $masterProductCategoryId, string $from, string $to): string
    {
        if ($masterProductCategoryId === null) {
            return 'empty'.'_'.$from.'_'.$to;
        }

        return $masterProductCategoryId.'_'.$from.'_'.$to;
    }

    public function handle(?int $masterProductCategoryId, bool $async = false, ?string $from = null, ?string $to = null): void
    {
        if (!$masterProductCategoryId) {
            return;
        }
        $masterProductCategory = MasterProductCategory::find($masterProductCategoryId);
        if (!$masterProductCategory) {
            return;
        }

        if (!$from || !$to) {
            $firstInvoicedDate = DB::table('invoice_transactions')->where("master_{$this->categoryType->value}_id", $masterProductCategory->id)->whereNull('deleted_at')->min('date');
            $lastInvoicedDate  = DB::table('invoice_transactions')->where("master_{$this->categoryType->value}_id", $masterProductCategory->id)->whereNull('deleted_at')->max('date');

            if (!$firstInvoicedDate) {
                return;
            }

            $from = $from ?? Carbon::parse($firstInvoicedDate)->toDateString();
            $to   = $to ?? Carbon::parse($lastInvoicedDate ?? now())->toDateString();
        }

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            if ($async) {
                ProcessMasterProductCategoryTimeSeriesRecords::dispatch($masterProductCategory->id, $frequency, $from, $to)->onQueue('sales_slave_historic');
            } else {
                ProcessMasterProductCategoryTimeSeriesRecords::run($masterProductCategory->id, $frequency, $from, $to);
            }
        }
    }


}
