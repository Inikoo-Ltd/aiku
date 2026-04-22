<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\Product;

use App\Actions\Catalogue\AssetTimeSeries\ProcessAssetTimeSeriesRecords;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Actions\Traits\WithTimeSeriesRedo;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Catalogue\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RedoProductTimeSeries
{
    use WithHydrateCommand;
    use WithTimeSeriesRedo {
        WithTimeSeriesRedo::asCommand insteadof WithHydrateCommand;
    }

    public string $commandSignature = 'products:redo_time_series {--from= : Start date (Y-m-d)} {--to= : End date (Y-m-d)} {--a|async : Run asynchronously}';

    public function __construct()
    {
        $this->model = Product::class;
    }

    public function handle(?int $productId, ?string $from = null, ?string $to = null, bool $async = false): void
    {
        if (!$productId) {
            return;
        }
        $product = Product::find($productId);
        if (!$product) {
            return;
        }


        if ($product->state == ProductStateEnum::IN_PROCESS) {
            return;
        }

        if (!$from || !$to) {
            $firstInvoicedDate = DB::table('invoice_transactions')->where('asset_id', $product->asset_id)->whereNull('deleted_at')->min('date');
            $lastInvoicedDate  = DB::table('invoice_transactions')->where('asset_id', $product->asset_id)->whereNull('deleted_at')->max('date');

            if (!$firstInvoicedDate) {
                return;
            }

            $from = $from ?? Carbon::parse($firstInvoicedDate)->toDateString();
            $to   = $to ?? Carbon::parse($lastInvoicedDate ?? now())->toDateString();
        }

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            if ($async) {
                ProcessAssetTimeSeriesRecords::dispatch($product->asset_id, $frequency, $from, $to)->onQueue('sales_slave_historic');
            } else {
                ProcessAssetTimeSeriesRecords::run($product->asset_id, $frequency, $from, $to);
            }
        }
    }


}
