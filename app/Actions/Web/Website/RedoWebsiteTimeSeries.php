<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Website;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Actions\Traits\WithTimeSeriesRedo;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Web\Website;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RedoWebsiteTimeSeries implements ShouldBeUnique
{
    use WithHydrateCommand;
    use WithTimeSeriesRedo {
        WithTimeSeriesRedo::asCommand insteadof WithHydrateCommand;
    }

    public string $jobQueue         = 'default-long-slave';
    public string $commandSignature = 'websites:redo_time_series {--from= : Start date (Y-m-d)} {--to= : End date (Y-m-d)} {--a|async : Run asynchronously}';

    public function __construct()
    {
        $this->model = Website::class;
    }

    public function getJobUniqueId(string $from, string $to): string
    {
        return "{$from}_$to";
    }

    public function handle(?int $websiteId, ?string $from = null, ?string $to = null, bool $async = false): void
    {
        if (!$websiteId) {
            return;
        }

        $website = Website::find($websiteId);

        if (!$website) {
            return;
        }

        if (!$from || !$to) {
            $firstVisitDate = DB::connection('aiku_no_sticky')->table('website_visitors')->where('website_id', $website->id)->min('first_seen_at');
            $lastVisitDate  = DB::connection('aiku_no_sticky')->table('website_visitors')->where('website_id', $website->id)->max('first_seen_at');

            if (!$firstVisitDate) {
                return;
            }

            $from = $from ?? Carbon::parse($firstVisitDate)->toDateString();
            $to   = $to ?? Carbon::parse($lastVisitDate ?? now())->toDateString();
        }

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            if ($async) {
                ProcessWebsiteTimeSeriesRecords::dispatch($website->id, $frequency, $from, $to)->onQueue('sales_slave_historic');
            } else {
                ProcessWebsiteTimeSeriesRecords::run($website->id, $frequency, $from, $to);
            }
        }
    }

}
