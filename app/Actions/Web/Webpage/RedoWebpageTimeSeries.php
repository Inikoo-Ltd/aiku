<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Webpage;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Actions\Traits\WithTimeSeriesRedo;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Web\Webpage;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class RedoWebpageTimeSeries implements ShouldBeUnique
{
    use WithHydrateCommand;
    use WithTimeSeriesRedo {
        WithTimeSeriesRedo::asCommand insteadof WithHydrateCommand;
    }

    public string $jobQueue         = 'default-long-slave';
    public string $commandSignature = 'webpages:redo_time_series {--from= : Start date (Y-m-d)} {--to= : End date (Y-m-d)} {--a|async : Run asynchronously}';

    public function __construct()
    {
        $this->model = Webpage::class;
    }

    public function getJobUniqueId(string $from, string $to): string
    {
        return "{$from}_{$to}";
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
            $firstViewDate = DB::connection('aiku_no_sticky')->table('website_page_views')->where('webpage_id', $webpage->id)->min('view_date');
            $lastViewDate  = DB::connection('aiku_no_sticky')->table('website_page_views')->where('webpage_id', $webpage->id)->max('view_date');

            if (!$firstViewDate) {
                return;
            }

            $from = $from ?? Carbon::parse($firstViewDate)->toDateString();
            $to   = $to ?? Carbon::parse($lastViewDate ?? now())->toDateString();
        }

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            if ($async) {
                ProcessWebpageTimeSeriesRecords::dispatch($webpage->id, $frequency, $from, $to);
            } else {
                ProcessWebpageTimeSeriesRecords::run($webpage->id, $frequency, $from, $to);
            }
        }
    }

    public function asJob(string $from, string $to): void
    {
        $tableName = (new $this->model())->getTable();
        $query     = DB::table($tableName)->select('id')->orderBy('id', 'desc');

        $query->chunk(1000, function (Collection $modelsData) use ($from, $to) {
            foreach ($modelsData as $modelId) {
                try {
                    $this->handle($modelId->id, $from, $to, false);
                } catch (Throwable $e) {
                    report($e);
                }
            }
        });
    }
}
