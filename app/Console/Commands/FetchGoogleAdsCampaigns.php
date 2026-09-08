<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 07 Sep 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Console\Commands;

use App\Actions\CRM\Customer\GoogleAds\Traits\WithGoogleAdsAccessToken;
use App\Actions\CRM\TrafficSource\GetTrafficSourceCampaign;
use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\TrafficSource;
use App\Models\CRM\TrafficSourceCampaign;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pulls the shop's Google Ads campaign inventory (name, status, channel type, budget, ad groups,
 * ads, keywords and the last 30 days of metrics) into traffic_source_campaigns. Spend is not pulled
 * here: Google Ads Scripts push it to us instead (see docs/google-ads-cost-ingestion), because a
 * script running inside Google's platform can do what a REST pull cannot for the free daily quota
 * this uses.
 */
class FetchGoogleAdsCampaigns extends Command
{
    use WithGoogleAdsAccessToken;

    protected $signature = 'google-ads:fetch-campaigns
                           {shop? : Shop slug, defaults to every shop connected to Google Ads}
                           {--dry-run : Fetch and report without writing anything}';

    protected $description = 'Fetch the campaign inventory from the Google Ads API';

    private const string API_VERSION = 'v21';

    private const string CAMPAIGN_QUERY = "SELECT campaign.id, campaign.name, campaign.status, campaign.advertising_channel_type, campaign_budget.amount_micros, customer.currency_code FROM campaign WHERE campaign.status != 'REMOVED'";

    private const string ADS_QUERY = "SELECT campaign.id, ad_group.id, ad_group.name, ad_group.status, ad_group_ad.status, ad_group_ad.ad.id, ad_group_ad.ad.type, ad_group_ad.ad.final_urls, ad_group_ad.ad.responsive_search_ad.headlines, ad_group_ad.ad.responsive_search_ad.descriptions FROM ad_group_ad WHERE campaign.status != 'REMOVED' AND ad_group_ad.status != 'REMOVED'";

    private const string KEYWORDS_QUERY = "SELECT campaign.id, ad_group.id, ad_group_criterion.criterion_id, ad_group_criterion.keyword.text, ad_group_criterion.keyword.match_type, ad_group_criterion.status FROM keyword_view WHERE campaign.status != 'REMOVED'";

    private const string METRICS_QUERY = "SELECT campaign.id, segments.date, metrics.impressions, metrics.clicks, metrics.cost_micros, metrics.conversions, metrics.conversions_value FROM campaign WHERE segments.date DURING LAST_30_DAYS AND campaign.status != 'REMOVED'";

    public function handle(): int
    {
        if (blank(config('services.google_ads.developer_token'))) {
            $this->warn('No Google Ads developer token configured (services.google_ads.developer_token).');

            return Command::FAILURE;
        }

        $shops = $this->shops();

        if ($shops->isEmpty()) {
            $this->error('No shop is configured for Google Ads: a customer id and a refresh token.');

            return Command::FAILURE;
        }

        $failed = 0;

        foreach ($shops as $shop) {
            try {
                $stored = $this->fetchShop($shop);
                $this->info("{$shop->slug}: {$stored} campaign(s).");
            } catch (\Throwable $e) {
                $failed++;
                $this->error("{$shop->slug}: ".$e->getMessage());
                Log::error('Google Ads campaign fetch failed', ['shop' => $shop->slug, 'error' => $e->getMessage()]);
            }
        }

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Shop>
     */
    private function shops(): \Illuminate\Support\Collection
    {
        $shops = Shop::when(
            $this->argument('shop'),
            fn ($query) => $query->where('slug', $this->argument('shop'))
        )->get();

        if ($this->argument('shop') && $shops->isEmpty()) {
            $this->error("Unknown shop '{$this->argument('shop')}'.");
        }

        return $shops->filter(fn (Shop $shop) => filled(data_get($shop->settings, 'google_ads.refresh_token'))
            && filled(data_get($shop->settings, 'google_ads.customer_id')));
    }

    private function fetchShop(Shop $shop): int
    {
        $trafficSource = TrafficSource::where('shop_id', $shop->id)
            ->where('type', TrafficSourcesTypeEnum::GOOGLE_ADS->value)
            ->first();

        if (!$trafficSource) {
            throw new \RuntimeException('shop has no google-ads traffic source, run traffic-source:seed');
        }

        $accessToken = $this->googleAdsAccessToken($shop);
        $customerId  = $this->onlyDigits((string) data_get($shop->settings, 'google_ads.customer_id'));
        $loginId     = $this->onlyDigits((string) data_get($shop->settings, 'google_ads.login_customer_id'));

        $headers = [
            'Authorization'   => "Bearer {$accessToken}",
            'developer-token' => config('services.google_ads.developer_token'),
        ];

        if ($loginId !== '') {
            $headers['login-customer-id'] = $loginId;
        }

        $url = "https://googleads.googleapis.com/".self::API_VERSION."/customers/{$customerId}/googleAds:search";

        $campaignRows = $this->search($headers, $url, self::CAMPAIGN_QUERY);
        $adGroups     = $this->groupAdGroups(
            $this->searchOptional($headers, $url, self::ADS_QUERY, $shop, 'ads'),
            $this->searchOptional($headers, $url, self::KEYWORDS_QUERY, $shop, 'keywords'),
        );
        $metrics = $this->groupMetrics($this->searchOptional($headers, $url, self::METRICS_QUERY, $shop, 'metrics'));

        $stored  = 0;
        $skipped = 0;

        foreach ($campaignRows as $result) {
            $campaignId = (string) data_get($result, 'campaign.id');

            if ($this->storeCampaign(
                $trafficSource,
                $result,
                array_values($adGroups[$campaignId] ?? []),
                $metrics[$campaignId] ?? ['totals' => $this->emptyMetricsTotal(), 'daily' => []],
            )) {
                $stored++;
            } else {
                $skipped++;
            }
        }

        if ($skipped > 0) {
            $this->line("  {$skipped} campaign(s) skipped, reference already claimed by another shop.");
        }

        return $stored;
    }

    /**
     * @return array<int, array>
     */
    private function search(array $headers, string $url, string $query): array
    {
        $body      = ['query' => $query, 'pageSize' => 1000];
        $results   = [];
        $pageToken = null;

        do {
            if ($pageToken) {
                $body['pageToken'] = $pageToken;
            }

            $response = Http::withHeaders($headers)->post($url, $body);

            if ($response->failed()) {
                throw new \RuntimeException(
                    'Google Ads returned '.$response->status().': '.data_get($response->json(), 'error.message', $response->body())
                );
            }

            array_push($results, ...$response->json('results', []));

            $pageToken = $response->json('nextPageToken');
        } while ($pageToken);

        return $results;
    }

    /**
     * @return array<int, array>
     */
    private function searchOptional(array $headers, string $url, string $query, Shop $shop, string $label): array
    {
        try {
            return $this->search($headers, $url, $query);
        } catch (\Throwable $e) {
            $this->warn("{$shop->slug}: could not fetch {$label}: ".$e->getMessage());
            Log::warning('Google Ads optional fetch failed', ['shop' => $shop->slug, 'label' => $label, 'error' => $e->getMessage()]);

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

            $adGroups[$campaignId][$adGroupId] ??= [
                'id'     => $adGroupId,
                'name'   => data_get($row, 'adGroup.name'),
                'status' => data_get($row, 'adGroup.status'),
                'ads'    => [],
                'keywords' => [],
            ];

            $adGroups[$campaignId][$adGroupId]['ads'][] = [
                'id'          => data_get($row, 'adGroupAd.ad.id'),
                'type'        => data_get($row, 'adGroupAd.ad.type'),
                'status'      => data_get($row, 'adGroupAd.status'),
                'final_urls'  => data_get($row, 'adGroupAd.ad.finalUrls', []),
                'headlines'   => collect(data_get($row, 'adGroupAd.ad.responsiveSearchAd.headlines', []))->pluck('text')->all(),
                'descriptions' => collect(data_get($row, 'adGroupAd.ad.responsiveSearchAd.descriptions', []))->pluck('text')->all(),
            ];
        }

        foreach ($keywordRows as $row) {
            $campaignId = (string) data_get($row, 'campaign.id');
            $adGroupId  = (string) data_get($row, 'adGroup.id');

            $adGroups[$campaignId][$adGroupId] ??= [
                'id'       => $adGroupId,
                'name'     => null,
                'status'   => null,
                'ads'      => [],
                'keywords' => [],
            ];

            $adGroups[$campaignId][$adGroupId]['keywords'][] = [
                'text'       => data_get($row, 'adGroupCriterion.keyword.text'),
                'match_type' => data_get($row, 'adGroupCriterion.keyword.matchType'),
                'status'     => data_get($row, 'adGroupCriterion.status'),
            ];
        }

        return $adGroups;
    }

    /**
     * @param array<int, array> $metricRows
     * @return array<string, array{totals: array, daily: array<int, array>}>
     */
    private function groupMetrics(array $metricRows): array
    {
        $metrics = [];

        foreach ($metricRows as $row) {
            $campaignId = (string) data_get($row, 'campaign.id');
            $cost       = ((float) data_get($row, 'metrics.costMicros', 0)) / 1_000_000;

            $metrics[$campaignId] ??= ['totals' => $this->emptyMetricsTotal(), 'daily' => []];

            $metrics[$campaignId]['totals']['impressions']       += (int) data_get($row, 'metrics.impressions', 0);
            $metrics[$campaignId]['totals']['clicks']             += (int) data_get($row, 'metrics.clicks', 0);
            $metrics[$campaignId]['totals']['cost']               += $cost;
            $metrics[$campaignId]['totals']['conversions']        += (float) data_get($row, 'metrics.conversions', 0);
            $metrics[$campaignId]['totals']['conversions_value']  += (float) data_get($row, 'metrics.conversionsValue', 0);

            $metrics[$campaignId]['daily'][] = [
                'date'        => data_get($row, 'segments.date'),
                'impressions' => (int) data_get($row, 'metrics.impressions', 0),
                'clicks'      => (int) data_get($row, 'metrics.clicks', 0),
                'cost'        => $cost,
                'conversions' => (float) data_get($row, 'metrics.conversions', 0),
            ];
        }

        return $metrics;
    }

    /**
     * @return array{impressions: int, clicks: int, cost: float, conversions: float, conversions_value: float}
     */
    private function emptyMetricsTotal(): array
    {
        return [
            'impressions'       => 0,
            'clicks'            => 0,
            'cost'              => 0.0,
            'conversions'       => 0.0,
            'conversions_value' => 0.0,
        ];
    }

    private function storeCampaign(TrafficSource $trafficSource, array $result, array $adGroups, array $metrics): bool
    {
        $campaignId = (string) data_get($result, 'campaign.id');
        $name       = (string) data_get($result, 'campaign.name');
        $data       = [
            'status'        => data_get($result, 'campaign.status'),
            'channel_type'  => data_get($result, 'campaign.advertisingChannelType'),
            'budget_amount' => data_get($result, 'campaignBudget.amountMicros') !== null
                ? ((float) data_get($result, 'campaignBudget.amountMicros')) / 1_000_000
                : null,
            'currency'       => data_get($result, 'customer.currencyCode'),
            'fetched_at'     => now()->toIso8601String(),
            'ad_groups'      => $adGroups,
            'metrics_30d'    => $metrics['totals'],
            'metrics_daily'  => $metrics['daily'],
        ];

        if ($this->option('dry-run')) {
            $adsCount     = collect($adGroups)->sum(fn ($group) => count($group['ads']));
            $keywordCount = collect($adGroups)->sum(fn ($group) => count($group['keywords']));

            $this->line(sprintf(
                '  %-14s %-40s %-10s %-10s ad_groups=%d ads=%d keywords=%d metric_days=%d',
                $campaignId,
                $name,
                $data['status'],
                $data['channel_type'],
                count($adGroups),
                $adsCount,
                $keywordCount,
                count($metrics['daily']),
            ));

            return true;
        }

        $trafficSourceCampaignId = GetTrafficSourceCampaign::run($trafficSource, $campaignId, $name);

        if ($trafficSourceCampaignId === null) {
            return false;
        }

        TrafficSourceCampaign::whereKey($trafficSourceCampaignId)->update([
            'name' => $name,
            'data' => $data,
        ]);

        return true;
    }

    private function onlyDigits(string $value): string
    {
        return preg_replace('/\D/', '', $value);
    }
}
