<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 13 May 2026 20:16:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website;

use App\Models\Web\Website;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\Crawler\Crawler;
use Spatie\Crawler\CrawlProgress;
use Spatie\Crawler\CrawlResponse;

class CrawlWebsite
{
    use AsAction;

    public string $jobQueue = 'low-priority';

    public function handle(?int $websiteId, int $depth = 10): void
    {
        if (!app()->environment('production')) {
            return;
        }

        if (!$websiteId) {
            return;
        }

        $website = Website::find($websiteId);

        if (!$website) {
            return;
        }

        Crawler::create($website->storefront->canonical_url)
            ->internalOnly()
            ->depth($depth)
            ->shouldCrawl(function (string $url) {
                return !str_contains($url, '/app') && !str_contains($url, '/search');
            })
            ->onCrawled(function (string $url, CrawlResponse $response, CrawlProgress $progress) {
                echo "[$progress->urlsProcessed/$progress->urlsFound] $url\n";
            })
            ->start();
    }

    public function getCommandSignature(): string
    {
        return 'website:crawl {website?}';
    }

    public function asCommand(Command $command): int
    {
        if ($command->argument('website')) {
            $website = Website::where('slug', $command->argument('website'))->firstOrFail();
            $this->handle($website->id);

            return 0;
        }

        return 0;
    }
}
