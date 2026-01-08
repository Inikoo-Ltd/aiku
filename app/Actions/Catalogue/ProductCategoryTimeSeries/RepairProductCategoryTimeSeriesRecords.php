<?php

namespace App\Actions\Catalogue\ProductCategoryTimeSeries;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Catalogue\ProductCategoryTimeSeries;
use App\Models\Catalogue\ProductCategoryTimeSeriesRecord;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Actions\Traits\WithTimeSeriesRecordsGeneration;

class RepairProductCategoryTimeSeriesRecords
{
    use AsAction;
    use WithTimeSeriesRecordsGeneration;

    public string $commandSignature = 'repair:product-category-time-series-records {--dry-run}';

    public function asCommand(Command $command): int
    {
        $dryRun = $command->option('dry-run');
        $timeSeriesList = ProductCategoryTimeSeries::all();
        $inserted = 0;

        foreach ($timeSeriesList as $timeSeries) {
            $frequency = $timeSeries->frequency;
            $type = $timeSeries->type;
            $existingRecords = $timeSeries->records()->get()->keyBy('period');

            $start = $timeSeries->from ? Carbon::parse($timeSeries->from) : $timeSeries->created_at;
            $end = $timeSeries->to ? Carbon::parse($timeSeries->to) : now();

            $periods = $this->generatePeriods($start, $end, $frequency);

            foreach ($periods as $period) {
                $periodKey = $this->formatPeriodKey($period['from'], $frequency);

                if ($existingRecords->has($periodKey)) {
                    continue;
                }

                $data = [
                    'product_category_time_series_id' => $timeSeries->id,
                    'type' => match ($type) {
                        'department' => 'D',
                        'sub_department' => 'S',
                        'family' => 'F',
                        default => $type,
                    },
                    'frequency' => $frequency->singleLetter(),
                    'period' => $periodKey,
                    'from' => $period['from'],
                    'to' => $period['to'],
                    'sales' => 0,
                    'sales_org_currency' => 0,
                    'sales_grp_currency' => 0,
                    'invoices' => 0,
                    'refunds' => 0,
                    'orders' => 0,
                    'customers_invoiced' => 0,
                ];

                if ($dryRun) {
                    $command->line('[DRY RUN] Would insert: ' . json_encode($data));
                } else {
                    ProductCategoryTimeSeriesRecord::create($data);
                    $command->info("Inserted missing period: {$periodKey} for TS #{$timeSeries->id}");
                }
                $inserted++;
            }
        }

        $command->info("Inserted $inserted missing periods" . ($dryRun ? " (dry run)" : ""));
        return 0;
    }

    protected function formatPeriodKey(Carbon $date, TimeSeriesFrequencyEnum $frequency): string
    {
        return match ($frequency) {
            TimeSeriesFrequencyEnum::DAILY => $date->format('Y-m-d'),
            TimeSeriesFrequencyEnum::WEEKLY => $date->format('o \W\WW'),
            TimeSeriesFrequencyEnum::MONTHLY => $date->format('Y-m'),
            TimeSeriesFrequencyEnum::QUARTERLY => $date->format('Y') . ' Q' . $date->quarter,
            TimeSeriesFrequencyEnum::YEARLY => $date->format('Y'),
        };
    }
}
