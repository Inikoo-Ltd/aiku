<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 13 May 2026 20:16:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Crawl;

use App\Enums\Web\Crawl\CrawlStateEnum;
use App\Enums\Web\Crawl\CrawlTriggerEnum;
use App\Enums\Web\Crawl\CrawlTypeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Web\Crawl;
use App\Models\Web\Website;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;

class CrawlWebsite
{
    use AsAction;

    public string $jobQueue = 'cache-warming';

    protected Website $website;
    protected Crawl $crawl;
    private bool $shouldStop = false;


    public function handle(int $crawlId): void
    {
        if (!app()->environment('production')) {
            return;
        }

        $crawl = Crawl::find($crawlId);
        if (!$crawl || $crawl->state != CrawlStateEnum::READY) {
            return;
        }

        if (!$crawl->is_seeder) {
            $this->stopCurrentCrawls($crawl);
        }

        $crawl = $this->protectFromSurges($crawl);

        $crawl->update(
            [
                'state'    => CrawlStateEnum::RUNNING,
                'start_at' => now(),
                'running'  => true
            ]
        );
        $this->crawl = $crawl;

        $urls  = $this->getPrioritisedUrls($crawl);
        $total = count($urls);
        $crawl->update(['urls_found' => $total]);

        $processed = 0;
        foreach (array_chunk($urls, max(1, $crawl->concurrency)) as $chunk) {
            $this->checkIfShouldStop();
            if ($this->shouldStop) {
                $this->finish('interrupted', $processed, $total);

                return;
            }

            Http::pool(fn (Pool $pool) => array_map(
                fn (string $url) => $pool->connectTimeout(10)->timeout(30)->withHeaders(['User-Agent' => 'aiku-cache-warmer'])->get($url),
                $chunk
            ));

            $previousProcessed = $processed;
            $processed         += count($chunk);
            echo "[$processed/$total] {$chunk[0]}\n";

            if (intdiv($previousProcessed, 100) !== intdiv($processed, 100)) {
                $this->crawl->update(['urls_processed' => $processed]);
            }
        }

        $this->finish('completed', $processed, $total);
    }

    /**
     * Most-visited pages first; seeder crawls only warm the head of the traffic
     * distribution, full crawls warm every page with a visit in the window.
     *
     * @return array<int, string>
     */
    protected function getPrioritisedUrls(Crawl $crawl): array
    {
        $pages = DB::connection('aiku_no_sticky')->table('webpages')
            ->join('website_page_views', 'website_page_views.webpage_id', '=', 'webpages.id')
            ->leftJoin('website_visitors', 'website_visitors.id', '=', 'website_page_views.website_visitor_id')
            ->where('webpages.website_id', $crawl->website_id)
            ->where('webpages.state', WebpageStateEnum::LIVE)
            ->whereNotNull('webpages.canonical_url')
            ->where('website_page_views.created_at', '>=', now()->subDays(30))
            ->groupBy('webpages.id', 'webpages.canonical_url')
            ->orderByRaw('sum(case when website_visitors.web_user_id is not null then 3 else 1 end) desc, webpages.id')
            ->select('webpages.canonical_url', DB::raw('sum(case when website_visitors.web_user_id is not null then 3 else 1 end) as views'))
            ->get();

        if ($pages->isEmpty()) {
            $storefrontUrl = $crawl->website->storefront?->canonical_url;

            return $storefrontUrl ? [$storefrontUrl] : [];
        }

        if (!$crawl->is_seeder) {
            return $pages->pluck('canonical_url')->all();
        }

        $totalViews = $pages->sum('views');
        $cumulative = 0;
        $urls       = [];
        foreach ($pages as $page) {
            $urls[]     = $page->canonical_url;
            $cumulative += $page->views;
            if ($cumulative >= $totalViews * 0.8) {
                break;
            }
        }

        return $urls;
    }

    protected function finish(string $reason, int $processed, int $total): void
    {
        $this->crawl->update(
            [
                'state'          => CrawlStateEnum::FINISH,
                'end_at'         => now(),
                'running'        => false,
                'finish_reason'  => $reason,
                'urls_processed' => $processed,
                'urls_found'     => $total
            ]
        );
    }

    protected function protectFromSurges(Crawl $crawl): Crawl
    {
        $totalCrawlInstances = (int)Crawl::where('running', true)->sum('concurrency');

        $available     = 16 - $totalCrawlInstances;
        $realAvailable = $available;

        if ($available < 1) {
            $available = 1;
        } elseif ($available <= 5) {
            $available = 2;
        }

        $concurrency = min($available, $crawl->concurrency);
        if ($realAvailable > 12) {
            $concurrency = max(6, $crawl->concurrency);
        } elseif ($realAvailable > 10) {
            $concurrency = max(4, $crawl->concurrency);
        } elseif ($realAvailable > 8) {
            $concurrency = max(3, $crawl->concurrency);
        } elseif ($realAvailable > 4) {
            $concurrency = max(2, $crawl->concurrency);
        }


        $crawl->update(
            [
                'concurrency' => $concurrency
            ]
        );

        return $crawl;
    }

    protected function stopCurrentCrawls(Crawl $crawl): void
    {
        foreach (
            Crawl::where('state', '!=', CrawlStateEnum::FINISH)
                ->where('id', '!=', $crawl->id)
                ->where('type', $crawl->type)
                ->where('is_seeder', false)
                ->where('website_id', $crawl->website_id)->get() as $crawlToStop
        ) {
            StopCrawl::run($crawlToStop);
        }
    }

    protected function checkIfShouldStop(): void
    {
        $shouldStop = Cache::remember(
            "crawl.{$this->crawl->id}.should_stop",
            now()->addMinutes(5),
            function () {
                $crawl = DB::table('crawls')->select('should_stop')->where('id', $this->crawl->id)->first();

                return !$crawl || $crawl->should_stop;
            }
        );

        if ($shouldStop) {
            $this->shouldStop = true;
        }
    }

    public function getCommandSignature(): string
    {
        return 'crawl {website?} {--c|concurrency=10} {--deployment} {--s|seeder}';
    }


    public function asCommand(Command $command): int
    {
        $trigger = $command->option('deployment') ? CrawlTriggerEnum::DEPLOYMENT : CrawlTriggerEnum::COMMAND;

        if ($command->argument('website')) {
            $website = Website::where('slug', $command->argument('website'))->firstOrFail();
            $command->info("Warming website: $website->slug (ID: $website->id) Concurrency: {$command->option('concurrency')}");
            /** @var Crawl $crawl */
            $crawl = $website->crawls()->create(
                [
                    'depth'       => 0,
                    'concurrency' => $command->option('concurrency'),
                    'trigger'     => $trigger,
                    'type'        => CrawlTypeEnum::HTML,
                    'is_seeder'   => $command->option('seeder')
                ]
            );

            $this->handle($crawl->id);

            return 0;
        }

        CrawlWebsites::run($trigger, $command->option('seeder'), $command);

        return 0;
    }
}
