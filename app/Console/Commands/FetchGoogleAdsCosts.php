<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Console\Commands;

use App\Actions\CRM\Customer\GoogleAds\Traits\WithGoogleAdsAccessToken;
use App\Actions\CRM\TrafficSource\GetTrafficSourceCampaign;
use App\Actions\CRM\TrafficSource\StoreTrafficSourceCost;
use App\Enums\CRM\TrafficSource\TrafficSourceCostFetchedViaEnum;
use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\TrafficSource;
use App\Models\CRM\TrafficSourceCampaign;
use App\Models\Helpers\Currency;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pulls Google Ads spend per campaign and day into traffic source costs.
 *
 * Spend used to reach us only by a Google Ads Script pushing it from inside the advertiser's account,
 * which meant every shop needed a script installed, a token held by whoever installed it, and a person
 * to notice when either stopped. The shops already connected for Customer Match hold an OAuth refresh
 * token and a customer id in their settings, which is everything the reporting API needs, so the same
 * figures can be fetched from our side on our own schedule.
 *
 * The script is not retired by this. It stays working for accounts we have no OAuth connection to -
 * an agency-run account that will not grant one, a shop mid-migration - and both paths write the same
 * row: source, campaign and date are the key, so a day offered twice is one row either way. Where the
 * two disagree the pull wins, because it re-reads a window of days and so carries Google's late
 * corrections, while a script posts yesterday once and never revisits it.
 *
 * Campaign references are Google's numeric campaign ids, the same ids the touch history stores when
 * ads are tagged with `utm_campaign={campaignid}`. Untagged accounts still get correct source-level
 * spend, only the campaign-level ROAS goes unmatched.
 *
 * One Google Ads account can be configured on more than one shop - a retail brand and its dropship
 * arm share an account and a payment method, and the campaigns are told apart only by how they are
 * named. Such an account goes on every shop it serves, each with
 * `shop.settings.google_ads.campaign_name_prefix` naming the campaigns that belong to that shop. A
 * shop with no prefix takes the whole account, which is what a shop that owns its account alone
 * wants. Spend left to another shop's prefix is counted and reported, because a day's total that
 * silently comes back short reads exactly like an account that underspent.
 */
class FetchGoogleAdsCosts extends Command
{
    use WithGoogleAdsAccessToken;

    protected $signature = 'traffic-source:fetch-google-ads-costs
                           {shop? : Shop slug, defaults to every shop connected to Google Ads}
                           {--date= : Last day to fetch, defaults to yesterday}
                           {--days=1 : How many days to fetch, counting back from --date}
                           {--dry-run : Fetch and report without writing anything}';

    protected $description = 'Fetch advertising spend from the Google Ads API';

    private const string API_VERSION = 'v25';

    private array $currencies = [];

    public function handle(): int
    {
        if (blank(config('services.google_ads.developer_token'))) {
            $this->warn('No Google Ads developer token configured (services.google_ads.developer_token).');

            return Command::FAILURE;
        }

        $until = $this->option('date') ? Carbon::parse($this->option('date')) : now()->subDay();
        $since = $until->copy()->subDays(max(1, (int) $this->option('days')) - 1);

        $shops = $this->shops();

        if ($shops->isEmpty()) {
            $this->error('No shop is configured for Google Ads: a customer id and a refresh token.');

            return Command::FAILURE;
        }

        $failed = 0;

        foreach ($shops as $shop) {
            try {
                $stored = $this->fetchShop($shop, $since, $until);
                $this->info("{$shop->slug}: {$stored} campaign-day(s) of spend.");
            } catch (\Throwable $e) {
                $failed++;
                $this->error("{$shop->slug}: ".$e->getMessage());
                Log::error('Google Ads cost fetch failed', ['shop' => $shop->slug, 'error' => $e->getMessage()]);
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

        return $shops->filter(function (Shop $shop) {
            $hasRefreshToken = filled(data_get($shop->settings, 'google_ads.refresh_token'));
            $hasCustomerId   = filled(data_get($shop->settings, 'google_ads.customer_id'));

            /* A shop that granted OAuth but never had its ad account entered is half-connected, and
               it is the half that looks connected on the settings page. Reported rather than skipped
               in silence, because such a shop is one field away from being on the pull and otherwise
               stays on its script for ever without anybody being told why. */
            if ($hasRefreshToken && !$hasCustomerId) {
                $this->warn("{$shop->slug}: skipped, Google Ads connected but no customer id set.");
            }

            return $hasRefreshToken && $hasCustomerId;
        });
    }

    private function fetchShop(Shop $shop, Carbon $since, Carbon $until): int
    {
        $trafficSource = TrafficSource::where('shop_id', $shop->id)
            ->where('type', TrafficSourcesTypeEnum::GOOGLE_ADS->value)
            ->first();

        if (!$trafficSource) {
            throw new \RuntimeException('shop has no google-ads traffic source, run traffic-source:seed');
        }

        $rows = $this->search($shop, $this->costQuery($since, $until));

        return $this->store(
            $trafficSource,
            $rows,
            trim((string) data_get($shop->settings, 'google_ads.campaign_name_prefix'))
        );
    }

    private function costQuery(Carbon $since, Carbon $until): string
    {
        return "SELECT campaign.id, campaign.name, campaign.advertising_channel_type, customer.currency_code, segments.date, metrics.cost_micros "
            ."FROM campaign "
            ."WHERE segments.date BETWEEN '{$since->toDateString()}' AND '{$until->toDateString()}'";
    }

    /**
     * @return array<int, array>
     */
    private function search(Shop $shop, string $query): array
    {
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

        $url = 'https://googleads.googleapis.com/'.self::API_VERSION."/customers/{$customerId}/googleAds:search";

        /* No page size: the search endpoint fixes it at 10,000 rows and rejects the whole request
           for naming one at all. Paging still happens, through the page token below. */
        $body = ['query' => $query];

        $results   = [];
        $pageToken = null;

        do {
            if ($pageToken) {
                $body['pageToken'] = $pageToken;
            }

            $response = Http::timeout(60)->withHeaders($headers)->post($url, $body);

            if ($response->failed()) {
                throw new \RuntimeException('Google Ads returned '.$response->status().': '.$this->errorMessage($response));
            }

            array_push($results, ...$response->json('results', []));

            $pageToken = $response->json('nextPageToken');
        } while ($pageToken);

        return $results;
    }

    /**
     * Google's top-level message for a rejected request is 'Request contains an invalid argument',
     * which names neither the argument nor the reason. The detail underneath does both.
     */
    private function errorMessage(\Illuminate\Http\Client\Response $response): string
    {
        $detail = data_get($response->json(), 'error.details.0.errors.0.message');

        return $detail
            ? $detail.' ('.data_get($response->json(), 'error.message').')'
            : (string) data_get($response->json(), 'error.message', $response->body());
    }

    /**
     * @param array<int, array> $rows
     */
    private function store(TrafficSource $trafficSource, array $rows, string $prefix): int
    {
        $stored          = 0;
        $leftToOtherShop = 0;

        foreach ($rows as $row) {
            $amount = ((float) data_get($row, 'metrics.costMicros', 0)) / 1_000_000;

            /* Google reports every campaign for every day in the range, spent or not. Storing the
               empty ones would open a cost row, and with it a campaign row, for campaigns that have
               never cost anything. */
            if ($amount <= 0) {
                continue;
            }

            $date         = (string) data_get($row, 'segments.date');
            $campaignId   = (string) data_get($row, 'campaign.id');
            $campaignName = (string) data_get($row, 'campaign.name');
            $channelType  = data_get($row, 'campaign.advertisingChannelType');

            if (!$this->belongsToShop($campaignName, $prefix) || $this->claimedByAnotherSource($trafficSource, $campaignId)) {
                $leftToOtherShop++;

                continue;
            }

            $currency = $this->currency((string) data_get($row, 'customer.currencyCode'));

            if ($this->option('dry-run')) {
                $this->line(sprintf(
                    '  %s  %-40s %-16s %10.2f %s',
                    $date,
                    $campaignName,
                    $channelType,
                    $amount,
                    $currency->code
                ));
                $stored++;

                continue;
            }

            StoreTrafficSourceCost::run($trafficSource, [
                'date'                       => $date,
                'source_amount'              => $amount,
                'source_currency_id'         => $currency->id,
                'fetched_via'                => TrafficSourceCostFetchedViaEnum::API->value,
                'traffic_source_campaign_id' => GetTrafficSourceCampaign::run(
                    $trafficSource,
                    $campaignId,
                    $campaignName,
                    $channelType
                ),
            ]);
            $stored++;
        }

        if ($leftToOtherShop > 0) {
            $this->line("  {$leftToOtherShop} campaign-day(s) with spend left to another shop sharing this Google Ads account.");
        }

        return $stored;
    }

    /**
     * A shop with no prefix configured takes every campaign in the account.
     */
    private function belongsToShop(string $campaignName, string $prefix): bool
    {
        if ($prefix === '') {
            return true;
        }

        return str_starts_with(strtolower(trim($campaignName)), strtolower($prefix));
    }

    /**
     * A campaign reference is unique across every traffic source, so a campaign already held by
     * another shop's source is that shop's campaign and its spend is that shop's spend.
     *
     * Skipped rather than stored without a campaign: a campaign-less row is keyed on source and date
     * alone, so every unclaimable campaign of the same day would overwrite the last and the shop
     * would be left holding one arbitrary campaign's spend, reported as the day's total. Two shops
     * on one account with no prefix between them is a configuration to fix, not a figure to invent.
     */
    private function claimedByAnotherSource(TrafficSource $trafficSource, string $campaignId): bool
    {
        if (blank($campaignId)) {
            return false;
        }

        return TrafficSourceCampaign::where('reference', $campaignId)
            ->where('traffic_source_id', '!=', $trafficSource->id)
            ->exists();
    }

    private function currency(string $code): Currency
    {
        return $this->currencies[$code] ??= Currency::where('code', strtoupper($code))->firstOr(
            fn () => throw new \RuntimeException("unknown currency '{$code}'")
        );
    }

    private function onlyDigits(string $value): string
    {
        return preg_replace('/\D/', '', $value);
    }
}
