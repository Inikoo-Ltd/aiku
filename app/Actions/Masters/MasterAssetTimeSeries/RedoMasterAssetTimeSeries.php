<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 Jan 2026 00:54:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAssetTimeSeries;

use App\Helpers\TimeSeriesPeriodCalculator;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Actions\Traits\WithTimeSeriesRedo;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Masters\MasterAsset;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RedoMasterAssetTimeSeries implements ShouldBeUnique
{
    use WithHydrateCommand;
    use WithTimeSeriesRedo {
        WithTimeSeriesRedo::asCommand insteadof WithHydrateCommand;
    }

    public string $jobQueue = 'long-low-priority';
    public string $commandSignature = 'master-assets:redo_time_series {--from= : Start date (Y-m-d)} {--to= : End date (Y-m-d)} {--a|async : Run asynchronously}';

    public function __construct()
    {
        $this->model = MasterAsset::class;
    }

    public function getJobUniqueId(string $from, string $to): string
    {
        return "{$from}_$to";
    }

    protected function dateRangeSources(): array
    {
        return [
            [
                'query' => fn () => DB::connection('aiku_no_sticky')->table('invoice_transactions')->whereNull('deleted_at'),
                'key'   => 'master_asset_id',
                'date'  => 'date',
            ],
        ];
    }

    public function handle(?int $masterAssetId, ?string $from = null, ?string $to = null, bool $async = false): void
    {
        if (!$masterAssetId) {
            return;
        }

        if (!$from || !$to) {
            $dateRange = $this->getDateRange($masterAssetId);

            if (!$dateRange['from']) {
                return;
            }

            $from = $from ?? Carbon::parse($dateRange['from'])->toDateString();
            $to   = $to ?? Carbon::parse($dateRange['to'] ?? now())->toDateString();
        }

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            [$periodFrom, $periodTo] = TimeSeriesPeriodCalculator::expandWindowToFullPeriods($frequency, $from, $to);

            if ($async) {
                ProcessMasterAssetTimeSeriesRecords::dispatch($masterAssetId, $frequency, $periodFrom, $periodTo)->onQueue('sales_slave_historic');
            } else {
                ProcessMasterAssetTimeSeriesRecords::run($masterAssetId, $frequency, $periodFrom, $periodTo);
            }
        }
    }
}
