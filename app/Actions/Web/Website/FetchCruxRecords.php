<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Sep 2026 23:40:00 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website;

use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Website\WebsiteStateEnum;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Google adds a week to the Chrome UX Report every Monday. Every live website is fetched, and every
 * page with enough of our own tracked views to stand a chance of having its own data; any other
 * page is fetched when someone opens it.
 */
class FetchCruxRecords
{
    use AsAction;

    public string $jobQueue = 'cache-warming';

    public const int MIN_PAGE_VIEWS = 50;

    /**
     * A fetch makes up to three calls and the Chrome UX Report allows 150 a minute per project.
     */
    public const int SECONDS_BETWEEN_FETCHES = 2;

    public function handle(): int
    {
        $queued = 0;

        $websites = Website::where('state', WebsiteStateEnum::LIVE)->with('storefront')->get();

        foreach ($websites as $website) {
            FetchCruxHistory::dispatch($website)->delay(now()->addSeconds($queued++ * self::SECONDS_BETWEEN_FETCHES));
        }

        $webpageIds = DB::table('website_page_views')
            ->where('view_date', '>=', now()->subDays(28)->toDateString())
            ->whereIn('website_id', $websites->pluck('id'))
            ->whereNotNull('webpage_id')
            ->groupBy('webpage_id')
            ->havingRaw('count(*) >= ?', [self::MIN_PAGE_VIEWS])
            ->pluck('webpage_id');

        $webpages = Webpage::whereIn('id', $webpageIds)->where('state', WebpageStateEnum::LIVE)->with('website')->get();

        foreach ($webpages as $webpage) {
            FetchCruxHistory::dispatch($webpage->website, $webpage)->delay(now()->addSeconds($queued++ * self::SECONDS_BETWEEN_FETCHES));
        }

        return $queued;
    }

    public function getCommandSignature(): string
    {
        return 'crux:fetch';
    }

    public function getCommandDescription(): string
    {
        return 'Queue the Chrome UX Report real user history of every live website and its most visited pages';
    }

    public function asCommand(Command $command): int
    {
        $queued = $this->handle();

        $command->info("Queued $queued fetches over ".ceil($queued * self::SECONDS_BETWEEN_FETCHES / 60).' minutes');

        return 0;
    }
}
