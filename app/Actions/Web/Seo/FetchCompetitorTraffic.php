<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use App\Enums\Web\Website\WebsiteStateEnum;
use App\Models\Catalogue\Shop;
use App\Services\DataForSeo\DataForSeoException;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Once a month, the Google search traffic of our live websites and their competitors with its
 * history, one request per market (country and language) for all the shops in it.
 */
class FetchCompetitorTraffic
{
    use AsAction;

    public string $commandSignature = 'seo:fetch_competitor_traffic';

    public string $commandDescription = 'Fetch the monthly search traffic of our websites and their competitors from DataForSEO Labs';

    public string $jobQueue = 'long-low-priority';

    public int $jobTimeout = 3600;

    public int $jobTries = 1;

    /**
     * @throws DataForSeoException
     */
    public function handle(): int
    {
        $fetched = 0;

        $markets = Shop::query()
            ->whereHas('website', fn (Builder $query) => $query->where('state', WebsiteStateEnum::LIVE))
            ->whereNotNull('country_id')
            ->whereNotNull('language_id')
            ->with(['website', 'country', 'language', 'seoCompetitors'])
            ->get()
            ->groupBy(fn (Shop $shop) => $shop->country->code.'|'.strtolower($shop->language->code));

        foreach ($markets as $shops) {
            /** @var Collection<int, Shop> $shops */
            $first   = $shops->first();
            $domains = $shops->flatMap(fn (Shop $shop) => [$shop->website->domain, ...$shop->seoCompetitors->pluck('domain')->all()])->all();

            try {
                $fetched += FetchDomainTraffic::run($domains, $first->country->code, $first->language->code, $first->website);
            } catch (ValidationException) {
                continue;
            } catch (DataForSeoException $e) {
                if ($e->isBudgetReached()) {
                    throw $e;
                }
            }
        }

        return $fetched;
    }

    public function asCommand(Command $command): int
    {
        try {
            $command->line($this->handle().' domains fetched');
        } catch (DataForSeoException $e) {
            $command->error($e->getMessage());

            return 1;
        }

        return 0;
    }
}
