<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Webpage;

use App\Helpers\TimeSeriesPeriodCalculator;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Actions\Traits\WithTimeSeriesRedo;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\Web\WebsiteConversionEvent\WebsiteConversionEventTypeEnum;
use App\Models\Web\Webpage;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

class RedoWebpageTimeSeries implements ShouldBeUnique
{
    use WithHydrateCommand;
    use WithTimeSeriesRedo {
        WithTimeSeriesRedo::asCommand insteadof WithHydrateCommand;
    }

    public string $jobQueue         = 'long-low-priority';
    public string $commandSignature = 'webpages:redo_time_series {--S|shop= : Shop slug} {--O|organisation= : Organisation slug} {--from= : Start date (Y-m-d)} {--to= : End date (Y-m-d)} {--a|async : Run asynchronously}';

    protected ?string $windowFrom = null;
    protected ?string $windowTo   = null;

    public function __construct()
    {
        $this->model = Webpage::class;
    }

    protected function beforeCommand(Command $command): void
    {
        if ($command->option('from') && $command->option('to')) {
            $this->windowFrom = $command->option('from');
            $this->windowTo   = $command->option('to');
        }
    }

    protected function modifyQuery(Builder $query): Builder
    {
        if (!$this->windowFrom) {
            return $query;
        }

        return $query->whereIn(
            'id',
            DB::table('website_page_views')
                ->select('webpage_id')
                ->whereBetween('view_date', [$this->windowFrom, $this->windowTo])
                ->whereNotNull('webpage_id')
                ->union(
                    DB::table('website_conversion_events')
                        ->select('webpage_id')
                        ->whereBetween('event_date', [$this->windowFrom, $this->windowTo])
                        ->where('event_type', WebsiteConversionEventTypeEnum::ADD_TO_BASKET->value)
                        ->whereNotNull('webpage_id')
                )
                ->union(
                    DB::table('website_conversion_events')
                        ->select('landing_webpage_id')
                        ->whereBetween('event_date', [$this->windowFrom, $this->windowTo])
                        ->whereIn('event_type', [WebsiteConversionEventTypeEnum::CHECKOUT->value, WebsiteConversionEventTypeEnum::PURCHASE->value])
                        ->whereNotNull('landing_webpage_id')
                )
        );
    }

    public function getJobUniqueId(string $from, string $to): string
    {
        return "{$from}_$to";
    }

    protected function dateRangeSources(): array
    {
        return [
            [
                'query' => fn () => DB::connection('aiku_no_sticky')->table('website_page_views'),
                'key'   => 'webpage_id',
                'date'  => 'view_date',
            ],
            [
                'query' => fn () => DB::connection('aiku_no_sticky')->table('website_conversion_events'),
                'key'   => 'landing_webpage_id',
                'date'  => 'event_date',
            ],
        ];
    }

    public function handle(?int $webpageId, ?string $from = null, ?string $to = null, bool $async = false): void
    {
        if (!$webpageId) {
            return;
        }

        $webpage = Webpage::find($webpageId);

        if (!$webpage) {
            return;
        }

        if (!$from || !$to) {
            $dateRange = $this->getDateRange($webpage->id);

            if (!$dateRange['from']) {
                return;
            }

            $from = $from ?? Carbon::parse($dateRange['from'])->toDateString();
            $to   = $to ?? Carbon::parse($dateRange['to'] ?? now())->toDateString();
        }

        $jobs = [];

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            [$periodFrom, $periodTo] = TimeSeriesPeriodCalculator::expandWindowToFullPeriods($frequency, $from, $to);

            if ($async) {
                $jobs[] = ProcessWebpageTimeSeriesRecords::makeJob($webpage->id, $frequency, $periodFrom, $periodTo)->onQueue('sales_slave_historic');
            } else {
                ProcessWebpageTimeSeriesRecords::run($webpage->id, $frequency, $periodFrom, $periodTo);
            }
        }

        if ($jobs) {
            Bus::chain($jobs)->dispatch();
        }
    }

}
