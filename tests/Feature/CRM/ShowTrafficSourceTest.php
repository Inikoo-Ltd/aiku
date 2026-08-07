<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\CRM\Customer\UI\IndexCustomers;
use App\Actions\CRM\TrafficSource\RecalculateTrafficSourceAttribution;
use App\Actions\CRM\TrafficSource\UI\TrafficSourceTabsEnum;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Artisan;

beforeAll(function () {
    loadDB();
});

function fakeRouteForCustomers(): void
{
    $route = (new Route('GET', '/_test', []))->name('test.traffic_sources.show');

    request()->setRouteResolver(fn () => $route);
}

beforeEach(function () {
    Artisan::call('migrate');

    list(
        $this->organisation,
        $this->user,
        $this->shop
    ) = createShop();

    $this->googleAds = createTrafficSource($this->shop, 'google-ads', 'Google Ads');
    $this->organic   = createTrafficSource($this->shop, 'organic-google', 'Organic Google');

    $this->customer = createCustomer($this->shop);
    $this->customer->trafficSources()->detach();
});

it('lists the customers attributed to a traffic source', function () {
    $this->customer->update(['traffic_sources' => '1700000000b']);
    RecalculateTrafficSourceAttribution::run($this->customer->fresh());

    fakeRouteForCustomers();

    $customers = IndexCustomers::make()->handle($this->googleAds, TrafficSourceTabsEnum::CUSTOMERS->value);

    expect($customers->pluck('id'))->toContain($this->customer->id);
});

it('shows how much of a shared customer the source actually owns', function () {
    $this->customer->update(['traffic_sources' => '1700000000b|1700000100a']);
    RecalculateTrafficSourceAttribution::run($this->customer->fresh());

    fakeRouteForCustomers();

    $row = IndexCustomers::make()->handle($this->googleAds, TrafficSourceTabsEnum::CUSTOMERS->value)
        ->firstWhere('id', $this->customer->id);

    expect((float) $row->attribution_share)->toBe(0.5);
});

it('excludes customers attributed to a different source', function () {
    $this->customer->update(['traffic_sources' => '1700000000b']);
    RecalculateTrafficSourceAttribution::run($this->customer->fresh());

    fakeRouteForCustomers();

    $customers = IndexCustomers::make()->handle($this->organic, TrafficSourceTabsEnum::CUSTOMERS->value);

    expect($customers->pluck('id'))->not->toContain($this->customer->id);
});
