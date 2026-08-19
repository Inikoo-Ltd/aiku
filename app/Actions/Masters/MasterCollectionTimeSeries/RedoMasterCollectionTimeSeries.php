<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 Jan 2026 02:52:11 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterCollectionTimeSeries;

use App\Helpers\TimeSeriesPeriodCalculator;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Actions\Traits\WithTimeSeriesRedo;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Masters\MasterCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RedoMasterCollectionTimeSeries
{
    use WithHydrateCommand;
    use WithTimeSeriesRedo {
        WithTimeSeriesRedo::asCommand insteadof WithHydrateCommand;
    }

    public string $jobQueue         = 'long-low-priority';
    public string $commandSignature = 'master_collections:redo_time_series {--from= : Start date (Y-m-d)} {--to= : End date (Y-m-d)} {--a|async : Run asynchronously}';

    public function __construct()
    {
        $this->model = MasterCollection::class;
    }


    protected function dateRangeSources(): array
    {
        return [
            [
                'query' => fn () => DB::connection('aiku_no_sticky')->table('invoice_transactions')
                    ->join('master_collection_has_models', function ($join) {
                        $join->on('master_collection_has_models.model_id', '=', 'invoice_transactions.master_asset_id')
                            ->where('master_collection_has_models.model_type', '=', 'MasterAsset');
                    })
                    ->whereNull('invoice_transactions.deleted_at'),
                'key'   => 'master_collection_has_models.master_collection_id',
                'date'  => 'invoice_transactions.date',
            ],
        ];
    }

    public function handle(?int $masterCollectionId, ?string $from = null, ?string $to = null, bool $async = false): void
    {
        if (!$masterCollectionId) {
            return;
        }
        $masterCollection = MasterCollection::find($masterCollectionId);
        if (!$masterCollection) {
            return;
        }

        if (!$from || !$to) {
            $dateRange = $this->getDateRange($masterCollection->id);

            if (!$dateRange['from']) {
                return;
            }

            $from = $from ?? Carbon::parse($dateRange['from'])->toDateString();
            $to   = $to ?? Carbon::parse($dateRange['to'] ?? now())->toDateString();
        }

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            [$periodFrom, $periodTo] = TimeSeriesPeriodCalculator::expandWindowToFullPeriods($frequency, $from, $to);

            if ($async) {
                ProcessMasterCollectionTimeSeriesRecords::dispatch($masterCollection->id, $frequency, $periodFrom, $periodTo);
            } else {
                ProcessMasterCollectionTimeSeriesRecords::run($masterCollection->id, $frequency, $periodFrom, $periodTo);
            }
        }
    }
}
