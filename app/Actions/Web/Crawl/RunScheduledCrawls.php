<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 06 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Crawl;

use App\Enums\Web\Crawl\CrawlStateEnum;
use App\Enums\Web\Crawl\CrawlTriggerEnum;
use App\Enums\Web\Crawl\CrawlTypeEnum;
use App\Enums\Web\Website\WebsiteStateEnum;
use App\Models\Web\Crawl;
use App\Models\Web\CrawlSchedule;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Starts the crawls whose schedule is due. A website already being crawled is skipped and booked for
 * its next time, so a long crawl is never stopped by its own schedule.
 */
class RunScheduledCrawls
{
    use AsAction;

    public function handle(): int
    {
        $started = 0;

        CrawlSchedule::with('website')
            ->where('is_active', true)
            ->where('next_run_at', '<=', now())
            ->get()
            ->each(function (CrawlSchedule $schedule) use (&$started) {
                $website = $schedule->website;

                if ($website && $website->state === WebsiteStateEnum::LIVE && !$this->isBeingCrawled($schedule)) {
                    AuditWebsite::dispatch(AuditWebsite::startFor($website, CrawlTriggerEnum::SCHEDULE, user: $schedule->user));
                    $schedule->last_run_at = now();
                    $started++;
                }

                $schedule->next_run_at = $schedule->nextRunAfter(now());
                $schedule->save();
            });

        return $started;
    }

    private function isBeingCrawled(CrawlSchedule $schedule): bool
    {
        $unfinished = Crawl::where('website_id', $schedule->website_id)
            ->where('type', CrawlTypeEnum::AUDIT)
            ->where('state', '!=', CrawlStateEnum::FINISH)
            ->latest('id')
            ->first();

        return $unfinished !== null && !AuditWebsite::closeIfAbandoned($unfinished);
    }

    public function getCommandSignature(): string
    {
        return 'crawl:scheduled';
    }

    public function asCommand(Command $command): int
    {
        $command->line($this->handle().' scheduled crawls started');

        return 0;
    }
}
