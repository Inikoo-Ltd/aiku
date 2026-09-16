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
 * Keywords that have been paid for over a long stretch and have never sold anything.
 *
 * The window is deliberately three times the one used for search terms. A keyword accumulates clicks
 * slowly, and judging one on a month of data mostly finds keywords that happened to be quiet in that
 * month. Ninety days is long enough that silence means something.
 *
 * Pausing rather than removing, always: a paused keyword keeps its history and can be switched back
 * on, and a removed one cannot be recovered through the API or through Google's own interface.
 */
class DetectDeadKeywords
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

            /* Only a keyword that is currently serving is worth pausing. */
            if ($row['status'] !== 'ENABLED') {
                continue;
            }

            $campaign = $campaigns->get($row['campaign_reference']);

            if (!$campaign) {
                continue;
            }

            $candidates[] = [
                'type'        => AdProposalTypeEnum::PAUSE_KEYWORD,
                'campaign_id' => $campaign->id,
                'fingerprint' => AdProposalFingerprint::run(
                    AdProposalTypeEnum::PAUSE_KEYWORD,
                    [$campaign->reference, $row['ad_group_id'], $row['criterion_id']]
                ),
                'subject' => $row['text'],
                'payload' => [
                    'type'        => 'keyword',
                    'ad_group_id' => $row['ad_group_id'],
                    'element_id'  => $row['criterion_id'],
                    'status'      => 'PAUSED',
                ],
                'evidence' => [
                    'campaign'    => $campaign->name,
                    'keyword'     => $row['text'],
                    'match_type'  => $row['match_type'],
                    'clicks'      => $row['clicks'],
                    'cost'        => round($row['cost'], 2),
                    'conversions' => 0,
                    'currency'    => data_get($campaign->data, 'currency'),
                    'from'        => $window[0],
                    'to'          => $window[1],
                    'rule'        => "at least {$minClicks} clicks over 90 days and no conversions",
                ],
                'amount' => round($row['cost'], 2),
            ];
        }

        return $candidates;
    }
}
