<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Models\CRM\TrafficSourceCampaign;
use App\Models\CRM\TrafficSourceCost;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    Artisan::call('migrate');

    list(
        $this->organisation,
        $this->user,
        $this->shop
    ) = createShop();

    $this->metaAds      = createTrafficSource($this->shop, 'meta-ads', 'Meta Ads');
    $this->instagramAds = createTrafficSource($this->shop, 'instagram-ads', 'Instagram Ads');

    $settings = $this->shop->settings ?? [];
    data_set($settings, 'meta_ads.ad_account_id', '1234567890');
    $this->shop->update(['settings' => $settings]);

    config()->set('services.meta_ads.access_token', 'system-user-token');

    TrafficSourceCost::where('shop_id', $this->shop->id)->delete();
});

function metaInsights(array $rows, ?string $next = null): array
{
    return array_filter([
        'data'   => $rows,
        'paging' => $next ? ['next' => $next] : null,
    ]);
}

function metaRow(string $campaignId, float $spend, string $currency, string $date = '2026-08-07', string $platform = 'facebook'): array
{
    return [
        'campaign_id'        => $campaignId,
        'campaign_name'      => 'Campaign '.$campaignId,
        'publisher_platform' => $platform,
        'spend'              => (string) $spend,
        'account_currency'   => $currency,
        'date_start'         => $date,
        'date_stop'          => $date,
    ];
}

it('stores a day of campaign spend from the insights api', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(metaInsights([
            metaRow('120000001', 88.40, $this->shop->currency->code),
            metaRow('120000002', 12.10, $this->shop->currency->code),
        ])),
    ]);

    $exit = Artisan::call('traffic-source:fetch-meta-costs', ['shop' => $this->shop->slug]);

    expect($exit)->toBe(0);

    $costs = TrafficSourceCost::where('traffic_source_id', $this->metaAds->id)->get();
    expect($costs)->toHaveCount(2);
    expect((float) $costs->sum('source_amount'))->toBe(100.50);
});

it('credits spend to the campaign the touch history already knows', function () {
    $campaign = TrafficSourceCampaign::create([
        'traffic_source_id' => $this->metaAds->id,
        'reference'         => '120000003',
        'name'              => 'August Push',
        'type'              => 'meta-ads',
    ]);

    Http::fake([
        'graph.facebook.com/*' => Http::response(metaInsights([
            metaRow('120000003', 25.00, $this->shop->currency->code),
        ])),
    ]);

    Artisan::call('traffic-source:fetch-meta-costs', ['shop' => $this->shop->slug]);

    $cost = TrafficSourceCost::where('traffic_source_id', $this->metaAds->id)->first();
    expect($cost->traffic_source_campaign_id)->toBe($campaign->id);
});

it('replaces rather than adds when the same day is fetched twice', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::sequence()
            ->push(metaInsights([metaRow('120000004', 50.00, $this->shop->currency->code)]))
            ->push(metaInsights([metaRow('120000004', 57.25, $this->shop->currency->code)])),
    ]);

    Artisan::call('traffic-source:fetch-meta-costs', ['shop' => $this->shop->slug]);
    Artisan::call('traffic-source:fetch-meta-costs', ['shop' => $this->shop->slug]);

    $costs = TrafficSourceCost::where('traffic_source_id', $this->metaAds->id)->get();
    expect($costs)->toHaveCount(1);
    expect((float) $costs->first()->source_amount)->toBe(57.25);
});

it('follows pagination so a long day is not silently cut short', function () {
    Http::fake([
        'graph.facebook.com/*page2*' => Http::response(metaInsights([
            metaRow('120000006', 10.00, $this->shop->currency->code),
        ])),
        'graph.facebook.com/*' => Http::response(metaInsights(
            [metaRow('120000005', 10.00, $this->shop->currency->code)],
            'https://graph.facebook.com/v21.0/act_1234567890/insights?page2=1'
        )),
    ]);

    Artisan::call('traffic-source:fetch-meta-costs', ['shop' => $this->shop->slug]);

    expect(TrafficSourceCost::where('traffic_source_id', $this->metaAds->id)->get())->toHaveCount(2);
});

it('skips campaign days that cost nothing', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(metaInsights([
            metaRow('120000007', 0, $this->shop->currency->code),
        ])),
    ]);

    Artisan::call('traffic-source:fetch-meta-costs', ['shop' => $this->shop->slug]);

    expect(TrafficSourceCost::where('traffic_source_id', $this->metaAds->id)->get())->toHaveCount(0);
    expect(TrafficSourceCampaign::where('reference', '120000007')->first())->toBeNull();
});

it('fails loudly when meta rejects the token', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid OAuth access token.']], 401),
    ]);

    $exit = Artisan::call('traffic-source:fetch-meta-costs', ['shop' => $this->shop->slug]);

    expect($exit)->toBe(1);
    expect(TrafficSourceCost::where('traffic_source_id', $this->metaAds->id)->get())->toHaveCount(0);
});

it('reports instagram spend as its own channel', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(metaInsights([
            metaRow('120000009', 40.00, $this->shop->currency->code, '2026-08-07', 'facebook'),
            metaRow('120000009', 15.00, $this->shop->currency->code, '2026-08-07', 'instagram'),
        ])),
    ]);

    Artisan::call('traffic-source:fetch-meta-costs', ['shop' => $this->shop->slug]);

    $meta      = TrafficSourceCost::where('traffic_source_id', $this->metaAds->id)->get();
    $instagram = TrafficSourceCost::where('traffic_source_id', $this->instagramAds->id)->get();

    expect((float) $meta->sum('source_amount'))->toBe(40.00);
    expect((float) $instagram->sum('source_amount'))->toBe(15.00);

    /* Both halves of the campaign keep a campaign row, which they only can if their references differ. */
    expect(TrafficSourceCampaign::where('reference', '120000009')->value('traffic_source_id'))->toBe($this->metaAds->id);
    expect(TrafficSourceCampaign::where('reference', 'ig-120000009')->value('traffic_source_id'))->toBe($this->instagramAds->id);
});

it('sums the non instagram platforms into one meta ads row', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(metaInsights([
            metaRow('120000010', 10.00, $this->shop->currency->code, '2026-08-07', 'facebook'),
            metaRow('120000010', 4.00, $this->shop->currency->code, '2026-08-07', 'audience_network'),
            metaRow('120000010', 1.50, $this->shop->currency->code, '2026-08-07', 'messenger'),
        ])),
    ]);

    Artisan::call('traffic-source:fetch-meta-costs', ['shop' => $this->shop->slug]);

    $costs = TrafficSourceCost::where('traffic_source_id', $this->metaAds->id)->get();

    expect($costs)->toHaveCount(1);
    expect((float) $costs->first()->source_amount)->toBe(15.50);
});

it('fails loudly when the instagram source has not been seeded', function () {
    $this->instagramAds->delete();

    Http::fake([
        'graph.facebook.com/*' => Http::response(metaInsights([
            metaRow('120000011', 10.00, $this->shop->currency->code),
        ])),
    ]);

    $exit = Artisan::call('traffic-source:fetch-meta-costs', ['shop' => $this->shop->slug]);

    expect($exit)->toBe(1);
    expect(TrafficSourceCost::where('traffic_source_id', $this->metaAds->id)->get())->toHaveCount(0);
});

it('writes nothing on a dry run', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(metaInsights([
            metaRow('120000008', 33.00, $this->shop->currency->code),
        ])),
    ]);

    Artisan::call('traffic-source:fetch-meta-costs', ['shop' => $this->shop->slug, '--dry-run' => true]);

    expect(TrafficSourceCost::where('traffic_source_id', $this->metaAds->id)->get())->toHaveCount(0);
});
