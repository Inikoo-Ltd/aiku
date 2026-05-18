<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 13 May 2026 20:16:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Crawl;

use App\Enums\Web\Crawl\CrawlTriggerEnum;
use App\Enums\Web\Crawl\CrawlTypeEnum;
use App\Models\Web\Crawl;
use App\Models\Web\Website;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class CrawlWebsites
{
    use AsAction;


    public function handle(CrawlTypeEnum $type, CrawlTriggerEnum $trigger, int $depth, bool $isSeeder, ?Command $command = null): void
    {
        $index = 0;
        /** @var Website $website */
        foreach (
            Website::query()
                ->where('migrated', true)
                ->withMax('webStats', 'number_visitors_last_24_hours')
                ->orderByDesc('web_stats_max_number_visitors_last_24_hours')
                ->get() as $website
        ) {
            /** @var Crawl $crawl */
            $crawl = $website->crawls()->create(
                [
                    'depth' => $depth,

                    'concurrency' => match ($index) {
                        0 => 5,
                        1 => 3,
                        2, 3 => 2,
                        default => 1
                    },
                    'trigger'     => $trigger,
                    'type'        => $type,
                    'is_seeder'   => $isSeeder
                ]
            );
            $command?->info("Crawling website: $website->slug ; C: ".$crawl->concurrency);

            $index++;
            $jobQueue = 'cache-warming';
            if ($crawl->type == CrawlTypeEnum::INERTIA) {
                $jobQueue = 'cache-warming-js';
            }
            CrawlWebsite::dispatch($crawl->id)->onQueue($jobQueue);
        }
    }

}
