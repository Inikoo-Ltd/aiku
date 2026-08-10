<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Console\Commands;

use App\Actions\CRM\TrafficSource\GetTrafficSourceCampaign;
use App\Actions\CRM\TrafficSource\StoreTrafficSourceCost;
use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\TrafficSource;
use App\Models\Helpers\Currency;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pulls yesterday's Meta (Facebook/Instagram) ad spend into traffic source costs.
 *
 * Google Ads pushes to us, because Google Ads Scripts run inside their platform. Meta has no such
 * thing, so the same job has to be a pull from our side: one Insights call per ad account per run,
 * broken down by campaign and by day.
 *
 * Configure a shop by putting its ad account in `shop.settings.meta_ads.ad_account_id` (the digits
 * only, no `act_` prefix). The access token comes from the same settings block, falling back to
 * `services.meta_ads.access_token` - a Business Manager system user token usually covers every ad
 * account in the business, so one env value normally serves every shop.
 *
 * Campaign references are Meta's numeric campaign ids. The touch history stores whatever the ad's
 * `utm_campaign` said, so campaign-level ROAS only lines up when the ads are tagged with Meta's
 * dynamic `utm_campaign={{campaign.id}}`. Untagged accounts still get correct source-level spend.
 *
 * Spend is broken down by publisher platform so Instagram is its own channel: Instagram ads live in
 * the same ad account as Facebook ones and have no separate API, but the platform that served the
 * impression is a breakdown of the same Insights call. Instagram rows go to the instagram-ads source,
 * every other platform (Facebook, Audience Network, Messenger, Threads) stays under meta-ads, which is
 * exactly how the click side splits them via the ads' `utm_source={{site_source_name}}`.
 *
 * ponytail: plain Http calls, no Facebook SDK. The whole integration is one GET with five query
 * parameters; the SDK is a dependency and an auth abstraction to do the same thing.
 */
class FetchMetaAdsCosts extends Command
{
    protected $signature = 'traffic-source:fetch-meta-costs
                           {shop? : Shop slug, defaults to every shop configured for Meta Ads}
                           {--date= : Last day to fetch, defaults to yesterday}
                           {--days=1 : How many days to fetch, counting back from --date}
                           {--dry-run : Fetch and report without writing anything}';

    protected $description = 'Fetch advertising spend from the Meta Marketing API';

    private array $currencies = [];

    public function handle(): int
    {
        $until = $this->option('date') ? Carbon::parse($this->option('date')) : now()->subDay();
        $since = $until->copy()->subDays(max(1, (int) $this->option('days')) - 1);

        $shops = $this->shops();

        if ($shops->isEmpty()) {
            $this->error('No shop has settings.meta_ads.ad_account_id set.');

            return Command::FAILURE;
        }

        $failed = 0;

        foreach ($shops as $shop) {
            try {
                $stored = $this->fetchShop($shop, $since, $until);
                $this->info("{$shop->slug}: {$stored} channel campaign-day(s) of spend.");
            } catch (\Throwable $e) {
                $failed++;
                $this->error("{$shop->slug}: ".$e->getMessage());
                Log::error('Meta Ads cost fetch failed', ['shop' => $shop->slug, 'error' => $e->getMessage()]);
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

        return $shops->filter(fn (Shop $shop) => filled(data_get($shop->settings, 'meta_ads.ad_account_id')));
    }

    private function fetchShop(Shop $shop, Carbon $since, Carbon $until): int
    {
        $accountId = (string) data_get($shop->settings, 'meta_ads.ad_account_id');
        $token     = (string) (data_get($shop->settings, 'meta_ads.access_token')
            ?: config('services.meta_ads.access_token'));

        if ($token === '') {
            throw new \RuntimeException('no Meta access token, set services.meta_ads.access_token or the shop setting');
        }

        $trafficSources = TrafficSource::where('shop_id', $shop->id)
            ->whereIn('type', [TrafficSourcesTypeEnum::META_ADS->value, TrafficSourcesTypeEnum::INSTAGRAM_ADS->value])
            ->get()
            ->keyBy('type');

        foreach ([TrafficSourcesTypeEnum::META_ADS, TrafficSourcesTypeEnum::INSTAGRAM_ADS] as $required) {
            if (!$trafficSources->has($required->value)) {
                throw new \RuntimeException("shop has no {$required->value} traffic source, run traffic-source:seed");
            }
        }

        $version = config('services.meta_ads.api_version');
        $url     = "https://graph.facebook.com/{$version}/act_".preg_replace('/^act_/', '', $accountId).'/insights';

        $query = [
            'level'          => 'campaign',
            'fields'         => 'campaign_id,campaign_name,spend,account_currency',
            'breakdowns'     => 'publisher_platform',
            'time_increment' => 1,
            'time_range'     => json_encode(['since' => $since->toDateString(), 'until' => $until->toDateString()]),
            'limit'          => 500,
            'access_token'   => $token,
        ];

        $rows = [];
        $seen = [];

        /* Insights paginates, and an account with hundreds of campaigns over a multi-day backfill
           needs every page or the day's total silently comes back short.
           A next link is followed only once: a self-referential or cyclic one would otherwise spin
           here for ever, holding a worker and re-counting the same spend on every lap. */
        while ($url !== null && !isset($seen[$url])) {
            $seen[$url] = true;

            /* The next link already carries its own paging parameters, and passing a query array
               alongside it - even an empty one - replaces them. That asked Meta for page one again,
               counted its spend twice, and left the loop to stop on its own repeated-page guard with
               every later page unread. */
            $response = $query === null
                ? Http::timeout(60)->get($url)
                : Http::timeout(60)->get($url, $query);

            $query = null;

            if ($response->failed()) {
                throw new \RuntimeException(
                    'Meta returned '.$response->status().': '.data_get($response->json(), 'error.message', $response->body())
                );
            }

            foreach ($response->json('data', []) as $row) {
                $this->collectRow($rows, $row);
            }

            $url = $response->json('paging.next');
        }

        if ($url !== null) {
            Log::warning('Meta Ads pagination stopped on a repeated page', ['shop' => $shop->slug, 'url' => $url]);
        }

        return $this->store($trafficSources, $rows);
    }

    /**
     * Folds one platform-broken-down insights row into the channel it is reported under.
     *
     * The breakdown makes the grain (campaign, day, platform), while a cost row is keyed on (source,
     * campaign, day): Facebook, Audience Network and Messenger all land on the same Meta Ads key, so
     * they have to be summed here. Storing them one by one would have each overwrite the last and
     * report only whichever platform Meta happened to return last.
     *
     * @param array<string, array{type: string, campaign_id: ?string, campaign_name: ?string, date: ?string, currency: string, amount: float}> $rows
     */
    private function collectRow(array &$rows, array $row): void
    {
        $amount = (float) data_get($row, 'spend', 0);

        /* Meta reports delivered-but-unspent campaign days. Storing them would create a campaign row
           for every campaign that has never cost anything. */
        if ($amount <= 0) {
            return;
        }

        $type = data_get($row, 'publisher_platform') === 'instagram'
            ? TrafficSourcesTypeEnum::INSTAGRAM_ADS->value
            : TrafficSourcesTypeEnum::META_ADS->value;

        $key = $type.'|'.data_get($row, 'campaign_id').'|'.data_get($row, 'date_start');

        $rows[$key] ??= [
            'type'          => $type,
            'campaign_id'   => data_get($row, 'campaign_id'),
            'campaign_name' => data_get($row, 'campaign_name'),
            'date'          => data_get($row, 'date_start'),
            'currency'      => (string) data_get($row, 'account_currency'),
            'amount'        => 0.0,
        ];

        $rows[$key]['amount'] += $amount;
    }

    /**
     * @param \Illuminate\Support\Collection<string, TrafficSource>                                                          $trafficSources
     * @param array<string, array{type: string, campaign_id: ?string, campaign_name: ?string, date: ?string, currency: string, amount: float}> $rows
     */
    private function store(\Illuminate\Support\Collection $trafficSources, array $rows): int
    {
        foreach ($rows as $row) {
            $currency      = $this->currency($row['currency']);
            $trafficSource = $trafficSources->get($row['type']);

            if ($this->option('dry-run')) {
                $this->line(sprintf(
                    '  %s  %-14s %-40s %10.2f %s',
                    $row['date'],
                    $row['type'],
                    $row['campaign_name'],
                    $row['amount'],
                    $currency->code
                ));

                continue;
            }

            StoreTrafficSourceCost::run($trafficSource, [
                'date'                       => $row['date'],
                'source_amount'              => $row['amount'],
                'source_currency_id'         => $currency->id,
                'traffic_source_campaign_id' => GetTrafficSourceCampaign::run(
                    $trafficSource,
                    $this->campaignReference($row['type'], $row['campaign_id']),
                    $row['campaign_name']
                ),
            ]);
        }

        return count($rows);
    }

    /**
     * Instagram claims a prefixed reference because the Facebook half of the same Meta campaign already
     * holds the bare id, and references are unique across sources. The click side prefixes identically,
     * so the two still meet on one campaign row per channel.
     */
    private function campaignReference(string $type, ?string $campaignId): ?string
    {
        if (blank($campaignId)) {
            return null;
        }

        return $type === TrafficSourcesTypeEnum::INSTAGRAM_ADS->value
            ? TrafficSourcesTypeEnum::INSTAGRAM_CAMPAIGN_PREFIX.$campaignId
            : $campaignId;
    }

    private function currency(string $code): Currency
    {
        return $this->currencies[$code] ??= Currency::where('code', strtoupper($code))->firstOr(
            fn () => throw new \RuntimeException("unknown currency '{$code}'")
        );
    }
}
