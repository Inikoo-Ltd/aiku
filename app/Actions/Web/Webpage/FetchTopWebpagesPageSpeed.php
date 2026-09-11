<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Webpage;

use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Web\Webpage;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class FetchTopWebpagesPageSpeed
{
    use AsAction;

    public string $jobQueue = 'cache-warming';

    public const int WEBPAGES_PER_WEBSITE = 10;
    public const int LOOKBACK_DAYS        = 7;

    /**
     * A run takes around 45 seconds, so with the 5 cache-warming workers the queue drains about
     * 6 runs per minute. Dispatching at that pace keeps an on demand run from waiting behind the
     * whole nightly crawl.
     */
    public const int SECONDS_BETWEEN_RUNS = 10;

    public function handle(int $webpagesPerWebsite = self::WEBPAGES_PER_WEBSITE, int $lookbackDays = self::LOOKBACK_DAYS): array
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

        foreach ($this->topWebpages($webpagesPerWebsite, $lookbackDays) as $webpage) {
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
    private function topWebpages(int $webpagesPerWebsite, int $lookbackDays): Collection
    {
        $viewsPerWebpage = DB::table('website_page_views')
            ->selectRaw('website_id, webpage_id, count(*) as views')
            ->where('view_date', '>=', now()->subDays($lookbackDays)->toDateString())
            ->whereNotNull('webpage_id')
            ->groupBy('website_id', 'webpage_id');

        $rankedPerWebsite = DB::query()
            ->fromSub($viewsPerWebpage, 'views_per_webpage')
            ->select('webpage_id', 'views')
            ->selectRaw('row_number() over (partition by website_id order by views desc) as website_position');

        $webpageIds = DB::query()
            ->fromSub($rankedPerWebsite, 'ranked_per_website')
            ->where('website_position', '<=', $webpagesPerWebsite)
            ->orderByDesc('views')
            ->pluck('webpage_id');

        return Webpage::whereIn('id', $webpageIds)
            ->where('state', WebpageStateEnum::LIVE)
            ->with(['website', 'shop'])
            ->get()
            ->sortBy(fn (Webpage $webpage) => $webpageIds->search($webpage->id))
            ->values();
    }

    public function getCommandSignature(): string
    {
        return 'pagespeed:fetch_top_webpages {--webpages-per-website= : Webpages measured per website} {--days= : Days of page views used for the ranking}';
    }

    public function getCommandDescription(): string
    {
        return 'Queue PageSpeed Insights runs for the most visited webpage of each website';
    }

    public function asCommand(Command $command): int
    {
        $summary = $this->handle(
            (int)($command->option('webpages-per-website') ?: self::WEBPAGES_PER_WEBSITE),
            (int)($command->option('days') ?: self::LOOKBACK_DAYS),
        );

        if (isset($summary['error'])) {
            $command->error($summary['error']);

            return 1;
        }

        $command->info("Queued $summary[queued_runs] runs over $summary[spread_minutes] minutes, skipped $summary[skipped_webpages] webpages without a public url");

        return 0;
    }
}
