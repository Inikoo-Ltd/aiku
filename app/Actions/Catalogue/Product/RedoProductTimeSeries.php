<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\Product;

use App\Helpers\TimeSeriesPeriodCalculator;
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

    public string $jobQueue         = 'long-low-priority';
    public string $commandSignature = 'products:redo_time_series {--S|shop= : Shop slug} {--O|organisation= : Organisation slug} {--from= : Start date (Y-m-d)} {--to= : End date (Y-m-d)} {--a|async : Run asynchronously}';

    public function __construct()
    {
        $this->model = Product::class;
    }

    protected function dateRangeSources(): array
    {
        return [
            [
                'query' => fn () => DB::connection('aiku_no_sticky')->table('invoice_transactions')->whereNull('deleted_at'),
                'key'   => 'asset_id',
                'date'  => 'date',
            ],
        ];
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
            $dateRange = $this->getDateRange($product->asset_id);

            if (!$dateRange['from']) {
                return;
            }

            $from = $from ?? Carbon::parse($dateRange['from'])->toDateString();
            $to   = $to ?? Carbon::parse($dateRange['to'] ?? now())->toDateString();
        }

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            [$periodFrom, $periodTo] = TimeSeriesPeriodCalculator::expandWindowToFullPeriods($frequency, $from, $to);

            if ($async) {
                ProcessAssetTimeSeriesRecords::dispatch($product->asset_id, $frequency, $periodFrom, $periodTo)->onQueue('sales_slave_historic');
            } else {
                ProcessAssetTimeSeriesRecords::run($product->asset_id, $frequency, $periodFrom, $periodTo);
            }
        }
    }
}
