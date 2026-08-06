<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 05 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\CRM\TrafficSource\ProcessTrafficSourceShare;
use App\Actions\CRM\TrafficSource\RecalculateTrafficSourceAttribution;
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

    $this->customer = createCustomer($this->shop);

    $this->customer->trafficSources()->detach();
    $this->customer->update(['traffic_sources' => null]);

    createTrafficSource($this->shop, 'organic-google', 'Organic Google');

    createTrafficSource($this->shop, 'google-ads', 'Google Ads');
});

it('rebuilds attribution for a customer under the requested model', function () {
    $this->customer->update(['traffic_sources' => '1700000000a|1700000100b']);

    RecalculateTrafficSourceAttribution::run($this->customer->fresh(), ProcessTrafficSourceShare::ATTRIBUTION_FIRST_TOUCH);

    $trafficSources = $this->customer->trafficSources()->get();
    expect($trafficSources)->toHaveCount(1);
    expect($trafficSources->first()->type)->toBe('organic-google');
    expect((float) $trafficSources->first()->pivot->share)->toBe(1.0);
    expect($trafficSources->first()->pivot->attribution_model)->toBe(ProcessTrafficSourceShare::ATTRIBUTION_FIRST_TOUCH);
});

it('is idempotent: rerunning the same model does not create duplicate pivot rows', function () {
    $this->customer->update(['traffic_sources' => '1700000000a|1700000100b']);

    RecalculateTrafficSourceAttribution::run($this->customer->fresh(), ProcessTrafficSourceShare::ATTRIBUTION_LINEAR);
    RecalculateTrafficSourceAttribution::run($this->customer->fresh(), ProcessTrafficSourceShare::ATTRIBUTION_LINEAR);

    expect($this->customer->trafficSources()->count())->toBe(2);
});

it('replaces the attribution cleanly when switching models', function () {
    $this->customer->update(['traffic_sources' => '1700000000a|1700000100b']);

    RecalculateTrafficSourceAttribution::run($this->customer->fresh(), ProcessTrafficSourceShare::ATTRIBUTION_LINEAR);
    expect($this->customer->trafficSources()->count())->toBe(2);

    RecalculateTrafficSourceAttribution::run($this->customer->fresh(), ProcessTrafficSourceShare::ATTRIBUTION_LAST_TOUCH);

    $trafficSources = $this->customer->trafficSources()->get();
    expect($trafficSources)->toHaveCount(1);
    expect($trafficSources->first()->type)->toBe('google-ads');
});

it('detaches everything and does nothing else when there is no touch history', function () {
    RecalculateTrafficSourceAttribution::run($this->customer->fresh(), ProcessTrafficSourceShare::ATTRIBUTION_LINEAR);

    expect($this->customer->trafficSources()->count())->toBe(0);
});

it('recalculates customers via the artisan command', function () {
    $this->customer->update(['traffic_sources' => '1700000000a|1700000100b']);

    Artisan::call('traffic-source:recalculate-attribution', [
        '--shop'  => $this->shop->slug,
        '--type'  => 'customers',
        '--model' => ProcessTrafficSourceShare::ATTRIBUTION_FIRST_TOUCH,
    ]);

    $trafficSources = $this->customer->trafficSources()->get();
    expect($trafficSources)->toHaveCount(1);
    expect($trafficSources->first()->type)->toBe('organic-google');
});

it('does not modify records when the artisan command runs in dry-run mode', function () {
    $this->customer->update(['traffic_sources' => '1700000000a|1700000100b']);

    Artisan::call('traffic-source:recalculate-attribution', [
        '--shop'    => $this->shop->slug,
        '--type'    => 'customers',
        '--model'   => ProcessTrafficSourceShare::ATTRIBUTION_FIRST_TOUCH,
        '--dry-run' => true,
    ]);

    expect($this->customer->trafficSources()->count())->toBe(0);
});
