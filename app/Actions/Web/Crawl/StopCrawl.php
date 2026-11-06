<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 14 May 2026 13:04:50 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Crawl;

use App\Enums\Web\Crawl\CrawlStateEnum;
use App\Models\Web\Crawl;
use App\Models\Web\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

class StopCrawl
{
    use AsAction;

    public function handle(Crawl $crawl): void
    {
        $crawl->update(
            [
                'should_stop' => true
            ]
        );

        Cache::put("crawl.{$crawl->id}.should_stop", true, now()->addMinutes(5));

        if ($crawl->state == CrawlStateEnum::READY) {
            $crawl->update(
                [
                    'state'         => CrawlStateEnum::FINISH,
                    'end_at'        => now(),
                    'running'       => false,
                    'finish_reason' => 'interrupted',
                ]
            );
        }
    }

    public function getCommandSignature(): string
    {
        return 'crawl:stop {website?}';
    }

    public function asCommand(Command $command): int
    {
        $websiteIds = [];
        if ($command->argument('website')) {
            $website      = Website::where('slug', $command->argument('website'))->firstOrFail();
            $websiteIds[] = $website->id;
        } else {
            $websiteIds = Website::where('migrated', true)->pluck('id')->toArray();
        }

        /** @var Crawl $crawl */
        foreach (Crawl::where('state', '!=', CrawlStateEnum::FINISH)->whereIn('website_id', $websiteIds)->get() as $crawl) {
            $command->info("Stopping crawl: ID {$crawl->id} Website ({$crawl->website->domain}) ");
            $this->handle($crawl);
        }


        return 0;
    }

}
