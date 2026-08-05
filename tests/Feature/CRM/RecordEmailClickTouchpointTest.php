<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 05 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\CRM\TrafficSource\RecordEmailClickTouchpoint;
use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use Illuminate\Support\Carbon;
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
    $this->customer->update(['traffic_sources' => null]);
    $this->customer->trafficSources()->detach();
});

it('records an email click as a newsletter touch and attributes it', function () {
    RecordEmailClickTouchpoint::run($this->customer, Carbon::parse('2026-01-01 10:00:00'));

    $trafficSources = $this->customer->fresh()->trafficSources()->get();

    expect($trafficSources)->toHaveCount(1);
    expect($trafficSources->first()->type)->toBe(TrafficSourcesTypeEnum::NEWSLETTER->value);
    expect((float) $trafficSources->first()->pivot->share)->toBe(1.0);
});

it('does not record a duplicate touch for a repeat click on the same day', function () {
    RecordEmailClickTouchpoint::run($this->customer, Carbon::parse('2026-01-01 10:00:00'));
    RecordEmailClickTouchpoint::run($this->customer->fresh(), Carbon::parse('2026-01-01 18:00:00'));

    $touches = explode('|', $this->customer->fresh()->traffic_sources);

    expect($touches)->toHaveCount(1);
});

it('records a new touch for a click occurring on a later day', function () {
    RecordEmailClickTouchpoint::run($this->customer, Carbon::parse('2026-01-01 10:00:00'));
    RecordEmailClickTouchpoint::run($this->customer->fresh(), Carbon::parse('2026-01-02 10:00:00'));

    $touches = explode('|', $this->customer->fresh()->traffic_sources);

    expect($touches)->toHaveCount(2);
});

it('preserves earlier touches and layers the newsletter click on top for attribution', function () {
    $this->customer->update(['traffic_sources' => '1700000000a']);

    RecordEmailClickTouchpoint::run($this->customer->fresh(), Carbon::parse('2026-01-01 10:00:00'));

    $trafficSources = $this->customer->fresh()->trafficSources()->get();

    expect($trafficSources)->toHaveCount(2);
    expect($trafficSources->pluck('type')->all())->toEqualCanonicalizing([
        TrafficSourcesTypeEnum::ORGANIC_GOOGLE->value,
        TrafficSourcesTypeEnum::NEWSLETTER->value,
    ]);
});
