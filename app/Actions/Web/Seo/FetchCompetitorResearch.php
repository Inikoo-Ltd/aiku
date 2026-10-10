<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use App\Enums\Web\Website\WebsiteStateEnum;
use App\Models\Web\Website;
use App\Services\DataForSeo\DataForSeoException;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Keeps the ranked keywords of every live website and its shop's competitors at most four weeks
 * old, in the shop's own market, for the domain comparison and the keyword gap.
 */
class FetchCompetitorResearch
{
    use AsAction;

    public string $commandSignature = 'seo:fetch_domain_keywords {website? : Website slug}';

    public string $commandDescription = 'Fetch the ranked keywords of our websites and their competitors that are older than four weeks';

    public string $jobQueue = 'long-low-priority';

    public int $jobTimeout = 7200;

    public int $jobTries = 1;

    /**
     * @throws DataForSeoException
     */
    public function handle(?Website $website = null): int
    {
        $websites = Website::query()
            ->where('state', WebsiteStateEnum::LIVE)
            ->when($website, fn ($query) => $query->where('id', $website->id))
            ->with(['shop.country', 'shop.language', 'shop.seoCompetitors'])
            ->get();

        $fetched = 0;

        foreach ($websites as $liveWebsite) {
            $shop = $liveWebsite->shop;

            if (!$shop?->country || !$shop->language) {
                continue;
            }

            $domains = [$liveWebsite->domain, ...$shop->seoCompetitors->pluck('domain')->all()];

            foreach ($domains as $domain) {
                try {
                    $before = now();
                    $overview = FetchDomainKeywords::run($domain, $shop->country->code, $shop->language->code, $liveWebsite);

                    if ($overview?->updated_at?->gte($before)) {
                        $fetched++;
                    }
                } catch (ValidationException) {
                    break;
                } catch (DataForSeoException $e) {
                    if ($e->isBudgetReached()) {
                        throw $e;
                    }
                }
            }
        }

        return $fetched;
    }

    public function asCommand(Command $command): int
    {
        $website = $command->argument('website') ? Website::where('slug', $command->argument('website'))->firstOrFail() : null;

        try {
            $command->line($this->handle($website).' domains fetched');
        } catch (DataForSeoException $e) {
            $command->error($e->getMessage());

            return 1;
        }

        return 0;
    }
}
