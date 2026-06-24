<?php

namespace App\Actions\Helpers\Brand;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Actions\Traits\WithTimeSeriesRedo;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Helpers\Brand;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Telescope\Telescope;

class RedoBrandTimeSeries implements ShouldBeUnique
{
    use WithHydrateCommand;
    use WithTimeSeriesRedo {
        WithTimeSeriesRedo::asCommand insteadof WithHydrateCommand;
    }

    public string $jobQueue = 'default-long-slave';
    public string $commandSignature = 'brands:redo_time_series {--from= : Start date (Y-m-d)} {--to= : End date (Y-m-d)} {--a|async : Run asynchronously}';

    public function __construct()
    {
        $this->model = Brand::class;
    }

    public function getJobUniqueId(string $from, string $to): string
    {
        return "{$from}_$to";
    }

    protected function beforeCommand(Command $command): void
    {
        if (class_exists(Telescope::class)) {
            Telescope::stopRecording();
        }
    }

    public function handle(?int $brandId, ?string $from = null, ?string $to = null, bool $async = false): void
    {
        if (!$brandId) {
            return;
        }

        $brand = Brand::find($brandId);

        if (!$brand) {
            return;
        }

        $shopIds = DB::connection('aiku_no_sticky')->table('invoice_transactions')
            ->where('brand_id', $brand->id)
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('shop_id')
            ->filter()
            ->all();

        if (empty($shopIds)) {
            return;
        }

        if (!$from || !$to) {
            $dateRange = DB::connection('aiku_no_sticky')->table('invoice_transactions')
                ->where('brand_id', $brand->id)
                ->whereNull('deleted_at')
                ->selectRaw('MIN(date) as first_date, MAX(date) as last_date')
                ->first();

            if (!$dateRange?->first_date) {
                return;
            }

            $from = $from ?? Carbon::parse($dateRange->first_date)->toDateString();
            $to   = $to ?? Carbon::parse($dateRange->last_date ?? now())->toDateString();
        }

        foreach ($shopIds as $shopId) {
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                if ($async) {
                    ProcessBrandTimeSeriesRecords::dispatch($brand->id, $shopId, $frequency, $from, $to)->onQueue('sales_slave_historic');
                } else {
                    ProcessBrandTimeSeriesRecords::run($brand->id, $shopId, $frequency, $from, $to);
                }
            }
        }
    }

}
