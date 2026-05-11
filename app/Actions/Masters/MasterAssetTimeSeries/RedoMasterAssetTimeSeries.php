<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 Jan 2026 00:54:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAssetTimeSeries;

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

    public string $jobQueue = 'default-long-slave';
    public string $commandSignature = 'master-assets:redo_time_series {--from= : Start date (Y-m-d)} {--to= : End date (Y-m-d)} {--a|async : Run asynchronously}';

    public function __construct()
    {
        $this->model = MasterAsset::class;
    }

    public function getJobUniqueId(string $from, string $to): string
    {
        return "{$from}_$to";
    }

    public function handle(?int $masterAssetId, ?string $from = null, ?string $to = null, bool $async = false): void
    {
        if (!$masterAssetId) {
            return;
        }

        $masterAsset = MasterAsset::find($masterAssetId);

        if (!$masterAsset) {
            return;
        }

        if (!$from || !$to) {
            $firstInvoicedDate = DB::connection('aiku_no_sticky')->table('invoice_transactions')->where('master_asset_id', $masterAsset->id)->whereNull('deleted_at')->min('date');
            $lastInvoicedDate  = DB::connection('aiku_no_sticky')->table('invoice_transactions')->where('master_asset_id', $masterAsset->id)->whereNull('deleted_at')->max('date');

            if (!$firstInvoicedDate) {
                return;
            }

            $from = $from ?? Carbon::parse($firstInvoicedDate)->toDateString();
            $to   = $to ?? Carbon::parse($lastInvoicedDate ?? now())->toDateString();
        }

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            if ($async) {
                ProcessMasterAssetTimeSeriesRecords::dispatch($masterAsset->id, $frequency, $from, $to)->onQueue('sales_slave_historic');
            } else {
                ProcessMasterAssetTimeSeriesRecords::run($masterAsset->id, $frequency, $from, $to);
            }
        }
    }

}
