<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use App\Models\Catalogue\Shop;
use App\Models\Web\SeoTrackedKeyword;
use App\Services\DataForSeo\DataForSeoClient;
use App\Services\DataForSeo\DataForSeoException;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Keeps volume, difficulty and intent of every active tracked keyword at most a month old in
 * `seo_keywords`, through Labs keyword_overview (up to 700 keywords per request). Runs daily, so a
 * keyword added by hand gets its figures the next day.
 */
class RefreshTrackedKeywordVolumes
{
    use AsAction;

    public const int REFRESH_DAYS = 30;

    private const int KEYWORDS_PER_REQUEST = 700;

    public string $commandSignature = 'seo:refresh_keyword_volumes';

    public string $commandDescription = 'Refresh volume, difficulty and intent of the tracked keywords older than a month';

    public string $jobQueue = 'long-low-priority';

    public int $jobTimeout = 3600;

    public int $jobTries = 1;

    /**
     * @throws DataForSeoException
     */
    public function handle(?Shop $shop = null): int
    {
        $client = DataForSeoClient::make();

        if (!$client) {
            return 0;
        }

        $refreshed = 0;

        $groups = SeoTrackedKeyword::query()
            ->where('seo_tracked_keywords.is_active', true)
            ->when($shop, fn ($query) => $query->where('seo_tracked_keywords.shop_id', $shop->id))
            ->leftJoin('seo_keywords', function ($join) {
                $join->on('seo_keywords.shop_id', '=', 'seo_tracked_keywords.shop_id')
                    ->on('seo_keywords.keyword', '=', 'seo_tracked_keywords.keyword')
                    ->on('seo_keywords.country_code', '=', 'seo_tracked_keywords.country_code')
                    ->on('seo_keywords.language_code', '=', 'seo_tracked_keywords.language_code');
            })
            ->where(fn ($query) => $query
                ->whereNull('seo_keywords.fetched_at')
                ->orWhere('seo_keywords.fetched_at', '<', now()->subDays(self::REFRESH_DAYS)))
            ->distinct()
            ->get(['seo_tracked_keywords.shop_id', 'seo_tracked_keywords.keyword', 'seo_tracked_keywords.country_code', 'seo_tracked_keywords.language_code'])
            ->groupBy(fn (SeoTrackedKeyword $trackedKeyword) => "$trackedKeyword->shop_id|$trackedKeyword->country_code|$trackedKeyword->language_code");

        foreach ($groups as $trackedKeywords) {
            $first = $trackedKeywords->first();
            $shop  = Shop::find($first->shop_id);

            try {
                [$locationCode, $languageCode] = GetKeywordIdeas::make()->location($client, $first->country_code, $first->language_code);
            } catch (ValidationException) {
                continue;
            }

            foreach ($trackedKeywords->pluck('keyword')->unique()->chunk(self::KEYWORDS_PER_REQUEST) as $keywords) {
                $items = Arr::get($client->live('dataforseo_labs/google/keyword_overview/live', [
                    'keywords'          => $keywords->values()->all(),
                    'location_code'     => $locationCode,
                    'language_code'     => $languageCode,
                    'include_serp_info' => false,
                ], $shop->website), '0.items') ?? [];

                $ideas = collect($items)
                    ->map(fn (array $item) => GetKeywordIdeas::idea($item))
                    ->keyBy('keyword');

                foreach ($keywords as $keyword) {
                    $ideas[$keyword] ??= GetKeywordIdeas::idea(['keyword' => $keyword]);
                }

                $ideas = $ideas->values()->all();

                GetKeywordIdeas::make()->store($shop, $ideas, $first->country_code, $first->language_code);

                $refreshed += count($ideas);
            }
        }

        return $refreshed;
    }

    public function asCommand(Command $command): int
    {
        try {
            $command->line($this->handle().' keywords refreshed');
        } catch (DataForSeoException $e) {
            $command->error($e->getMessage());

            return 1;
        }

        return 0;
    }
}
