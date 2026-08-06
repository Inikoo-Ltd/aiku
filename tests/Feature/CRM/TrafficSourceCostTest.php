<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 06 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\CRM\TrafficSource\Hydrator\TrafficSourceHydrateCosts;
use App\Actions\CRM\TrafficSource\StoreTrafficSourceCost;
use App\Actions\CRM\TrafficSource\UI\IndexTrafficSources;
use App\Models\CRM\TrafficSourceCampaign;
use App\Models\CRM\TrafficSourceCost;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Artisan;

beforeAll(function () {
    loadDB();
});

/**
 * IndexTrafficSources names its paginator after the current route, which does not exist when the
 * action is called directly rather than through a request.
 */
function fakeCurrentRoute(): void
{
    $route = (new Route('GET', '/_test', []))->name('test.traffic_sources.index');

    request()->setRouteResolver(fn () => $route);
}

beforeEach(function () {
    Artisan::call('migrate');

    list(
        $this->organisation,
        $this->user,
        $this->shop
    ) = createShop();

    $this->trafficSource = createTrafficSource($this->shop, 'google-ads', 'Google Ads');
    $this->currency      = $this->shop->currency;

    // The shop and traffic source are shared across the tests in this file, so clear anything a
    // previous test left on them.
    TrafficSourceCost::where('traffic_source_id', $this->trafficSource->id)->delete();
    $this->trafficSource->stats()->updateOrCreate(
        ['traffic_source_id' => $this->trafficSource->id],
        [
            'total_cost'                 => 0,
            'org_total_cost'             => 0,
            'total_customer_revenue'     => 0,
            'org_total_customer_revenue' => 0,
            'number_customers'           => 0,
        ]
    );
});

it('records a cost and rolls it into the traffic source stats', function () {
    StoreTrafficSourceCost::run($this->trafficSource, [
        'date'               => '2026-08-01',
        'source_amount'      => 153.22,
        'source_currency_id' => $this->currency->id,
    ]);

    TrafficSourceHydrateCosts::run($this->trafficSource);

    expect((float) $this->trafficSource->stats()->first()->total_cost)->toBe(153.22);
});

it('keeps the originally billed amount alongside the converted one', function () {
    $cost = StoreTrafficSourceCost::run($this->trafficSource, [
        'date'               => '2026-08-01',
        'source_amount'      => 99.5,
        'source_currency_id' => $this->currency->id,
    ]);

    expect((float) $cost->source_amount)->toBe(99.5);
    expect($cost->source_currency_id)->toBe($this->currency->id);
    expect((float) $cost->amount)->toBe(99.5);
});

it('updates rather than duplicates when the same day is imported again', function () {
    foreach ([100.00, 137.65] as $amount) {
        StoreTrafficSourceCost::run($this->trafficSource, [
            'date'               => '2026-08-01',
            'source_amount'      => $amount,
            'source_currency_id' => $this->currency->id,
        ]);
    }

    $costs = TrafficSourceCost::where('traffic_source_id', $this->trafficSource->id)->get();

    expect($costs)->toHaveCount(1);
    expect((float) $costs->first()->source_amount)->toBe(137.65);
});

it('keeps campaign level and source level spend as separate rows for the same day', function () {
    $campaign = TrafficSourceCampaign::create([
        'traffic_source_id' => $this->trafficSource->id,
        'reference'         => 'camp-'.uniqid(),
        'name'              => 'August Push',
        'type'              => 'search',
    ]);

    StoreTrafficSourceCost::run($this->trafficSource, [
        'date'               => '2026-08-01',
        'source_amount'      => 40.00,
        'source_currency_id' => $this->currency->id,
    ]);

    StoreTrafficSourceCost::run($this->trafficSource, [
        'date'                       => '2026-08-01',
        'source_amount'              => 60.00,
        'source_currency_id'         => $this->currency->id,
        'traffic_source_campaign_id' => $campaign->id,
    ]);

    TrafficSourceHydrateCosts::run($this->trafficSource);

    expect(TrafficSourceCost::where('traffic_source_id', $this->trafficSource->id)->count())->toBe(2);
    expect((float) $this->trafficSource->stats()->first()->total_cost)->toBe(100.0);
});

it('sums spend across days', function () {
    foreach (['2026-08-01' => 10.00, '2026-08-02' => 15.50] as $date => $amount) {
        StoreTrafficSourceCost::run($this->trafficSource, [
            'date'               => $date,
            'source_amount'      => $amount,
            'source_currency_id' => $this->currency->id,
        ]);
    }

    TrafficSourceHydrateCosts::run($this->trafficSource);

    expect((float) $this->trafficSource->stats()->first()->total_cost)->toBe(25.5);
});

it('exposes cost, roas and cac on the shop traffic sources listing', function () {
    StoreTrafficSourceCost::run($this->trafficSource, [
        'date'               => '2026-08-01',
        'source_amount'      => 50.00,
        'source_currency_id' => $this->currency->id,
    ]);

    TrafficSourceHydrateCosts::run($this->trafficSource);

    $this->trafficSource->stats()->update([
        'total_customer_revenue' => 200.00,
        'number_customers'       => 4,
    ]);

    fakeCurrentRoute();

    $row = IndexTrafficSources::make()->handle($this->shop)
        ->firstWhere('id', $this->trafficSource->id);

    expect((float) $row->cost)->toBe(50.0);
    expect((float) $row->roas)->toBe(4.0);
    expect((float) $row->cac)->toBe(12.5);
});

it('reports no roas or cac for a source with no recorded spend', function () {
    $this->trafficSource->stats()->update([
        'total_customer_revenue' => 200.00,
        'number_customers'       => 4,
    ]);

    fakeCurrentRoute();

    $row = IndexTrafficSources::make()->handle($this->shop)
        ->firstWhere('id', $this->trafficSource->id);

    expect($row->roas)->toBeNull();
    expect($row->cac)->toBeNull();
});
