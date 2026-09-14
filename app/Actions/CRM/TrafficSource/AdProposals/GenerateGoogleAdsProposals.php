<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\TrafficSource\AdProposals;

use App\Enums\CRM\TrafficSource\AdProposalStateEnum;
use App\Enums\CRM\TrafficSource\AdProposalTypeEnum;
use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\TrafficSource;
use App\Models\CRM\TrafficSourceAdProposal;
use App\Models\CRM\TrafficSourceCampaign;
use App\Services\GoogleAds\GoogleAdsClient;
use App\Services\GoogleAds\GoogleAdsException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

/**
 * Works out what is worth changing in a shop's Google Ads account, and files each one for a person to
 * approve.
 *
 * Candidates are found by query, never by the language model. "Three people clicked this and none of
 * them bought" is arithmetic, and a model asked to do arithmetic over hundreds of rows invents
 * numbers, costs a fortune and cannot be tested. The model is handed the shortlist afterwards and
 * asked the one question SQL cannot answer: does this phrase read like somebody who would buy?
 *
 * How far to trust that judgement was settled by trying it. Asked to sort wasteful searches from
 * valuable ones it produced two opposite answers on the same account, the second of which would have
 * excluded `uk dropshipping suppliers` from a wholesale dropshipping supplier. So the types that cut
 * spending are switched off, and only the two that add a keyword are generated: see
 * AdProposalTypeEnum::isEnabled for what has to be true before the others come back.
 *
 * Anything a query can decide is decided by a query, including whether a site search is really a
 * product code. That one was left to the model first and it recommended `gas-06`.
 */
class GenerateGoogleAdsProposals
{
    use AsAction;

    /**
     * Thresholds calibrated against a live account rather than chosen for roundness. On a shop with
     * 532 search terms in 30 days, three clicks yields nine suggestions worth reading and eight yields
     * two, which is not a page worth opening. Keywords accumulate more slowly, so they get a longer
     * window and a higher bar: five clicks over 90 days yields eleven, ten clicks yields one.
     */
    private const int WASTEFUL_TERM_MIN_CLICKS = 3;

    private const int WASTEFUL_TERM_DAYS = 30;

    private const int DEAD_KEYWORD_MIN_CLICKS = 5;

    private const int DEAD_KEYWORD_DAYS = 90;

    /** Per type, so one noisy rule cannot bury the others, and the model's bill stays predictable. */
    private const int MAX_PER_TYPE = 25;

    /**
     * @return array{proposed: int, skipped: int, candidates: int}
     * @throws GoogleAdsException
     */
    public function handle(Shop $shop, bool $dryRun = false): array
    {
        $client = GoogleAdsClient::forShop($shop);

        if (!$client) {
            throw new RuntimeException(GoogleAdsClient::unreachableReason($shop) ?? __('Google Ads is not configured for this shop.'));
        }

        $trafficSource = TrafficSource::where('shop_id', $shop->id)
            ->where('type', TrafficSourcesTypeEnum::GOOGLE_ADS->value)
            ->first();

        if (!$trafficSource) {
            throw new RuntimeException("shop {$shop->slug} has no google-ads traffic source");
        }

        /* Keyed by Google's own campaign id, which is what both reports come back segmented by. */
        $campaigns = TrafficSourceCampaign::where('traffic_source_id', $trafficSource->id)
            ->get()
            ->keyBy('reference');

        $candidates = collect()
            ->concat($this->fromSearchTerms($client, $campaigns))
            ->concat($this->fromKeywords($client, $campaigns))
            ->concat(DetectOnSiteDemandKeywords::run($shop, $campaigns))
            ->concat(DetectThinAds::run($campaigns))
            ->concat(DetectUncoveredDepartments::run($shop, $campaigns))

            /* Disabled types are dropped here rather than at each detector, so switching one back on
               is one boolean and not a hunt through four files. */
            ->filter(fn (array $candidate) => $candidate['type']->isEnabled());

        $total     = $candidates->count();
        $shortlist = $this->shortlist($candidates, $trafficSource);

        if ($shortlist->isEmpty()) {
            return ['proposed' => 0, 'skipped' => $total, 'candidates' => $total];
        }

        /* Two different jobs for the model, so two paths. Judging asks whether a phrase suits the
           business; drafting asks it to write copy. Sending both through one call would mean one
           prompt doing two things, which is how the first version of the judging ended up producing
           the same sentence for every card. */
        [$toDraft, $toJudge] = $shortlist->partition(
            fn (array $candidate) => $candidate['type'] === AdProposalTypeEnum::STRENGTHEN_AD
        );

        $judged = array_merge(
            JudgeAdProposalCandidates::run($shop, $toJudge->values()->all()),
            DraftAdHeadlines::run($shop, $toDraft->values()->all(), JudgeAdProposalCandidates::make()->shopTrade($shop)),
        );

        if ($dryRun) {
            return ['proposed' => count($judged), 'skipped' => $total - count($judged), 'candidates' => $total];
        }

        foreach ($judged as $candidate) {
            TrafficSourceAdProposal::updateOrCreate(
                [
                    'traffic_source_id' => $trafficSource->id,
                    'fingerprint'       => $candidate['fingerprint'],
                ],
                [
                    'group_id'                   => $trafficSource->group_id,
                    'organisation_id'            => $trafficSource->organisation_id,
                    'shop_id'                    => $trafficSource->shop_id,
                    'traffic_source_campaign_id' => $candidate['campaign_id'],
                    'type'                       => $candidate['type'],
                    'state'                      => AdProposalStateEnum::OPEN,
                    'payload'                    => $candidate['payload'],
                    'evidence'                   => $candidate['evidence'],
                    'rationale'                  => $candidate['rationale'] ?? null,
                    'amount'                     => $candidate['amount'],
                ]
            );
        }

        return ['proposed' => count($judged), 'skipped' => $total - count($judged), 'candidates' => $total];
    }

    /**
     * Drops anything already settled, then keeps the most expensive few of each type.
     *
     * The fingerprint lookup is what makes a dismissal permanent: a suggestion turned down once is
     * never raised again, however many times this runs. Without that the queue becomes noise and stops
     * being opened, which is the usual way a feature like this dies.
     *
     * @param Collection<int, array> $candidates
     * @return Collection<int, array>
     */
    private function shortlist(Collection $candidates, TrafficSource $trafficSource): Collection
    {
        $settled = TrafficSourceAdProposal::where('traffic_source_id', $trafficSource->id)
            ->whereIn('state', [
                AdProposalStateEnum::APPLIED->value,
                AdProposalStateEnum::DISMISSED->value,
                AdProposalStateEnum::STALE->value,
            ])
            ->pluck('fingerprint')
            ->flip();

        return $candidates
            ->reject(fn (array $candidate) => $settled->has($candidate['fingerprint']))
            ->groupBy(fn (array $candidate) => $candidate['type']->value)
            ->map(fn (Collection $group) => $group->sortByDesc('amount')->take(self::MAX_PER_TYPE))
            ->flatten(1);
    }

    /**
     * One account wide query rather than one per campaign: 54 campaigns would otherwise be 54 round
     * trips a night for an answer that arrives segmented by campaign anyway.
     *
     * @param Collection<string, TrafficSourceCampaign> $campaigns
     * @return array<int, array>
     * @throws GoogleAdsException
     */
    private function fromSearchTerms(GoogleAdsClient $client, Collection $campaigns): array
    {
        $from = now()->subDays(self::WASTEFUL_TERM_DAYS)->toDateString();
        $to   = now()->toDateString();

        $rows = $client->search(
            "SELECT campaign.id, search_term_view.search_term, search_term_view.status,
                    metrics.clicks, metrics.cost_micros, metrics.conversions
             FROM search_term_view
             WHERE segments.date BETWEEN '{$from}' AND '{$to}'"
        );

        $totals = [];

        foreach ($rows as $row) {
            $campaignReference = (string) data_get($row, 'campaign.id');
            $term              = (string) data_get($row, 'searchTermView.searchTerm');
            $key               = $campaignReference.'|'.$term;

            $totals[$key] ??= [
                'campaign_reference' => $campaignReference,
                'term'               => $term,
                'status'             => (string) data_get($row, 'searchTermView.status', 'NONE'),
                'clicks'             => 0,
                'cost'               => 0.0,
                'conversions'        => 0.0,
            ];

            $totals[$key]['clicks']      += (int) data_get($row, 'metrics.clicks', 0);
            $totals[$key]['cost']        += ((float) data_get($row, 'metrics.costMicros', 0)) / 1_000_000;
            $totals[$key]['conversions'] += (float) data_get($row, 'metrics.conversions', 0);
        }

        $candidates = DetectConvertingSearchTerms::run($totals, $campaigns, [$from, $to]);

        if (AdProposalTypeEnum::EXCLUDE_SEARCH_TERM->isEnabled()) {
            $candidates = array_merge($candidates, DetectWastefulSearchTerms::run($totals, $campaigns, [$from, $to], self::WASTEFUL_TERM_MIN_CLICKS));
        }

        return $candidates;
    }

    /**
     * @param Collection<string, TrafficSourceCampaign> $campaigns
     * @return array<int, array>
     * @throws GoogleAdsException
     */
    private function fromKeywords(GoogleAdsClient $client, Collection $campaigns): array
    {
        /* Skipped entirely rather than filtered afterwards: this report is the expensive one, five
           thousand rows over ninety days, and there is no point paying for it to throw it away. */
        if (!AdProposalTypeEnum::PAUSE_KEYWORD->isEnabled()) {
            return [];
        }

        $from = now()->subDays(self::DEAD_KEYWORD_DAYS)->toDateString();
        $to   = now()->toDateString();

        $rows = $client->search(
            "SELECT campaign.id, ad_group.id, ad_group_criterion.criterion_id,
                    ad_group_criterion.keyword.text, ad_group_criterion.keyword.match_type,
                    ad_group_criterion.status, metrics.clicks, metrics.cost_micros, metrics.conversions
             FROM keyword_view
             WHERE segments.date BETWEEN '{$from}' AND '{$to}'"
        );

        $totals = [];

        foreach ($rows as $row) {
            $key = data_get($row, 'adGroup.id').'|'.data_get($row, 'adGroupCriterion.criterionId');

            $totals[$key] ??= [
                'campaign_reference' => (string) data_get($row, 'campaign.id'),
                'ad_group_id'        => (string) data_get($row, 'adGroup.id'),
                'criterion_id'       => (string) data_get($row, 'adGroupCriterion.criterionId'),
                'text'               => (string) data_get($row, 'adGroupCriterion.keyword.text'),
                'match_type'         => (string) data_get($row, 'adGroupCriterion.keyword.matchType'),
                'status'             => (string) data_get($row, 'adGroupCriterion.status'),
                'clicks'             => 0,
                'cost'               => 0.0,
                'conversions'        => 0.0,
            ];

            $totals[$key]['clicks']      += (int) data_get($row, 'metrics.clicks', 0);
            $totals[$key]['cost']        += ((float) data_get($row, 'metrics.costMicros', 0)) / 1_000_000;
            $totals[$key]['conversions'] += (float) data_get($row, 'metrics.conversions', 0);
        }

        return DetectDeadKeywords::run($totals, $campaigns, [$from, $to], self::DEAD_KEYWORD_MIN_CLICKS);
    }

    /**
     * Every shop the nightly run covers.
     *
     * @return Collection<int, Shop>
     */
    public static function connectedShops(?string $slug = null): Collection
    {
        return Shop::when($slug, fn ($query) => $query->where('slug', $slug))
            ->get()
            ->filter(fn (Shop $shop) => filled(Arr::get($shop->settings, 'google_ads.refresh_token'))
                && filled(Arr::get($shop->settings, 'google_ads.customer_id')));
    }
}
