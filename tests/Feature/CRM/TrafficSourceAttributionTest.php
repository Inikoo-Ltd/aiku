<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 05 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\CRM\TrafficSource\Hydrator\TrafficSourceHydrateCustomers;
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

it('keeps a row per campaign with the source credit summing to one', function () {
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

    $rows = $customer->trafficSources()->get();

    expect($rows)->toHaveCount(2);
    expect($rows->pluck('pivot.traffic_source_campaign_id')->sort()->values()->all())
        ->toBe([$campaigns[0]->id, $campaigns[1]->id]);
    expect(round($rows->sum(fn ($row) => (float) $row->pivot->share), 2))->toBe(1.0);
});

it('creates the campaign from a touch when the reference looks like an ad platform id', function () {
    createTrafficSource($this->shop, 'google-ads', 'Google Ads');

    $reference = (string) random_int(10000000000, 99999999999);

    $customer = StoreCustomer::make()->action(
        $this->shop,
        array_merge(Customer::factory()->definition(), [
            'traffic_sources' => '1700000000b'.$reference,
        ])
    );

    $campaign = TrafficSourceCampaign::where('reference', $reference)->first();

    expect($campaign)->not->toBeNull();
    expect($customer->trafficSources()->first()->pivot->traffic_source_campaign_id)->toBe($campaign->id);
});

it('refuses to mint a campaign from a non-numeric reference', function () {
    createTrafficSource($this->shop, 'google-ads', 'Google Ads');

    $reference = 'crafted-'.uniqid();

    $customer = StoreCustomer::make()->action(
        $this->shop,
        array_merge(Customer::factory()->definition(), [
            'traffic_sources' => '1700000000b'.$reference,
        ])
    );

    expect(TrafficSourceCampaign::where('reference', $reference)->exists())->toBeFalse();

    $pivot = $customer->trafficSources()->first()->pivot;
    expect($pivot->traffic_source_campaign_id)->toBeNull();
    expect((float) $pivot->share)->toBe(1.0);
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

it('does not credit a channel with a registration that happened before the touch', function () {
    $newsletter = createTrafficSource($this->shop, 'newsletter', 'Newsletter');
    $customer   = createCustomer($this->shop);
    $customer->trafficSources()->detach();
    $customer->update(['created_at' => now()->subDays(30)]);

    $customer->trafficSources()->attach($newsletter->id, [
        'share'          => 1,
        'first_touch_at' => now()->subDay(),
        'last_touch_at'  => now()->subDay(),
    ]);

    TrafficSourceHydrateCustomers::run($newsletter);

    expect((float) $newsletter->stats->fresh()->number_customers)->toBe(0.0);
});

it('credits a channel with a registration that followed the touch', function () {
    $organic  = createTrafficSource($this->shop, 'organic-google', 'Organic Google');
    $customer = createCustomer($this->shop);
    $customer->trafficSources()->detach();
    $customer->update(['created_at' => now()->subDay()]);

    $customer->trafficSources()->attach($organic->id, [
        'share'          => 1,
        'first_touch_at' => now()->subDays(2),
        'last_touch_at'  => now()->subDays(2),
    ]);

    TrafficSourceHydrateCustomers::run($organic);

    expect((float) $organic->stats->fresh()->number_customers)->toBe(1.0);
});

it('gives the whole credit to the remaining touches when a channel has no row in the shop', function () {
    $shop = $this->shop;

    createTrafficSource($shop, 'organic-google', 'Organic Google');
    App\Models\CRM\TrafficSource::where('shop_id', $shop->id)->where('type', 'organic-bing')->delete();

    $customer = createCustomer($shop);
    $customer->trafficSources()->detach();
    $customer->update([
        'traffic_sources' => now()->subDays(2)->timestamp.'a|'.now()->subDay()->timestamp.'c',
    ]);

    App\Actions\CRM\TrafficSource\RecalculateTrafficSourceAttribution::run($customer->fresh());

    $credited = $customer->trafficSources()->get();

    expect($credited)->toHaveCount(1)
        ->and($credited->first()->type)->toBe('organic-google')
        ->and((float) $credited->first()->pivot->share)->toBe(1.0);
});

it('refreshes a channel\'s rollups when the customer is invoiced, not only when a touch lands', function () {
    $source   = createTrafficSource($this->shop, 'organic-google', 'Organic Google');
    $customer = createCustomer($this->shop);
    $customer->trafficSources()->detach();
    $customer->trafficSources()->attach($source->id, [
        'share'          => 1,
        'first_touch_at' => now()->subDays(2),
        'last_touch_at'  => now()->subDays(2),
    ]);

    createInvoiceFor($customer, $this->shop, now()->subDay()->toDateTimeString(), 400);

    App\Actions\CRM\TrafficSource\Hydrator\RefreshCustomerTrafficSourceStats::run($customer->fresh());

    expect((float) $source->stats()->first()->total_customer_revenue)->toBe(400.0);
});
