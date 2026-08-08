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

/* Named apart from the helpers in MetaAdsCostFetchTest: Pest test files share one process, so two
   global functions of the same name are a fatal redeclaration rather than an override. */
function igSplitInsights(array $rows): array
{
    return ['data' => $rows];
}

function igSplitRow(string $campaignId, float $spend, string $currency, string $platform, string $date = '2026-08-07'): array
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

it('reports instagram spend as its own channel', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(igSplitInsights([
            igSplitRow('120000009', 40.00, $this->shop->currency->code, 'facebook'),
            igSplitRow('120000009', 15.00, $this->shop->currency->code, 'instagram'),
        ])),
    ]);

    Artisan::call('traffic-source:fetch-meta-costs', ['shop' => $this->shop->slug]);

    $meta      = TrafficSourceCost::where('traffic_source_id', $this->metaAds->id)->get();
    $instagram = TrafficSourceCost::where('traffic_source_id', $this->instagramAds->id)->get();

    expect((float) $meta->sum('source_amount'))->toBe(40.00);
    expect((float) $instagram->sum('source_amount'))->toBe(15.00);
});

it('keeps a campaign row for each half of the same meta campaign', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(igSplitInsights([
            igSplitRow('120000012', 40.00, $this->shop->currency->code, 'facebook'),
            igSplitRow('120000012', 15.00, $this->shop->currency->code, 'instagram'),
        ])),
    ]);

    Artisan::call('traffic-source:fetch-meta-costs', ['shop' => $this->shop->slug]);

    /* References are unique across every traffic source, so both halves only keep their campaign
       breakdown while the Instagram one claims a different string. */
    expect(TrafficSourceCampaign::where('reference', '120000012')->value('traffic_source_id'))->toBe($this->metaAds->id);
    expect(TrafficSourceCampaign::where('reference', 'ig-120000012')->value('traffic_source_id'))->toBe($this->instagramAds->id);
});

it('sums the non instagram platforms into one meta ads row', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(igSplitInsights([
            igSplitRow('120000010', 10.00, $this->shop->currency->code, 'facebook'),
            igSplitRow('120000010', 4.00, $this->shop->currency->code, 'audience_network'),
            igSplitRow('120000010', 1.50, $this->shop->currency->code, 'messenger'),
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
        'graph.facebook.com/*' => Http::response(igSplitInsights([
            igSplitRow('120000011', 10.00, $this->shop->currency->code, 'facebook'),
        ])),
    ]);

    $exit = Artisan::call('traffic-source:fetch-meta-costs', ['shop' => $this->shop->slug]);

    expect($exit)->toBe(1);
    expect(TrafficSourceCost::where('traffic_source_id', $this->metaAds->id)->get())->toHaveCount(0);
});
