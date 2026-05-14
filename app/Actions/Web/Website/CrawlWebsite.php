<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 13 May 2026 20:16:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website;

use App\Actions\Web\Crawl\StopCrawl;
use App\Enums\Web\Crawl\CrawlStateEnum;
use App\Enums\Web\Crawl\CrawlTriggerEnum;
use App\Enums\Web\Crawl\CrawlTypeEnum;
use App\Models\Web\Crawl;
use App\Models\Web\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\Crawler\Crawler;
use Spatie\Crawler\CrawlProgress;
use Spatie\Crawler\CrawlResponse;
use Spatie\Crawler\Enums\FinishReason;

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

        $this->stopCurrentCrawls($crawl);

        $crawl = $this->protectFromSurges($crawl);

        $crawl->update(
            [
                'state'    => CrawlStateEnum::RUNNING,
                'start_at' => now(),
                'running'  => true
            ]
        );
        $this->crawl = $crawl;

        $crawler = Crawler::create($this->crawl->website->storefront->canonical_url);
        if ($crawl->type == CrawlTypeEnum::JAVASCRIPT) {
            $crawler->executeJavaScript();
        }

        $crawler->internalOnly()
            ->concurrency($crawl->concurrency)
            ->shouldStopCallback(function () {
                return $this->shouldStop;
            })
            ->depth($crawl->depth)
            ->shouldCrawl($this->shouldCrawlUrl(...))
            ->onCrawled($this->onCrawledUrl(...))
            ->onCrawled($this->checkIfShouldStop(...))
            ->onFinished($this->onFinished(...))
            ->start();
    }


    protected function protectFromSurges(Crawl $crawl): Crawl
    {
        $totalCrawlInstances = (int)Crawl::where('running', true)->sum('concurrency');

        $available = 30 - $totalCrawlInstances;

        if ($available < 1) {
            $available = 1;
        } elseif ($available <= 4) {
            $available = 2;
        }

        $concurrency = min($available, $crawl->concurrency);
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
                ->where('website_id', $crawl->website_id)->get() as $crawlToStop
        ) {
            StopCrawl::run($crawlToStop);
        }
    }

    protected function shouldCrawlUrl(string $url): bool
    {
        $website = $this->crawl->website;
        $domain  = preg_replace('/^www\./i', '', parse_url($url, PHP_URL_HOST));

        return $domain === $website->domain && !str_contains($url, '/app/') && !str_contains($url, '/search');
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

    /** @noinspection PhpUnusedParameterInspection */
    protected function onCrawledUrl(string $url, CrawlResponse $response, CrawlProgress $progress): void
    {
        echo "[$progress->urlsProcessed/$progress->urlsFound] $url\n";
        $this->crawl->update(
            [
                'urls_processed' => $progress->urlsProcessed,
                'urls_found'     => $progress->urlsFound
            ]
        );
    }

    protected function onFinished(FinishReason $reason, CrawlProgress $progress): void
    {
        $this->crawl->update(
            [
                'state'          => CrawlStateEnum::FINISH,
                'end_at'         => now(),
                'running'        => false,
                'finish_reason'  => $reason->value,
                'urls_processed' => $progress->urlsProcessed,
                'urls_found'     => $progress->urlsFound
            ]
        );
    }

    public function getCommandSignature(): string
    {
        return 'crawl {website?} {--d|depth=10} {--c|concurrency=10} {--t|type=html}';
    }


    public function asCommand(Command $command): int
    {
        $type = $command->option('type');
        if (!in_array($type, ['html', 'javascript'])) {
            $command->error("Invalid type option. Accepted values are: html, javascript");

            return 1;
        }

        $crawlType = $type === 'javascript' ? CrawlTypeEnum::JAVASCRIPT : CrawlTypeEnum::HTML;

        if ($command->argument('website')) {
            $website = Website::where('slug', $command->argument('website'))->firstOrFail();
            $command->info("Crawling website: $website->slug (ID: $website->id)");
            $command->info("Depth: {$command->option('depth')}, Concurrency: {$command->option('concurrency')} Type: $crawlType->value");
            /** @var Crawl $crawl */
            $crawl = $website->crawls()->create(
                [
                    'depth'       => $command->option('depth'),
                    'concurrency' => $command->option('concurrency'),
                    'trigger'     => CrawlTriggerEnum::COMMAND,
                    'type'        => $crawlType
                ]
            );

            $this->handle($crawl->id);

            return 0;
        }

        /** @var Website $website */
        foreach (Website::where('migrated', true)->get() as $website) {
            $command->info("Crawling website: $website->slug");
            /** @var Crawl $crawl */
            $crawl = $website->crawls()->create(
                [
                    'depth'       => $command->option('depth'),
                    'concurrency' => 3,
                    'trigger'     => CrawlTriggerEnum::COMMAND,
                    'type'        => $crawlType
                ]
            );

            $jobQueue = 'cache-warming';
            if ($crawl->type == CrawlTypeEnum::JAVASCRIPT) {
                $jobQueue = 'cache-warming-js';
            }

            CrawlWebsite::dispatch($crawl->id)->onQueue($jobQueue);
        }

        return 0;
    }
}
