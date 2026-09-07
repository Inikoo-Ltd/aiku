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
use Illuminate\Database\Query\Builder;
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

    protected function modifyQuery(Builder $query): Builder
    {
        return $query->where('type', $this->categoryType->value);
    }

    protected function dateRangeSources(): array
    {
        return [
            [
                'query' => fn () => DB::connection('aiku_no_sticky')->table('invoice_transactions')->whereNull('deleted_at'),
                'key'   => "master_{$this->categoryType->value}_id",
                'date'  => 'date',
            ],
        ];
    }

    public function handle(?int $masterProductCategoryId, ?string $from = null, ?string $to = null, bool $async = false): void
    {
        if (!$masterProductCategoryId) {
            return;
        }

        if (!$from || !$to) {
            $dateRange = $this->getDateRange($masterProductCategoryId);

            if (!$dateRange['from']) {
                return;
            }

            $from = $from ?? Carbon::parse($dateRange['from'])->toDateString();
            $to   = $to ?? Carbon::parse($dateRange['to'] ?? now())->toDateString();
        }

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            if ($async) {
                ProcessMasterProductCategoryTimeSeriesRecords::dispatch($masterProductCategoryId, $frequency, $from, $to);
            } else {
                ProcessMasterProductCategoryTimeSeriesRecords::run($masterProductCategoryId, $frequency, $from, $to);
            }
        }
    }
}
