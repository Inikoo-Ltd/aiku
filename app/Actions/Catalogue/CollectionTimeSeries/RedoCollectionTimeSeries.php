<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\CollectionTimeSeries;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Actions\Traits\WithTimeSeriesRedo;
use App\Enums\Catalogue\Collection\CollectionStateEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Catalogue\Collection as CollectionModel;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RedoCollectionTimeSeries implements ShouldBeUnique
{
    use WithHydrateCommand;
    use WithTimeSeriesRedo {
        WithTimeSeriesRedo::asCommand insteadof WithHydrateCommand;
    }

    public string $jobQueue = 'default-long-slave';
    public string $commandSignature = 'collections:redo_time_series {--from= : Start date (Y-m-d)} {--to= : End date (Y-m-d)} {--a|async : Run asynchronously}';

    public function __construct()
    {
        $this->model = CollectionModel::class;
    }

    public function getJobUniqueId(string $from, string $to): string
    {
        return "{$from}_$to";
    }

    public function handle(?int $collectionId, ?string $from = null, ?string $to = null, bool $async = false): void
    {
        if (!$collectionId) {
            return;
        }

        $collection = CollectionModel::find($collectionId);

        if (!$collection) {
            return;
        }

        if ($collection->state == CollectionStateEnum::IN_PROCESS || !$collection->source_id) {
            return;
        }

        if (!$from || !$to) {
            $assetsIDs = $collection->products->pluck('asset_id')->unique()->toArray();

            $firstInvoicedDate = DB::connection('aiku_no_sticky')->table('invoice_transactions')->whereIn('asset_id', $assetsIDs)->whereNull('deleted_at')->min('date');
            $lastInvoicedDate  = DB::connection('aiku_no_sticky')->table('invoice_transactions')->whereIn('asset_id', $assetsIDs)->whereNull('deleted_at')->max('date');

            if (!$firstInvoicedDate) {
                return;
            }

            $from = $from ?? Carbon::parse($firstInvoicedDate)->toDateString();
            $to   = $to ?? Carbon::parse($lastInvoicedDate ?? now())->toDateString();
        }

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            if ($async) {
                ProcessCollectionTimeSeriesRecords::dispatch($collection->id, $frequency, $from, $to)->onQueue('sales_slave_historic');
            } else {
                ProcessCollectionTimeSeriesRecords::run($collection->id, $frequency, $from, $to);
            }
        }
    }

}
