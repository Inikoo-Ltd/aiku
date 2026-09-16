<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\TrafficSourceCampaign\GoogleAds;

use App\Actions\CRM\TrafficSource\GetTrafficSourceCampaign;
use App\Actions\CRM\TrafficSource\StoreTrafficSourceCost;
use App\Enums\CRM\TrafficSource\TrafficSourceCostFetchedViaEnum;
use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\TrafficSource;
use App\Models\CRM\TrafficSourceCampaign;
use App\Models\CRM\TrafficSourceCampaignConversion;
use App\Models\CRM\TrafficSourceCampaignMetric;
use App\Models\Helpers\Currency;
use App\Services\GoogleAds\GoogleAdsClient;
use App\Services\GoogleAds\GoogleAdsException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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

    /**
     * Campaign level negatives only. A negative sitting on an ad group is a different resource that
     * excludes a different scope, and showing the two in one list would misstate what a campaign is
     * actually blocking.
     */
    private const string NEGATIVES_QUERY = "SELECT campaign.id, campaign_criterion.criterion_id, campaign_criterion.keyword.text, campaign_criterion.keyword.match_type FROM campaign_criterion WHERE campaign_criterion.negative = true AND campaign_criterion.type = 'KEYWORD' AND campaign.status != 'REMOVED'";

    /**
     * @return array{campaigns: int, skipped: int, metric_days: int, conversion_rows: int, asset_groups: int, dry_run: bool}
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

        [$from, $to] = $this->window($days);

        $campaignRows = $client->search(self::CAMPAIGN_QUERY);
        $structure    = ReadGoogleAdsCampaignStructure::run($client, $shop, $from, $to);
        $metrics      = $this->groupMetrics($this->searchOptional($client, $this->metricsQuery($from, $to), $shop, 'metrics') ?? []);
        $negatives    = $this->groupNegatives($this->searchOptional($client, self::NEGATIVES_QUERY, $shop, 'negative keywords') ?? []);

        $conversionRows = $this->searchOptional($client, $this->conversionsQuery($from, $to), $shop, 'conversion actions');
        $conversions    = $conversionRows === null ? null : $this->groupConversions($conversionRows);

        $summary = ['campaigns' => 0, 'skipped' => 0, 'metric_days' => 0, 'conversion_rows' => 0, 'asset_groups' => 0, 'dry_run' => $dryRun];

        foreach ($campaignRows as $result) {
            $campaignId   = (string) data_get($result, 'campaign.id');
            $campaignDays = $metrics[$campaignId] ?? [];

            $campaignStructure = [
                'ad_groups'         => $structure['ad_groups'][$campaignId] ?? [],
                'asset_groups'      => $structure['asset_groups'][$campaignId] ?? [],
                'exclusions'        => $structure['exclusions'][$campaignId] ?? [],
                'negative_keywords' => array_values($negatives[$campaignId] ?? []),
                'structure_window'  => $structure['window'],
            ];

            $campaign = $this->storeCampaign($trafficSource, $result, $campaignStructure, $dryRun);

            if (!$campaign) {
                $summary['skipped']++;

                continue;
            }

            $summary['campaigns']++;
            $summary['metric_days'] += count($campaignDays);
            $summary['asset_groups'] += count($campaignStructure['asset_groups']);

            $campaignConversions = $conversions[$campaignId] ?? [];
            $summary['conversion_rows'] += count($campaignConversions);

            if (!$dryRun) {
                $currencyCode = (string) data_get($result, 'customer.currencyCode');

                $this->storeMetrics($trafficSource, $campaign, $campaignDays, $currencyCode);

                if ($conversions !== null) {
                    $this->storeConversions($campaign, $campaignConversions, $from, $to, $currencyCode);
                }
            }
        }

        return $summary;
    }

    /**
     * Google closes a day in the account's own time zone, not ours, and keeps revising it afterwards.
     * Today is included so the page is not a day behind by lunchtime; it reads low until the day ends
     * and is corrected by the next run, which is what every ads report does.
     *
     * @return array{0: string, 1: string}
     */
    private function window(int $days): array
    {
        return [
            now()->subDays(max($days - 1, 0))->toDateString(),
            now()->toDateString(),
        ];
    }

    /**
     * The impression share fields come back empty for campaign types that do not run on Google
     * Search, and Google reports anything under 10% as 0.0999 and anything over 90% as 0.9001.
     */
    private function metricsQuery(string $from, string $to): string
    {
        $impressionShareFields = implode(', ', array_map(
            fn (string $column) => "metrics.{$column}",
            TrafficSourceCampaignMetric::IMPRESSION_SHARE_COLUMNS
        ));

        return "SELECT campaign.id, segments.date, metrics.impressions, metrics.clicks, metrics.cost_micros, metrics.conversions, metrics.conversions_value, metrics.all_conversions, metrics.all_conversions_value, {$impressionShareFields} FROM campaign WHERE segments.date BETWEEN '{$from}' AND '{$to}' AND campaign.status != 'REMOVED'";
    }

    /**
     * Segmenting by conversion action is what separates a purchase from a sign-up; Google only allows
     * conversion metrics alongside that segment, which is why this is a query of its own rather than
     * more columns on the metrics one.
     */
    private function conversionsQuery(string $from, string $to): string
    {
        return "SELECT campaign.id, segments.date, segments.conversion_action_category, segments.conversion_action_name, metrics.conversions, metrics.conversions_value, metrics.all_conversions, metrics.all_conversions_value FROM campaign WHERE segments.date BETWEEN '{$from}' AND '{$to}' AND campaign.status != 'REMOVED'";
    }

    /**
     * Ads and keywords are not worth failing a whole shop over: a campaign type that has neither, such
     * as Performance Max, makes those queries return nothing useful, and the campaign figures are the
     * part anyone is waiting for.
     *
     * Null on failure rather than an empty list, so a caller that replaces stored rows for the window
     * can tell "Google refused the question" from "Google answered that there is nothing".
     *
     * @return array<int, array>|null
     */
    private function searchOptional(GoogleAdsClient $client, string $query, Shop $shop, string $label): ?array
    {
        try {
            return $client->search($query);
        } catch (GoogleAdsException $e) {
            Log::warning('Google Ads optional fetch failed', ['shop' => $shop->slug, 'label' => $label, 'error' => $e->describe()]);

            return null;
        }
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

            $figures = [
                'impressions'                  => (int) data_get($row, 'metrics.impressions', 0),
                'clicks'                       => (int) data_get($row, 'metrics.clicks', 0),
                'conversions'                  => (float) data_get($row, 'metrics.conversions', 0),
                'source_cost'                  => ((float) data_get($row, 'metrics.costMicros', 0)) / 1_000_000,
                'source_conversions_value'     => (float) data_get($row, 'metrics.conversionsValue', 0),
                'all_conversions'              => (float) data_get($row, 'metrics.allConversions', 0),
                'source_all_conversions_value' => (float) data_get($row, 'metrics.allConversionsValue', 0),
            ];

            foreach (TrafficSourceCampaignMetric::IMPRESSION_SHARE_COLUMNS as $column) {
                $share = data_get($row, 'metrics.'.Str::camel($column));

                $figures[$column] = $share === null ? null : (float) $share;
            }

            $metrics[$campaignId][$date] = $figures;
        }

        return $metrics;
    }

    /**
     * @param array<int, array> $rows
     * @return array<string, array<int, array>> campaign id => rows of one day and one conversion action
     */
    private function groupConversions(array $rows): array
    {
        $conversions = [];

        foreach ($rows as $row) {
            $conversions[(string) data_get($row, 'campaign.id')][] = [
                'date'                         => (string) data_get($row, 'segments.date'),
                'category'                     => (string) data_get($row, 'segments.conversionActionCategory', 'UNSPECIFIED'),
                'action_name'                  => (string) data_get($row, 'segments.conversionActionName', ''),
                'conversions'                  => (float) data_get($row, 'metrics.conversions', 0),
                'source_conversions_value'     => (float) data_get($row, 'metrics.conversionsValue', 0),
                'all_conversions'              => (float) data_get($row, 'metrics.allConversions', 0),
                'source_all_conversions_value' => (float) data_get($row, 'metrics.allConversionsValue', 0),
            ];
        }

        return $conversions;
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

    /**
     * @param array{ad_groups: array<int, array>, asset_groups: array<int, array>, exclusions: array<int, array>, negative_keywords: array<int, array>, structure_window: array{from: string, to: string}} $structure
     */
    private function storeCampaign(TrafficSource $trafficSource, array $result, array $structure, bool $dryRun): ?TrafficSourceCampaign
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
                'fetched_at' => now()->toIso8601String(),
            ], $structure),
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
                    'fetched_via'                => TrafficSourceCostFetchedViaEnum::API->value,
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
     * The window is replaced rather than merged: Google leaves out an action that recorded nothing,
     * so a row that has vanished from the answer is a row that has been corrected away, and keeping
     * it would count a conversion Google no longer does.
     *
     * @param array<int, array> $rows
     */
    private function storeConversions(TrafficSourceCampaign $campaign, array $rows, string $from, string $to, string $currencyCode): void
    {
        $currencyId = Currency::where('code', $currencyCode)->value('id');
        $now        = now();

        DB::transaction(function () use ($campaign, $rows, $from, $to, $currencyId, $now) {
            TrafficSourceCampaignConversion::where('traffic_source_campaign_id', $campaign->id)
                ->whereBetween('date', [$from, $to])
                ->delete();

            if ($rows === []) {
                return;
            }

            TrafficSourceCampaignConversion::insert(array_map(
                fn (array $row) => array_merge($row, [
                    'traffic_source_campaign_id' => $campaign->id,
                    'source_currency_id'         => $currencyId,
                    'created_at'                 => $now,
                    'updated_at'                 => $now,
                ]),
                $rows
            ));
        });
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
