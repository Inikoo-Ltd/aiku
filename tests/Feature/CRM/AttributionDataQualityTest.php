<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\CRM\TrafficSource\GetShopAttributionDataQuality;
use App\Actions\CRM\TrafficSource\RecalculateTrafficSourceAttribution;
use App\Enums\UI\Marketing\MarketingPeriodEnum;
use App\Models\CRM\TrafficSource;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

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

    $this->googleAds = createTrafficSource($this->shop, 'google-ads', 'Google Ads');
    $this->customer  = createCustomer($this->shop);
    $this->customer->trafficSources()->detach();
});

function dataQualityCheck(string $key, $shop, MarketingPeriodEnum $period = MarketingPeriodEnum::LAST_30): array
{
    $checks = GetShopAttributionDataQuality::run($shop, $period)['checks'];

    return collect($checks)->firstWhere('key', $key);
}

it('reports registrations with no attribution as a proportion', function () {
    $this->customer->update(['traffic_sources' => now()->subDay()->timestamp.'b']);
    RecalculateTrafficSourceAttribution::run($this->customer->fresh());

    $unattributed = createCustomer($this->shop);
    $unattributed->trafficSources()->detach();

    $check = dataQualityCheck('registrations_without_attribution', $this->shop);

    $registrations = DB::table('customers')
        ->where('shop_id', $this->shop->id)
        ->where('created_at', '>=', now()->subDays(30)->startOfDay())
        ->count();

    expect($check['value'])->toContain('/ '.$registrations);
});

it('flags customers whose attribution shares do not sum to one', function () {
    $this->customer->trafficSources()->attach($this->googleAds->id, ['share' => 0.5]);

    $check = dataQualityCheck('shares_not_summing_to_one', $this->shop);

    expect($check['status'])->toBe(GetShopAttributionDataQuality::STATUS_ERROR)
        ->and($check['items'][0])->toContain('#'.$this->customer->id)
        ->and($check['items'][0])->toContain('0.50');
});

it('passes the share invariant check for a correctly attributed customer', function () {
    $this->customer->update(['traffic_sources' => now()->subDay()->timestamp.'b|'.now()->timestamp.'a']);
    RecalculateTrafficSourceAttribution::run($this->customer->fresh());

    $check = dataQualityCheck('shares_not_summing_to_one', $this->shop);

    expect($check['status'])->toBe(GetShopAttributionDataQuality::STATUS_OK)
        ->and($check['items'])->toBe([]);
});

it('flags a channel with no traffic source row in the shop', function () {
    TrafficSource::where('shop_id', $this->shop->id)->where('type', 'newsletter')->delete();

    $check = dataQualityCheck('missing_traffic_sources', $this->shop);

    expect($check['status'])->toBe(GetShopAttributionDataQuality::STATUS_ERROR)
        ->and($check['items'])->toContain('Newsletter');
});

it('lists channels that have never been credited a customer', function () {
    $this->customer->update(['traffic_sources' => now()->subDay()->timestamp.'b']);
    RecalculateTrafficSourceAttribution::run($this->customer->fresh());

    $check = dataQualityCheck('never_credited_traffic_sources', $this->shop);

    expect($check['items'])->not->toContain('Google Ads')
        ->and($check['status'])->toBe(GetShopAttributionDataQuality::STATUS_WARNING);
});

it('flags campaign references that matched no campaign', function () {
    $this->customer->update(['traffic_sources' => now()->subDay()->timestamp.'b'.'not-a-campaign']);

    $check = dataQualityCheck('unmatched_campaign_references', $this->shop);

    expect($check['status'])->toBe(GetShopAttributionDataQuality::STATUS_WARNING)
        ->and($check['items'][0])->toContain('not-a-campaign');
});

it('does not flag a campaign reference that resolves to a campaign', function () {
    $this->customer->update(['traffic_sources' => now()->subDay()->timestamp.'b'.'987654']);
    RecalculateTrafficSourceAttribution::run($this->customer->fresh());

    $check = dataQualityCheck('unmatched_campaign_references', $this->shop);

    expect($check['status'])->toBe(GetShopAttributionDataQuality::STATUS_OK)
        ->and($check['items'])->toBe([]);
});

it('surfaces the capture counters so nobody has to run a command to see them', function () {
    $day = now()->toDateString();

    Illuminate\Support\Facades\Cache::put('traffic_capture:'.$day.':anon:matched', 8, now()->addDay());
    Illuminate\Support\Facades\Cache::put('traffic_capture:'.$day.':anon:direct', 2, now()->addDay());
    Illuminate\Support\Facades\Cache::put('traffic_capture:'.$day.':hosts', ['app.aiku.io' => 26], now()->addDay());

    $capture = GetShopAttributionDataQuality::run($this->shop)['capture'];

    expect($capture['hits'])->toBe(10)
        ->and($capture['identified_pct'])->toBe(80.0)
        ->and($capture['rejected'][0])->toBe(['host' => 'app.aiku.io', 'hits' => 26]);
});
