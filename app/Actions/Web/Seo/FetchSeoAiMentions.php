<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use App\Enums\Web\Website\WebsiteStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Web\SeoAiMention;
use App\Models\Web\SeoCompetitor;
use App\Services\DataForSeo\DataForSeoClient;
use App\Services\DataForSeo\DataForSeoException;
use App\Services\DataForSeo\DataForSeoLocations;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Once a month, how often the AI answers in DataForSEO's LLM Mentions database mention our domain and
 * each competitor's: Google AI Overviews in the shop's market, and ChatGPT for shops in English (that
 * data is United States and English only). One request per shop and platform covers all its domains.
 */
class FetchSeoAiMentions
{
    use AsAction;

    public const string GOOGLE = 'google';

    public const string CHAT_GPT = 'chat_gpt';

    private const int MAX_TARGETS = 10;

    public const int US_LOCATION_CODE = 2840;

    public string $commandSignature = 'seo:fetch_ai_mentions {shop? : slug of one shop}';

    public string $commandDescription = 'Fetch how often AI answers mention our domains and the competitors, from DataForSEO LLM Mentions';

    public string $jobQueue = 'long-low-priority';

    public int $jobTimeout = 3600;

    public int $jobTries = 1;

    /**
     * @throws DataForSeoException
     */
    public function handle(?Shop $shop = null, bool $onlyWhenMissing = false): int
    {
        $client = DataForSeoClient::make();

        if (!$client) {
            return 0;
        }

        $markets = DataForSeoLocations::forLlmMentions($client);
        $fetched = 0;

        $shops = Shop::query()
            ->whereHas('website', fn (Builder $query) => $query->where('state', WebsiteStateEnum::LIVE))
            ->when($shop, fn (Builder $query) => $query->where('id', $shop->id))
            ->with(['website', 'country', 'language', 'seoCompetitors'])
            ->get();

        foreach ($shops as $eachShop) {
            if ($onlyWhenMissing && SeoAiMention::where('shop_id', $eachShop->id)->where('date', '>=', today()->startOfMonth())->exists()) {
                continue;
            }

            foreach ($this->platforms($client, $eachShop, $markets) as $platform => [$locationCode, $languageCode]) {
                try {
                    $this->fetch($client, $eachShop, $platform, $locationCode, $languageCode);
                    $fetched++;
                } catch (DataForSeoException $e) {
                    if ($e->isBudgetReached()) {
                        throw $e;
                    }
                }
            }
        }

        return $fetched;
    }

    /**
     * @param  array<int, array<string, array<int, string>>>  $markets
     * @return array<string, array{0: int, 1: string}>
     * @throws DataForSeoException
     */
    private function platforms(DataForSeoClient $client, Shop $shop, array $markets): array
    {
        $languageCode = strtolower((string) $shop->language?->code);
        $location     = $shop->country ? DataForSeoLocations::forCountry($client, $shop->country->code) : null;
        $platforms    = [];

        if ($location && in_array(self::GOOGLE, $markets[$location['location_code']][$languageCode] ?? [], true)) {
            $platforms[self::GOOGLE] = [$location['location_code'], $languageCode];
        }

        if ($languageCode === 'en') {
            $platforms[self::CHAT_GPT] = [self::US_LOCATION_CODE, 'en'];
        }

        return $platforms;
    }

    /**
     * @throws DataForSeoException
     */
    private function fetch(DataForSeoClient $client, Shop $shop, string $platform, int $locationCode, string $languageCode): void
    {
        $domains = collect([['domain' => StoreSerpResult::normaliseDomain($shop->website->domain), 'competitor_id' => null]])
            ->merge($shop->seoCompetitors->map(fn (SeoCompetitor $competitor) => ['domain' => StoreSerpResult::normaliseDomain($competitor->domain), 'competitor_id' => $competitor->id]))
            ->filter(fn (array $domain) => $domain['domain'] !== '')
            ->unique('domain')
            ->take(self::MAX_TARGETS)
            ->values();

        $market = ['platform' => $platform, 'location_code' => $locationCode, 'language_code' => $languageCode];

        $totals = $domains->count() > 1
            ? $this->multiTarget($client, $shop, $domains, $market)
            : $this->singleTarget($client, $shop, $domains->first()['domain'], $market);

        $date = today()->toDateString();
        $now  = now();

        SeoAiMention::upsert($domains->map(fn (array $domain) => [
            'shop_id'          => $shop->id,
            'date'             => $date,
            'platform'         => $platform,
            'location_code'    => $locationCode,
            'language_code'    => $languageCode,
            'domain'           => $domain['domain'],
            'competitor_id'    => $domain['competitor_id'],
            'mentions'         => (int) ($totals[$domain['domain']]['mentions'] ?? 0),
            'ai_search_volume' => (int) ($totals[$domain['domain']]['ai_search_volume'] ?? 0),
            'created_at'       => $now,
            'updated_at'       => $now,
        ])->all(), ['shop_id', 'platform', 'date', 'domain'], ['location_code', 'language_code', 'competitor_id', 'mentions', 'ai_search_volume', 'updated_at']);
    }

    /**
     * @return array<string, array{mentions: int, ai_search_volume: int}>
     * @throws DataForSeoException
     */
    private function multiTarget(DataForSeoClient $client, Shop $shop, Collection $domains, array $market): array
    {
        $result = Arr::first($client->live('ai_optimization/llm_mentions/multi_target_metrics/live', [
            ...$market,
            'targets'             => $domains->map(fn (array $domain) => ['key' => $domain['domain'], 'target' => [['domain' => $domain['domain']]]])->all(),
            'internal_list_limit' => 1,
        ], $shop->website));

        return collect(Arr::get($result, 'items') ?? [])
            ->mapWithKeys(fn (array $item) => [(string) $item['key'] => [
                'mentions'         => (int) Arr::get($item, 'total.mentions', 0),
                'ai_search_volume' => (int) Arr::get($item, 'total.ai_search_volume', 0),
            ]])
            ->all();
    }

    /**
     * @return array<string, array{mentions: int, ai_search_volume: int}>
     * @throws DataForSeoException
     */
    private function singleTarget(DataForSeoClient $client, Shop $shop, string $domain, array $market): array
    {
        $result = Arr::first($client->live('ai_optimization/llm_mentions/aggregated_metrics/live', [
            ...$market,
            'target'              => [['domain' => $domain]],
            'internal_list_limit' => 1,
        ], $shop->website));

        $platform = collect(Arr::get($result, 'total.platform') ?? []);

        return [$domain => [
            'mentions'         => (int) $platform->sum('mentions'),
            'ai_search_volume' => (int) $platform->sum('ai_search_volume'),
        ]];
    }

    public function asCommand(Command $command): int
    {
        $shop = $command->argument('shop') ? Shop::where('slug', $command->argument('shop'))->firstOrFail() : null;

        try {
            $command->line($this->handle($shop).' requests made');
        } catch (DataForSeoException $e) {
            $command->error($e->getMessage());

            return 1;
        }

        return 0;
    }
}
