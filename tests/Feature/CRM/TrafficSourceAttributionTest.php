<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 05 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\CRM\Customer\StoreCustomer;
use App\Models\CRM\Customer;
use App\Models\CRM\TrafficSourceCampaign;
use Illuminate\Support\Facades\Artisan;

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
});

it('attaches a single traffic source without a campaign to a newly registered customer', function () {
    $trafficSource = createTrafficSource($this->shop, 'organic-google', 'Organic Google');

    $customer = StoreCustomer::make()->action(
        $this->shop,
        array_merge(Customer::factory()->definition(), [
            'traffic_sources' => '1700000000a',
        ])
    );

    expect($customer->trafficSources()->count())->toBe(1);

    $pivot = $customer->trafficSources()->first()->pivot;
    expect($pivot->traffic_source_id)->toBe($trafficSource->id);
    expect((float) $pivot->share)->toBe(1.0);
    expect($pivot->traffic_source_campaign_id)->toBeNull();
});

it('splits credit across multiple distinct traffic sources on registration', function () {
    createTrafficSource($this->shop, 'organic-google', 'Organic Google');

    createTrafficSource($this->shop, 'google-ads', 'Google Ads');

    $customer = StoreCustomer::make()->action(
        $this->shop,
        array_merge(Customer::factory()->definition(), [
            'traffic_sources' => '1700000000a|1700000100b',
        ])
    );

    expect($customer->trafficSources()->count())->toBe(2);

    $totalShare = (float) $customer->trafficSources()->sum('model_has_traffic_sources.share');
    expect(round($totalShare, 2))->toBe(1.0);
});

it('links the matching campaign reference to the pivot record', function () {
    $trafficSource = createTrafficSource($this->shop, 'google-ads', 'Google Ads');

    $campaign = TrafficSourceCampaign::create([
        'traffic_source_id' => $trafficSource->id,
        'reference'         => 'summer-'.uniqid(),
        'name'              => 'Summer Sale',
        'type'              => 'search',
    ]);

    $customer = StoreCustomer::make()->action(
        $this->shop,
        array_merge(Customer::factory()->definition(), [
            'traffic_sources' => '1700000000b'.$campaign->reference,
        ])
    );

    $pivot = $customer->trafficSources()->first()->pivot;
    expect($pivot->traffic_source_campaign_id)->toBe($campaign->id);
});

it('keeps the full credit on the source when two of its campaigns are both credited', function () {
    $trafficSource = createTrafficSource($this->shop, 'google-ads', 'Google Ads');

    $campaigns = collect(['spring', 'summer'])->map(fn (string $season) => TrafficSourceCampaign::create([
        'traffic_source_id' => $trafficSource->id,
        'reference'         => $season.'-'.uniqid(),
        'name'              => ucfirst($season).' Sale',
        'type'              => 'search',
    ]));

    $customer = StoreCustomer::make()->action(
        $this->shop,
        array_merge(Customer::factory()->definition(), [
            'traffic_sources' => '1700000000b'.$campaigns[0]->reference.'|1700000100b'.$campaigns[1]->reference,
        ])
    );

    expect($customer->trafficSources()->count())->toBe(1);

    $pivot = $customer->trafficSources()->first()->pivot;
    expect((float) $pivot->share)->toBe(1.0);
    expect($pivot->traffic_source_campaign_id)->toBeNull();
});

it('does not attach anything when the abbreviation does not match a known traffic source type', function () {
    $customer = StoreCustomer::make()->action(
        $this->shop,
        array_merge(Customer::factory()->definition(), [
            'traffic_sources' => '1700000000z',
        ])
    );

    expect($customer->trafficSources()->count())->toBe(0);
});

it('ignores blank traffic source data', function () {
    $customer = StoreCustomer::make()->action(
        $this->shop,
        array_merge(Customer::factory()->definition(), [
            'traffic_sources' => null,
        ])
    );

    expect($customer->trafficSources()->count())->toBe(0);
});
