<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\TrafficSource\AdProposals;

use App\Enums\CRM\TrafficSource\AdProposalTypeEnum;
use App\Models\CRM\TrafficSourceCampaign;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Searches that were clicked and bought nothing.
 *
 * This rule finds the candidates; it does not decide they are waste. A search can be clicked three
 * times without a sale and still be exactly the customer the shop wants, and a phrase can be plainly
 * irrelevant on sight. Separating those two is the model's job, further along. All this says is:
 * money went out here, and nothing came back.
 */
class DetectWastefulSearchTerms
{
    use AsAction;

    /**
     * @param array<string, array> $totals
     * @param Collection<string, TrafficSourceCampaign> $campaigns
     * @param array{0: string, 1: string} $window
     * @return array<int, array>
     */
    public function handle(array $totals, Collection $campaigns, array $window, int $minClicks): array
    {
        $candidates = [];

        foreach ($totals as $row) {
            if ($row['clicks'] < $minClicks || $row['conversions'] > 0) {
                continue;
            }

            /* Already excluded, so there is nothing left to propose; Google keeps reporting the term
               for the days before the exclusion took effect. */
            if ($row['status'] === 'EXCLUDED') {
                continue;
            }

            $campaign = $campaigns->get($row['campaign_reference']);

            if (!$campaign) {
                continue;
            }

            $candidates[] = [
                'type'        => AdProposalTypeEnum::EXCLUDE_SEARCH_TERM,
                'campaign_id' => $campaign->id,
                'fingerprint' => AdProposalFingerprint::run(
                    AdProposalTypeEnum::EXCLUDE_SEARCH_TERM,
                    [$campaign->reference, $row['term']]
                ),
                'subject' => $row['term'],
                'payload' => [
                    'text'       => $row['term'],
                    'match_type' => 'PHRASE',
                ],
                'evidence' => [
                    'campaign'    => $campaign->name,
                    'term'        => $row['term'],
                    'clicks'      => $row['clicks'],
                    'cost'        => round($row['cost'], 2),
                    'conversions' => 0,
                    'currency'    => data_get($campaign->data, 'currency'),
                    'from'        => $window[0],
                    'to'          => $window[1],
                    'rule'        => "at least {$minClicks} clicks and no conversions",
                ],
                'amount' => round($row['cost'], 2),
            ];
        }

        return $candidates;
    }
}
