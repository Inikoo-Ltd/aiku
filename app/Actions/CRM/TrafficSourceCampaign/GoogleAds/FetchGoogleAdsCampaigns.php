<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\TrafficSourceCampaign\GoogleAds;

use App\Actions\CRM\TrafficSource\GetTrafficSourceCampaign;
use App\Actions\CRM\TrafficSource\StoreTrafficSourceCost;
use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\TrafficSource;
use App\Models\CRM\TrafficSourceCampaign;
use App\Models\CRM\TrafficSourceCampaignMetric;
use App\Models\Helpers\Currency;
use App\Services\GoogleAds\GoogleAdsClient;
use App\Services\GoogleAds\GoogleAdsException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

/**
 * Pulls one shop's Google Ads account into Aiku: the campaign inventory with its ad groups, ads and
 * keywords, and a day-by-day row of what each campaign did.
 *
 * Spend goes through StoreTrafficSourceCost rather than being written here, so it converts to shop,
 * organisation and group currency at the rate of the day it was spent and lands in the same table
 * the marketing dashboard already reads for ROAS. That table is also where the pasted Google Ads
 * Script writes, and both paths key on source + campaign + date, so whichever ran last wins and a
 * shop can be moved from the script to this fetch without the two disagreeing or double counting.
 *
 * Re-running is always safe and is how a correction is applied: Google keeps attributing conversions
 * to a date for weeks after it closes, so the most recent read of a day is the accurate one.
 */
class FetchGoogleAdsCampaigns
{
    use AsAction;

    /**
     * `primary_status` is the field worth reading, not `status`: a campaign can be ENABLED and still
     * not be showing anyone anything, because the budget ran out, the ads are in review or the
     * targeting matches nobody. `primary_status_reasons` names which of those it is.
     *
     * The date fields are `_date_time` here. They were `campaign.start_date` until Google renamed
     * them, and a query naming the old ones is rejected outright rather than ignored.
     */
    private const string CAMPAIGN_QUERY = "SELECT campaign.id, campaign.name, campaign.status, campaign.primary_status, campaign.primary_status_reasons, campaign.advertising_channel_type, campaign.bidding_strategy_type, campaign.start_date_time, campaign.end_date_time, campaign_budget.id, campaign_budget.amount_micros, campaign_budget.explicitly_shared, customer.currency_code FROM campaign WHERE campaign.status != 'REMOVED'";

    private const string ADS_QUERY = "SELECT campaign.id, ad_group.id, ad_group.name, ad_group.status, ad_group_ad.status, ad_group_ad.ad.id, ad_group_ad.ad.type, ad_group_ad.ad.final_urls, ad_group_ad.ad.responsive_search_ad.headlines, ad_group_ad.ad.responsive_search_ad.descriptions FROM ad_group_ad WHERE campaign.status != 'REMOVED' AND ad_group_ad.status != 'REMOVED'";

    private const string KEYWORDS_QUERY = "SELECT campaign.id, ad_group.id, ad_group_criterion.criterion_id, ad_group_criterion.keyword.text, ad_group_criterion.keyword.match_type, ad_group_criterion.status FROM keyword_view WHERE campaign.status != 'REMOVED'";

    /**
     * Campaign level negatives only. A negative sitting on an ad group is a different resource that
     * excludes a different scope, and showing the two in one list would misstate what a campaign is
     * actually blocking.
     */
    private const string NEGATIVES_QUERY = "SELECT campaign.id, campaign_criterion.criterion_id, campaign_criterion.keyword.text, campaign_criterion.keyword.match_type FROM campaign_criterion WHERE campaign_criterion.negative = true AND campaign_criterion.type = 'KEYWORD' AND campaign.status != 'REMOVED'";

    /**
     * @return array{campaigns: int, skipped: int, metric_days: int, dry_run: bool}
     * @throws GoogleAdsException
     */
    public function handle(Shop $shop, int $days = 30, bool $dryRun = false): array
    {
        $client = GoogleAdsClient::forShop($shop);

        if (!$client) {
            throw new RuntimeException(GoogleAdsClient::unreachableReason($shop) ?? __('Google Ads is not configured for this shop.'));
        }

        $trafficSource = TrafficSource::where('shop_id', $shop->id)
            ->where('type', TrafficSourcesTypeEnum::GOOGLE_ADS->value)
            ->first();

        if (!$trafficSource) {
            /* Seeded for every shop by StoreShop, so a shop without one predates that or was made
               by hand; SeedTrafficSources::run($shop) fills it in. There is no artisan command for
               it, whatever the older error message here used to claim. */
            throw new RuntimeException("shop {$shop->slug} has no google-ads traffic source; run SeedTrafficSources on it");
        }

        $campaignRows = $client->search(self::CAMPAIGN_QUERY);
        $adGroups     = $this->groupAdGroups(
            $this->searchOptional($client, self::ADS_QUERY, $shop, 'ads'),
            $this->searchOptional($client, self::KEYWORDS_QUERY, $shop, 'keywords'),
        );
        $metrics   = $this->groupMetrics($this->searchOptional($client, $this->metricsQuery($days), $shop, 'metrics'));
        $negatives = $this->groupNegatives($this->searchOptional($client, self::NEGATIVES_QUERY, $shop, 'negative keywords'));

        $summary = ['campaigns' => 0, 'skipped' => 0, 'metric_days' => 0, 'dry_run' => $dryRun];

        foreach ($campaignRows as $result) {
            $campaignId   = (string) data_get($result, 'campaign.id');
            $campaignDays = $metrics[$campaignId] ?? [];

            $campaign = $this->storeCampaign(
                $trafficSource,
                $result,
                array_values($adGroups[$campaignId] ?? []),
                array_values($negatives[$campaignId] ?? []),
                $dryRun
            );

            if (!$campaign) {
                $summary['skipped']++;

                continue;
            }

            $summary['campaigns']++;
            $summary['metric_days'] += count($campaignDays);

            if (!$dryRun) {
                $this->storeMetrics($trafficSource, $campaign, $campaignDays, (string) data_get($result, 'customer.currencyCode'));
            }
        }

        return $summary;
    }

    /**
     * Google closes a day in the account's own time zone, not ours, and keeps revising it afterwards.
     * Today is included so the page is not a day behind by lunchtime; it reads low until the day ends
     * and is corrected by the next run, which is what every ads report does.
     */
    private function metricsQuery(int $days): string
    {
        $from = now()->subDays(max($days - 1, 0))->toDateString();
        $to   = now()->toDateString();

        return "SELECT campaign.id, segments.date, metrics.impressions, metrics.clicks, metrics.cost_micros, metrics.conversions, metrics.conversions_value FROM campaign WHERE segments.date BETWEEN '{$from}' AND '{$to}' AND campaign.status != 'REMOVED'";
    }

    /**
     * Ads and keywords are not worth failing a whole shop over: a campaign type that has neither, such
     * as Performance Max, makes those queries return nothing useful, and the campaign figures are the
     * part anyone is waiting for.
     *
     * @return array<int, array>
     */
    private function searchOptional(GoogleAdsClient $client, string $query, Shop $shop, string $label): array
    {
        try {
            return $client->search($query);
        } catch (GoogleAdsException $e) {
            Log::warning('Google Ads optional fetch failed', ['shop' => $shop->slug, 'label' => $label, 'error' => $e->describe()]);

            return [];
        }
    }

    /**
     * @param array<int, array> $adRows
     * @param array<int, array> $keywordRows
     * @return array<string, array<string, array>>
     */
    private function groupAdGroups(array $adRows, array $keywordRows): array
    {
        $adGroups = [];

        foreach ($adRows as $row) {
            $campaignId = (string) data_get($row, 'campaign.id');
            $adGroupId  = (string) data_get($row, 'adGroup.id');

            $adGroups[$campaignId][$adGroupId] ??= $this->emptyAdGroup($adGroupId, data_get($row, 'adGroup.name'), data_get($row, 'adGroup.status'));

            $adGroups[$campaignId][$adGroupId]['ads'][] = [
                'id'           => data_get($row, 'adGroupAd.ad.id'),
                'type'         => data_get($row, 'adGroupAd.ad.type'),
                'status'       => data_get($row, 'adGroupAd.status'),
                'final_urls'   => data_get($row, 'adGroupAd.ad.finalUrls', []),
                'headlines'    => collect(data_get($row, 'adGroupAd.ad.responsiveSearchAd.headlines', []))->pluck('text')->all(),
                'descriptions' => collect(data_get($row, 'adGroupAd.ad.responsiveSearchAd.descriptions', []))->pluck('text')->all(),
            ];
        }

        foreach ($keywordRows as $row) {
            $campaignId = (string) data_get($row, 'campaign.id');
            $adGroupId  = (string) data_get($row, 'adGroup.id');

            $adGroups[$campaignId][$adGroupId] ??= $this->emptyAdGroup($adGroupId);

            $adGroups[$campaignId][$adGroupId]['keywords'][] = [
                'id'         => data_get($row, 'adGroupCriterion.criterionId'),
                'text'       => data_get($row, 'adGroupCriterion.keyword.text'),
                'match_type' => data_get($row, 'adGroupCriterion.keyword.matchType'),
                'status'     => data_get($row, 'adGroupCriterion.status'),
            ];
        }

        return $adGroups;
    }

    private function emptyAdGroup(string $id, ?string $name = null, ?string $status = null): array
    {
        return [
            'id'       => $id,
            'name'     => $name,
            'status'   => $status,
            'ads'      => [],
            'keywords' => [],
        ];
    }

    /**
     * @param array<int, array> $metricRows
     * @return array<string, array<string, array>> campaign id => date => figures
     */
    private function groupMetrics(array $metricRows): array
    {
        $metrics = [];

        foreach ($metricRows as $row) {
            $campaignId = (string) data_get($row, 'campaign.id');
            $date       = (string) data_get($row, 'segments.date');

            $metrics[$campaignId][$date] = [
                'impressions'              => (int) data_get($row, 'metrics.impressions', 0),
                'clicks'                   => (int) data_get($row, 'metrics.clicks', 0),
                'conversions'              => (float) data_get($row, 'metrics.conversions', 0),
                'source_cost'              => ((float) data_get($row, 'metrics.costMicros', 0)) / 1_000_000,
                'source_conversions_value' => (float) data_get($row, 'metrics.conversionsValue', 0),
            ];
        }

        return $metrics;
    }

    /**
     * @return array<string, array<int, array>>
     */
    private function groupNegatives(array $rows): array
    {
        $negatives = [];

        foreach ($rows as $row) {
            $negatives[(string) data_get($row, 'campaign.id')][] = [
                'id'         => data_get($row, 'campaignCriterion.criterionId'),
                'text'       => data_get($row, 'campaignCriterion.keyword.text'),
                'match_type' => data_get($row, 'campaignCriterion.keyword.matchType'),
            ];
        }

        return $negatives;
    }

    private function storeCampaign(TrafficSource $trafficSource, array $result, array $adGroups, array $negatives, bool $dryRun): ?TrafficSourceCampaign
    {
        $reference   = (string) data_get($result, 'campaign.id');
        $name        = (string) data_get($result, 'campaign.name');
        $channelType = data_get($result, 'campaign.advertisingChannelType');

        if ($dryRun) {
            return TrafficSourceCampaign::where('reference', $reference)->first()
                ?? new TrafficSourceCampaign(['reference' => $reference, 'name' => $name]);
        }

        $campaignId = GetTrafficSourceCampaign::run($trafficSource, $reference, $name, $channelType);

        if ($campaignId === null) {
            return null;
        }

        $campaign = TrafficSourceCampaign::find($campaignId);

        $campaign->update([
            'name' => $name,
            'data' => array_merge($campaign->data ?? [], [
                'status'                 => data_get($result, 'campaign.status'),
                'primary_status'         => data_get($result, 'campaign.primaryStatus'),
                'primary_status_reasons' => data_get($result, 'campaign.primaryStatusReasons', []),
                'channel_type'           => $channelType,
                'bidding_strategy_type'  => data_get($result, 'campaign.biddingStrategyType'),
                'start_date'             => data_get($result, 'campaign.startDateTime'),
                'end_date'               => data_get($result, 'campaign.endDateTime'),
                'budget_id'              => data_get($result, 'campaignBudget.id'),

                /* A shared budget belongs to several campaigns at once, so raising it from this
                   campaign's page would quietly raise the others too. The write action refuses on
                   this flag rather than surprising someone. */
                'budget_is_shared' => (bool) data_get($result, 'campaignBudget.explicitlyShared', false),
                'budget_amount'          => data_get($result, 'campaignBudget.amountMicros') !== null
                    ? ((float) data_get($result, 'campaignBudget.amountMicros')) / 1_000_000
                    : null,
                'currency'   => data_get($result, 'customer.currencyCode'),
                'fetched_at'        => now()->toIso8601String(),
                'ad_groups'         => $adGroups,
                'negative_keywords' => $negatives,
            ]),
        ]);

        return $campaign->refresh();
    }

    /**
     * @param array<string, array> $days
     */
    private function storeMetrics(TrafficSource $trafficSource, TrafficSourceCampaign $campaign, array $days, string $currencyCode): int
    {
        if ($days === []) {
            return 0;
        }

        $currency = Currency::where('code', $currencyCode)->first();

        foreach ($days as $date => $figures) {
            TrafficSourceCampaignMetric::updateOrCreate(
                [
                    'traffic_source_campaign_id' => $campaign->id,
                    'date'                       => $date,
                ],
                array_merge($figures, ['source_currency_id' => $currency?->id])
            );

            /* A day Google reports as costing nothing still reaches StoreTrafficSourceCost, so a
               campaign paused mid-month reads as zero spend on the days it was off rather than
               keeping whatever a previous run had recorded there. */
            if ($currency) {
                StoreTrafficSourceCost::run($trafficSource, [
                    'date'                       => Carbon::parse($date),
                    'source_amount'              => $figures['source_cost'],
                    'source_currency_id'         => $currency->id,
                    'traffic_source_campaign_id' => $campaign->id,
                ]);
            }
        }

        if (!$currency) {
            Log::warning('Google Ads spend not recorded, unknown account currency', [
                'shop'     => $trafficSource->shop->slug,
                'campaign' => $campaign->reference,
                'currency' => $currencyCode,
            ]);
        }

        return count($days);
    }

    /**
     * Every shop that is connected and has a Customer ID, which is what the nightly run covers.
     *
     * @return \Illuminate\Support\Collection<int, Shop>
     */
    public static function connectedShops(?string $slug = null): \Illuminate\Support\Collection
    {
        return Shop::when($slug, fn ($query) => $query->where('slug', $slug))
            ->get()
            ->filter(fn (Shop $shop) => filled(Arr::get($shop->settings, 'google_ads.refresh_token'))
                && filled(Arr::get($shop->settings, 'google_ads.customer_id')));
    }
}
