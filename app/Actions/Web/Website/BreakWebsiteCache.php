<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 24 Jun 2023 13:12:05 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website;

use App\Actions\Helpers\ClearCacheByWildcard;
use App\Actions\OrgAction;
use App\Actions\Web\Crawl\CrawlWebsite;
use App\Actions\Web\Crawl\StopCrawl;
use App\Enums\Web\Crawl\CrawlStateEnum;
use App\Enums\Web\Crawl\CrawlTriggerEnum;
use App\Enums\Web\Crawl\CrawlTypeEnum;
use App\Models\Web\Crawl;
use App\Models\Web\Website;
use Cache;
use Illuminate\Console\Command;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class BreakWebsiteCache extends OrgAction
{
    public function handle(Website $website, ?CrawlTriggerEnum $crawlTrigger, ?Command $command = null): Website
    {
        foreach (
            Crawl::where('state', '!=', CrawlStateEnum::FINISH)
                ->where('type', CrawlTypeEnum::HTML)
                ->where('website_id', $website->id)->get() as $crawlToStop
        ) {
            StopCrawl::run($crawlToStop);
        }

        ClearCacheByWildcard::run(config('iris.cache.webpage_path.prefix').'_domain:*', $command);
        ClearCacheByWildcard::run(config('iris.cache.webpage_path.prefix').'_'.$website->id.'_*', $command);
        ClearCacheByWildcard::run(config('iris.cache.webpage.prefix').'_'.$website->id.'_*', $command);
        ClearCacheByWildcard::run("irisData:website:$website->id:*", $command);

        Cache::forget(config('iris.cache.website.prefix').'_domain:'.$website->domain);

        BreakWebsiteVarnishCache::run($website);

        if ($crawlTrigger != null) {
            $concurrency         = 10;
            $totalCrawlInstances = (int)Crawl::where('running', true)
                ->where('should_stop', false)
                ->sum('concurrency');
            if ($totalCrawlInstances > 10) {
                $concurrency = 5;
            }
            if ($totalCrawlInstances < 5) {
                $concurrency = 15;
            }

            /** @var Crawl $crawl */
            $crawl = $website->crawls()->create(
                [
                    'depth'       => 10,
                    'concurrency' => $concurrency,
                    'trigger'     => $crawlTrigger,
                    'type'        => CrawlTypeEnum::HTML
                ]
            );

            $jobQueue = 'cache-warming';
            if ($crawl->type == CrawlTypeEnum::INERTIA) {
                $jobQueue = 'cache-warming-js';
            }
            CrawlWebsite::dispatch($crawl->id)->onQueue($jobQueue);
        }

        return $website;
    }

    public function asController(Website $website, ActionRequest $request): Website
    {
        $this->initialisationFromShop($website->shop, $request);

        return $this->handle($website, CrawlTriggerEnum::USER);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }

    public function getCommandSignature(): string
    {
        return 'website:break_cache {slug}';
    }

    public function asCommand(Command $command): int
    {
        $website = Website::where('slug', $command->argument('slug'))->first();
        $this->handle($website, CrawlTriggerEnum::COMMAND, $command);

        return 0;
    }

}
