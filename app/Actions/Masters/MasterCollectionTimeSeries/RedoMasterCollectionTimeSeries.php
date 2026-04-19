<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 Jan 2026 02:52:11 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterCollectionTimeSeries;

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

    public string $commandSignature = 'master_collections:redo_time_series {--from= : Start date (Y-m-d)} {--to= : End date (Y-m-d)} {--a|async : Run asynchronously}';

    public function __construct()
    {
        $this->model = MasterCollection::class;
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
            $masterAssetsIDs = $masterCollection->masterProducts()->pluck('master_assets.id')->unique()->toArray();

            $firstInvoicedDate = DB::table('invoice_transactions')->whereIn('master_asset_id', $masterAssetsIDs)->whereNull('deleted_at')->min('date');
            $lastInvoicedDate  = DB::table('invoice_transactions')->whereIn('master_asset_id', $masterAssetsIDs)->whereNull('deleted_at')->max('date');

            if (!$firstInvoicedDate) {
                return;
            }

            $from = $from ?? Carbon::parse($firstInvoicedDate)->toDateString();
            $to   = $to ?? Carbon::parse($lastInvoicedDate ?? now())->toDateString();
        }

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            if ($async) {
                ProcessMasterCollectionTimeSeriesRecords::dispatch($masterCollection->id, $frequency, $from, $to)->onQueue('sales_slave_historic');
            } else {
                ProcessMasterCollectionTimeSeriesRecords::run($masterCollection->id, $frequency, $from, $to);
            }
        }
    }

}
