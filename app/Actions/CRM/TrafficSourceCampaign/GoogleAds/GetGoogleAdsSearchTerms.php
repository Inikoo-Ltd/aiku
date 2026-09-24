<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\TrafficSourceCampaign\GoogleAds;

use App\Models\CRM\TrafficSourceCampaign;
use App\Services\GoogleAds\GoogleAdsClient;
use App\Services\GoogleAds\GoogleAdsException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * What people actually typed to trigger this campaign's ads, for a period.
 *
 * Read from Google on demand rather than stored nightly. A busy account produces tens of thousands of
 * these a month, nearly all of them seen once and never again, and keeping them would cost more table
 * than the answers are worth. The page defers the panel so nothing waits on this call.
 */
class GetGoogleAdsSearchTerms
{
    use AsAction;

    private const int LIMIT = 200;

    /**
     * @return array{terms: array<int, array>, error: string|null}
     */
    public function handle(TrafficSourceCampaign $campaign, string $from, string $to): array
    {
        $client = GoogleAdsClient::forShop($campaign->trafficSource->shop);

        if (!$client) {
            return ['terms' => [], 'error' => GoogleAdsClient::unreachableReason($campaign->trafficSource->shop)];
        }

        /* Ordered by spend, because the question this answers is "what is the money going on". The cap
           is there so one runaway campaign cannot hand the page ten thousand rows; the total spend
           beside it says how much of the picture is being shown. */
        $query = "SELECT search_term_view.search_term, search_term_view.status,
                         segments.keyword.info.text, segments.keyword.info.match_type,
                         metrics.impressions, metrics.clicks, metrics.cost_micros,
                         metrics.conversions, metrics.conversions_value
                  FROM search_term_view
                  WHERE campaign.id = {$campaign->reference}
                    AND segments.date BETWEEN '{$from}' AND '{$to}'
                  ORDER BY metrics.cost_micros DESC
                  LIMIT ".self::LIMIT;

        try {
            $rows = $client->search($query);
        } catch (GoogleAdsException $exception) {
            return ['terms' => [], 'error' => $exception->getMessage()];
        }

        return ['terms' => $this->shape($rows), 'error' => null];
    }

    /**
     * @param array<int, array> $rows
     * @return array<int, array>
     */
    private function shape(array $rows): array
    {
        return collect($rows)
            ->map(function (array $row) {
                $cost        = ((float) data_get($row, 'metrics.costMicros', 0)) / 1_000_000;
                $conversions = (float) data_get($row, 'metrics.conversions', 0);
                $clicks      = (int) data_get($row, 'metrics.clicks', 0);

                /* Google's own word on whether this term is already a keyword or already excluded, so
                   the page can offer the action that is left rather than one that would be refused. */
                $status = (string) data_get($row, 'searchTermView.status', 'NONE');

                return [
                    'term'              => (string) data_get($row, 'searchTermView.searchTerm'),
                    'status'            => $status,
                    'matched_keyword'   => data_get($row, 'segments.keyword.info.text'),
                    'matched_match_type' => data_get($row, 'segments.keyword.info.matchType'),
                    'impressions'       => (int) data_get($row, 'metrics.impressions', 0),
                    'clicks'            => $clicks,
                    'cost'              => round($cost, 2),
                    'conversions'       => $conversions,
                    'conversions_value' => round((float) data_get($row, 'metrics.conversionsValue', 0), 2),
                    'cost_per_click'    => $clicks > 0 ? round($cost / $clicks, 2) : null,
                ];
            })
            /* Google returns one row per term per day segment where a segment is present; without any
               date segment selected it is already one row per term, but a term can still appear under
               two different matched keywords. Those are folded together: the reader is deciding about
               the term, not about which keyword happened to catch it. */
            ->groupBy('term')
            ->map(function ($group) {
                $first       = $group->first();
                $impressions = (int) $group->sum('impressions');
                $clicks      = (int) $group->sum('clicks');
                $cost        = (float) $group->sum('cost');
                $conversions = (float) $group->sum('conversions');

                return [
                    'term'              => $first['term'],
                    'status'            => $group->pluck('status')->contains('EXCLUDED') ? 'EXCLUDED'
                        : ($group->pluck('status')->contains('ADDED') ? 'ADDED' : $first['status']),
                    'matched_keyword'   => $first['matched_keyword'],
                    'impressions'       => $impressions,
                    'clicks'            => $clicks,
                    'cost'              => round($cost, 2),
                    'conversions'       => $conversions,
                    'conversions_value' => round($group->sum('conversions_value'), 2),
                    'cost_per_click'    => $clicks > 0 ? round($cost / $clicks, 2) : null,
                    'cpm'               => $impressions > 0 ? round($cost * 1000 / $impressions, 2) : null,
                    'conversion_rate'   => $clicks > 0 ? round($conversions * 100 / $clicks, 2) : null,
                ];
            })
            ->sortByDesc('cost')
            ->values()
            ->all();
    }
}
