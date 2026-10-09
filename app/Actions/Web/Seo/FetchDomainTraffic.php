<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use App\Models\Web\SeoDomainTraffic;
use App\Models\Web\Website;
use App\Services\DataForSeo\DataForSeoClient;
use App\Services\DataForSeo\DataForSeoException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The monthly Google search traffic of domains in one market, back to October 2020, from DataForSEO
 * Labs (historical bulk traffic estimation). One request takes up to 1,000 domains and costs the
 * same for the whole history as for the last month, so every fetch reads it all. Estimated from
 * rankings and search volumes, like the rest of Labs: not a measurement.
 */
class FetchDomainTraffic
{
    use AsObject;

    public const int FRESH_DAYS = 25;

    public const string HISTORY_FROM = '2020-10-01';

    private const int DOMAINS_PER_REQUEST = 1000;

    private const array TYPES = ['organic', 'paid', 'featured_snippet', 'local_pack'];

    /**
     * Fetches the domains of the list not fetched for this market in the last `FRESH_DAYS` days and
     * returns how many were fetched.
     *
     * @param  array<int, string>  $domains
     * @throws DataForSeoException
     * @throws ValidationException
     */
    public function handle(array $domains, string $countryCode, string $languageCode, ?Website $website = null): int
    {
        $client = DataForSeoClient::make();
        $domains = collect($domains)->map(fn ($domain) => StoreSerpResult::normaliseDomain($domain))->filter()->unique()->values();

        if (!$client || $domains->isEmpty()) {
            return 0;
        }

        [$locationCode, $labsLanguageCode] = GetKeywordIdeas::make()->location($client, $countryCode, $languageCode);
        $languageCode                      = strtolower($languageCode);

        $fresh = SeoDomainTraffic::query()
            ->whereIn('domain', $domains)
            ->where('country_code', $countryCode)
            ->where('language_code', $languageCode)
            ->where('updated_at', '>=', now()->subDays(self::FRESH_DAYS))
            ->distinct()
            ->pluck('domain');

        $due = $domains->diff($fresh)->values();

        foreach ($due->chunk(self::DOMAINS_PER_REQUEST) as $chunk) {
            $result = Arr::first($client->live('dataforseo_labs/google/historical_bulk_traffic_estimation/live', [
                'targets'       => $chunk->values()->all(),
                'location_code' => $locationCode,
                'language_code' => $labsLanguageCode,
                'date_from'     => self::HISTORY_FROM,
                'item_types'    => self::TYPES,
            ], $website));

            $this->store(Arr::get($result ?? [], 'items') ?? [], $chunk->values()->all(), $countryCode, $languageCode);
        }

        return $due->count();
    }

    /**
     * Stores the months from the first one the domain is seen in. A domain with no traffic at all
     * gets one empty row for the current month, so it is not fetched again until it is stale.
     *
     * @param  array<int, array>  $items
     * @param  array<int, string>  $domains
     */
    public function store(array $items, array $domains, string $countryCode, string $languageCode): void
    {
        $now  = now();
        $rows = [];

        foreach ($items as $item) {
            $domain = StoreSerpResult::normaliseDomain(Arr::get($item, 'target'));
            $months = [];

            foreach (self::TYPES as $type) {
                foreach (Arr::get($item, "metrics.$type") ?? [] as $metric) {
                    $month = sprintf('%04d-%02d-01', $metric['year'], $metric['month']);

                    $months[$month][$type.'_traffic'] = round((float) ($metric['etv'] ?? 0), 2);

                    if (in_array($type, ['organic', 'paid'], true)) {
                        $months[$month][$type.'_keywords'] = (int) ($metric['count'] ?? 0);
                    }
                }
            }

            ksort($months);
            $seen = false;

            foreach ($months as $month => $values) {
                $seen = $seen || ($values['organic_keywords'] ?? 0) > 0 || ($values['paid_keywords'] ?? 0) > 0;

                if (!$seen) {
                    continue;
                }

                $rows[$domain.$month] = [
                    'domain'                   => $domain,
                    'country_code'             => $countryCode,
                    'language_code'            => $languageCode,
                    'month'                    => $month,
                    'organic_traffic'          => $values['organic_traffic'] ?? 0,
                    'organic_keywords'         => $values['organic_keywords'] ?? 0,
                    'paid_traffic'             => $values['paid_traffic'] ?? 0,
                    'paid_keywords'            => $values['paid_keywords'] ?? 0,
                    'featured_snippet_traffic' => $values['featured_snippet_traffic'] ?? 0,
                    'local_pack_traffic'       => $values['local_pack_traffic'] ?? 0,
                    'created_at'               => $now,
                    'updated_at'               => $now,
                ];
            }
        }

        $stored = collect($rows)->pluck('domain')->unique();

        foreach (collect($domains)->map(fn ($domain) => StoreSerpResult::normaliseDomain($domain))->diff($stored) as $domain) {
            $rows[$domain] = [
                'domain'                   => $domain,
                'country_code'             => $countryCode,
                'language_code'            => $languageCode,
                'month'                    => today()->startOfMonth()->toDateString(),
                'organic_traffic'          => 0,
                'organic_keywords'         => 0,
                'paid_traffic'             => 0,
                'paid_keywords'            => 0,
                'featured_snippet_traffic' => 0,
                'local_pack_traffic'       => 0,
                'created_at'               => $now,
                'updated_at'               => $now,
            ];
        }

        DB::transaction(function () use ($rows) {
            foreach (array_chunk(array_values($rows), 500) as $chunk) {
                SeoDomainTraffic::upsert($chunk, ['domain', 'country_code', 'language_code', 'month'], [
                    'organic_traffic', 'organic_keywords', 'paid_traffic', 'paid_keywords', 'featured_snippet_traffic', 'local_pack_traffic', 'updated_at',
                ]);
            }
        });
    }
}
