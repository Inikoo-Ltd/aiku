<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Webpage;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Models\Web\Webpage;
use Illuminate\Console\Command;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class FetchTopWebpagesPageSpeed
{
    use AsAction;

    public string $jobQueue = 'cache-warming';

    public const int WEBPAGES_PER_BUCKET = 25;

    /**
     * Every live content webpage and every live department is measured. Content covers the whole
     * content type, so about us, returns or terms pages are measured alongside the plain content
     * ones. Sub departments and families are too many to measure in full, so they keep their best
     * performing webpages only.
     */
    public const array UNCAPPED_BUCKETS = [
        WebpageTypeEnum::CONTENT->value,
        WebpageSubTypeEnum::DEPARTMENT->value,
    ];

    public const array CAPPED_BUCKETS = [
        WebpageSubTypeEnum::SUB_DEPARTMENT->value,
        WebpageSubTypeEnum::FAMILY->value,
    ];

    /**
     * A run takes around 45 seconds, so the cache-warming workers drain about 4 runs per minute.
     * Dispatching at that pace keeps an on demand run from waiting behind the whole nightly crawl
     * and keeps the crawl far below the 240 requests per minute PageSpeed Insights allows.
     */
    public const int SECONDS_BETWEEN_RUNS = 10;

    public function handle(int $webpagesPerBucket = self::WEBPAGES_PER_BUCKET): array
    {
        if (!config('app.analytics.google.pagespeed_api_key')) {
            return [
                'queued_runs'      => 0,
                'skipped_webpages' => 0,
                'spread_minutes'   => 0,
                'started_at'       => now()->toIso8601String(),
                'error'            => 'GOOGLE_PAGESPEED_API_KEY is not set, the unauthenticated quota cannot sustain a daily crawl',
            ];
        }

        $queued  = 0;
        $skipped = 0;

        foreach ($this->bestPerformingWebpages($webpagesPerBucket) as $webpage) {
            if (!GetWebpagePageSpeed::publiclyReachableUrl($webpage)) {
                $skipped++;
                continue;
            }

            foreach (GetWebpagePageSpeed::STRATEGIES as $strategy) {
                if (QueueWebpagePageSpeed::run($webpage, $strategy, $queued * self::SECONDS_BETWEEN_RUNS)) {
                    $queued++;
                }
            }
        }

        $summary = [
            'queued_runs'      => $queued,
            'skipped_webpages' => $skipped,
            'spread_minutes'   => (int)ceil($queued * self::SECONDS_BETWEEN_RUNS / 60),
            'started_at'       => now()->toIso8601String(),
        ];

        cache()->put('webpage-pagespeed-last-crawl', $summary, now()->addDays(2));

        return $summary;
    }

    /**
     * @return Collection<int, Webpage>
     */
    private function bestPerformingWebpages(int $webpagesPerBucket): Collection
    {
        $ranked = DB::query()
            ->fromSub($this->bucketedWebpages(), 'bucketed_webpages')
            ->select('id', 'bucket', 'performance')
            ->selectRaw('row_number() over (partition by website_id, bucket order by performance desc nulls last, id) as bucket_position');

        $webpageIds = DB::query()
            ->fromSub($ranked, 'ranked_webpages')
            ->where(function (Builder $query) use ($webpagesPerBucket) {
                $query->whereIn('bucket', self::UNCAPPED_BUCKETS)
                    ->orWhere(function (Builder $query) use ($webpagesPerBucket) {
                        $query->whereIn('bucket', self::CAPPED_BUCKETS)
                            ->where('bucket_position', '<=', $webpagesPerBucket);
                    });
            })
            ->orderByRaw('performance desc nulls last')
            ->orderBy('id')
            ->pluck('id');

        $positions = $webpageIds->flip();

        return Webpage::whereIn('id', $webpageIds)
            ->with(['website', 'shop'])
            ->get()
            ->sortBy(fn (Webpage $webpage) => $positions->get($webpage->id))
            ->values();
    }

    /**
     * The content type keeps its own bucket, the catalogue ones are split by sub type so that a
     * department never competes for a slot with a family.
     */
    private function bucketedWebpages(): Builder
    {
        return DB::table('webpages')
            ->leftJoinSub($this->latestPageSpeedPerformance(), 'latest_pagespeed', 'latest_pagespeed.webpage_id', '=', 'webpages.id')
            ->select('webpages.id', 'webpages.website_id', 'latest_pagespeed.performance')
            ->selectRaw('case when webpages.type = ? then ? else webpages.sub_type end as bucket', [
                WebpageTypeEnum::CONTENT->value,
                WebpageTypeEnum::CONTENT->value,
            ])
            ->where('webpages.state', WebpageStateEnum::LIVE)
            ->whereNull('webpages.deleted_at')
            ->where(function (Builder $query) {
                $query->where('webpages.type', WebpageTypeEnum::CONTENT->value)
                    ->orWhereIn('webpages.sub_type', [...self::UNCAPPED_BUCKETS, ...self::CAPPED_BUCKETS]);
            });
    }

    /**
     * The performance of the last day both strategies were measured, averaged when desktop and
     * mobile are both there. Webpages that were never measured come back without a score and are
     * ranked last, so they only take the slots the measured ones leave free.
     */
    private function latestPageSpeedPerformance(): Builder
    {
        $desktop = StoreWebpagePageSpeedTimeSeriesRecord::column('desktop', 'performance');
        $mobile  = StoreWebpagePageSpeedTimeSeriesRecord::column('mobile', 'performance');

        return DB::table('webpage_time_series_records as pagespeed_records')
            ->join('webpage_time_series as pagespeed_series', 'pagespeed_series.id', '=', 'pagespeed_records.webpage_time_series_id')
            ->selectRaw('distinct on (pagespeed_series.webpage_id) pagespeed_series.webpage_id')
            ->selectRaw("(coalesce($desktop, $mobile) + coalesce($mobile, $desktop)) / 2.0 as performance")
            ->where('pagespeed_records.frequency', TimeSeriesFrequencyEnum::DAILY->singleLetter())
            ->where(function (Builder $query) use ($desktop, $mobile) {
                $query->whereNotNull("pagespeed_records.$desktop")
                    ->orWhereNotNull("pagespeed_records.$mobile");
            })
            ->orderByRaw('pagespeed_series.webpage_id, pagespeed_records."from" desc');
    }

    public function getCommandSignature(): string
    {
        return 'pagespeed:fetch_top_webpages {--webpages-per-bucket= : Sub department and family webpages measured per website}';
    }

    public function getCommandDescription(): string
    {
        return 'Queue PageSpeed Insights runs for every content and department webpage and the best performing sub department and family webpages of each website';
    }

    public function asCommand(Command $command): int
    {
        $summary = $this->handle(
            (int)($command->option('webpages-per-bucket') ?: self::WEBPAGES_PER_BUCKET),
        );

        if (isset($summary['error'])) {
            $command->error($summary['error']);

            return 1;
        }

        $command->info("Queued $summary[queued_runs] runs over $summary[spread_minutes] minutes, skipped $summary[skipped_webpages] webpages without a public url");

        return 0;
    }
}
