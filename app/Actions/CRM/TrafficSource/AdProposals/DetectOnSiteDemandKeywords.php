<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\TrafficSource\AdProposals;

use App\Enums\CRM\TrafficSource\AdProposalTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\TrafficSourceCampaign;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * What visitors look for on the shop's own website, and nobody is bidding on in Google.
 *
 * This is the one signal in the set that owes nothing to Google. People arriving on the site and
 * searching it are saying what they came for in their own words, and the catalogue already proves the
 * shop can sell it. Marketing has never had a route from that to a keyword.
 *
 * Counted by session rather than by search on purpose. One customer looking up the same product code
 * eighteen times is eighteen searches and one person, and by searches those rows crowd out genuine
 * demand.
 *
 * Searches that are exactly a product code are then dropped outright against the catalogue. That was
 * first left to the model to spot and it did not: `gas-06` and `eid-04` came back recommended. The
 * catalogue already knows every code, so this belongs in a query, and the model is left the judgement
 * a query genuinely cannot make.
 */
class DetectOnSiteDemandKeywords
{
    use AsAction;

    private const int DAYS = 90;

    private const int MIN_SESSIONS = 5;

    /** Two characters is a size or an abbreviation, not something to bid on. */
    private const int MIN_LENGTH = 4;

    /**
     * @param Collection<string, TrafficSourceCampaign> $campaigns
     * @return array<int, array>
     */
    public function handle(Shop $shop, Collection $campaigns): array
    {
        /* Only Search campaigns can take a keyword, and only one with an ad group has somewhere to put
           it. Picking the busiest by keyword count: it is the one already built around search. */
        $campaign = $campaigns
            ->filter(fn (TrafficSourceCampaign $c) => filled(Arr::get($c->data, 'ad_groups', [])))
            ->sortByDesc(fn (TrafficSourceCampaign $c) => collect(Arr::get($c->data, 'ad_groups', []))
                ->sum(fn ($group) => count(Arr::get($group, 'keywords', []))))
            ->first();

        if (!$campaign) {
            return [];
        }

        $existing     = $this->existingKeywords($campaigns);
        $productCodes = $this->productCodes($shop);
        $adGroups     = Arr::get($campaign->data, 'ad_groups', []);

        $rows = DB::table('website_search_logs')
            ->where('shop_id', $shop->id)
            ->where('created_at', '>=', now()->subDays(self::DAYS))
            ->where('results_count', '>', 0)
            ->whereRaw('length(trim(query)) >= ?', [self::MIN_LENGTH])
            ->groupByRaw('lower(trim(query))')
            ->havingRaw('count(distinct session_id) >= ?', [self::MIN_SESSIONS])
            ->orderByRaw('count(distinct session_id) desc')
            ->limit(60)
            ->get([
                DB::raw('lower(trim(query)) as term'),
                DB::raw('count(*) as searches'),
                DB::raw('count(distinct session_id) as sessions'),
                DB::raw('count(*) filter (where clicked_at is not null) as clicked'),
            ]);

        $candidates = [];

        foreach ($rows as $row) {
            if ($existing->has($row->term)) {
                continue;
            }

            /* A search that is exactly a product code is an existing customer looking up something
               they already know, not somebody discovering the shop. Nobody types `gas-06` into Google.
               Caught by matching the catalogue rather than by guessing at the shape of a code, because
               the catalogue knows and a pattern would also catch real phrases. */
            if ($productCodes->has($row->term)) {
                continue;
            }

            $candidates[] = [
                'type'        => AdProposalTypeEnum::ADD_DEMAND_KEYWORD,
                'campaign_id' => $campaign->id,
                'fingerprint' => AdProposalFingerprint::run(
                    AdProposalTypeEnum::ADD_DEMAND_KEYWORD,
                    [$campaign->reference, $row->term]
                ),
                'subject' => $row->term,
                'payload' => [
                    'ad_group_id' => SuggestAdGroupForKeyword::run($row->term, $adGroups),
                    'text'        => $row->term,
                    'match_type'  => 'PHRASE',
                ],
                'evidence' => [
                    'campaign' => $campaign->name,
                    'term'     => $row->term,
                    'searches' => (int) $row->searches,
                    'sessions' => (int) $row->sessions,
                    'clicked'  => (int) $row->clicked,
                    'currency' => data_get($campaign->data, 'currency'),
                    'days'     => self::DAYS,
                    'rule'     => 'at least '.self::MIN_SESSIONS.' different visitors searched your site for this and found products',
                    'ad_groups' => collect($adGroups)
                        ->map(fn ($group) => ['id' => (string) Arr::get($group, 'id'), 'name' => Arr::get($group, 'name')])
                        ->values()
                        ->all(),
                ],

                /* No spend to rank by, so sessions stand in. Kept out of the money columns on the card
                   for the same reason: this number is people, not pounds. */
                'amount' => 0,
                'rank'   => (int) $row->sessions,
            ];
        }

        return $candidates;
    }

    /**
     * Every product code in the shop, lowercased, for recognising a lookup.
     *
     * @return Collection<string, bool>
     */
    private function productCodes(Shop $shop): Collection
    {
        return DB::table('products')
            ->where('shop_id', $shop->id)
            ->whereNotNull('code')
            ->pluck('code')
            ->map(fn ($code) => mb_strtolower(trim((string) $code)))
            ->flip();
    }

    /**
     * Every keyword already in the account, lowercased, so a term nobody bids on can be told from one
     * already covered. Exact text only: judging whether `candles` is covered by `scented candles` is
     * the sort of call left to the model.
     *
     * @param Collection<string, TrafficSourceCampaign> $campaigns
     * @return Collection<string, bool>
     */
    private function existingKeywords(Collection $campaigns): Collection
    {
        return $campaigns
            ->flatMap(fn (TrafficSourceCampaign $campaign) => collect(Arr::get($campaign->data, 'ad_groups', []))
                ->flatMap(fn ($group) => collect(Arr::get($group, 'keywords', []))->pluck('text')))
            ->filter()
            ->map(fn ($text) => mb_strtolower(trim($text)))
            ->flip();
    }
}
