<?php

namespace App\Actions\Helpers\Brand;

use App\Helpers\TimeSeriesPeriodCalculator;
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

    public string $jobQueue = 'long-low-priority';
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

    protected function dateRangeSources(): array
    {
        return [
            [
                'query' => fn () => DB::connection('aiku_no_sticky')->table('invoice_transactions')->whereNull('deleted_at'),
                'key'   => 'brand_id',
                'date'  => 'date',
            ],
        ];
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
            $dateRange = $this->getDateRange($brand->id);

            if (!$dateRange['from']) {
                return;
            }

            $from = $from ?? Carbon::parse($dateRange['from'])->toDateString();
            $to   = $to ?? Carbon::parse($dateRange['to'] ?? now())->toDateString();
        }

        foreach ($shopIds as $shopId) {
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                [$periodFrom, $periodTo] = TimeSeriesPeriodCalculator::expandWindowToFullPeriods($frequency, $from, $to);

                if ($async) {
                    ProcessBrandTimeSeriesRecords::dispatch($brand->id, $shopId, $frequency, $periodFrom, $periodTo)->onQueue('sales_slave_historic');
                } else {
                    ProcessBrandTimeSeriesRecords::run($brand->id, $shopId, $frequency, $periodFrom, $periodTo);
                }
            }
        }
    }

}
