<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\TrafficSource\AdProposals;

use App\Enums\CRM\TrafficSource\AdProposalTypeEnum;
use App\Models\CRM\TrafficSourceCampaign;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Searches that sold something while nobody was bidding on them directly.
 *
 * Google reaches these through a broad or phrase keyword that happens to cover them, which means the
 * match is at Google's discretion and can stop at any time. Bidding on the term itself makes the sale
 * deliberate rather than incidental.
 *
 * `amount` here is the spend the term already attracted, not a prediction. Nothing in this feature
 * forecasts: the queue is ordered by money that has actually moved.
 */
class DetectConvertingSearchTerms
{
    use AsAction;

    /**
     * @param array<string, array> $totals
     * @param Collection<string, TrafficSourceCampaign> $campaigns
     * @param array{0: string, 1: string} $window
     * @return array<int, array>
     */
    public function handle(array $totals, Collection $campaigns, array $window): array
    {
        $candidates = [];

        foreach ($totals as $row) {
            /* NONE is Google's word for "this is not one of your keywords and you have not excluded
               it". ADDED means somebody already bids on it, EXCLUDED means they decided against it. */
            if ($row['conversions'] <= 0 || $row['status'] !== 'NONE') {
                continue;
            }

            $campaign = $campaigns->get($row['campaign_reference']);

            if (!$campaign) {
                continue;
            }

            /* A keyword has to live in an ad group, and a campaign with none has nowhere to put it.
               Performance Max campaigns are the usual case: they carry no ad groups of this kind. */
            $adGroups = Arr::get($campaign->data, 'ad_groups', []);

            if ($adGroups === []) {
                continue;
            }

            $candidates[] = [
                'type'        => AdProposalTypeEnum::ADD_SEARCH_TERM_KEYWORD,
                'campaign_id' => $campaign->id,
                'fingerprint' => AdProposalFingerprint::run(
                    AdProposalTypeEnum::ADD_SEARCH_TERM_KEYWORD,
                    [$campaign->reference, $row['term']]
                ),
                'subject' => $row['term'],
                'payload' => [
                    /* A suggestion, not a decision: the card pre-selects it and the marketer can move
                       it, because a keyword in the wrong ad group spends against the wrong ads. Null
                       when the name gives nothing to go on, which leaves the choice showing. */
                    'ad_group_id' => SuggestAdGroupForKeyword::run($row['term'], $adGroups),
                    'text'        => $row['term'],
                    'match_type'  => 'PHRASE',
                ],
                'evidence' => [
                    'campaign'    => $campaign->name,
                    'term'        => $row['term'],
                    'clicks'      => $row['clicks'],
                    'cost'        => round($row['cost'], 2),
                    'conversions' => round($row['conversions'], 2),
                    'currency'    => data_get($campaign->data, 'currency'),
                    'from'        => $window[0],
                    'to'          => $window[1],
                    'rule'        => 'converted at least once and is not one of your keywords',
                    'ad_groups'   => collect($adGroups)
                        ->map(fn ($group) => ['id' => (string) Arr::get($group, 'id'), 'name' => Arr::get($group, 'name')])
                        ->values()
                        ->all(),
                ],
                'amount' => round($row['cost'], 2),
            ];
        }

        return $candidates;
    }
}
