<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 05 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Comms\Mailshot\StoreMailshot;
use App\Actions\CRM\Prospect\StoreProspect;
use App\Actions\CRM\TrafficSource\ProcessTrafficSourceShare;
use App\Actions\CRM\TrafficSource\RecalculateTrafficSourceAttribution;
use App\Actions\CRM\TrafficSource\RecordEmailClickTouchpoint;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Models\Comms\Mailshot;
use App\Models\CRM\Prospect;
use App\Models\CRM\TrafficSource;
use App\Models\CRM\TrafficSourceCampaign;
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

it('links the touch to a traffic source campaign matching the mailshot', function () {
    $outbox   = $this->shop->outboxes()->where('type', OutboxCodeEnum::MARKETING)->first();
    $mailshot = StoreMailshot::make()->action($outbox, Mailshot::factory()->definition());

    RecordEmailClickTouchpoint::run($this->customer, Carbon::parse('2026-01-01 10:00:00'), $mailshot);

    $trafficSource = TrafficSource::where('shop_id', $this->shop->id)
        ->where('type', TrafficSourcesTypeEnum::NEWSLETTER->value)
        ->first();

    $campaign = TrafficSourceCampaign::where('traffic_source_id', $trafficSource->id)
        ->where('reference', RecordEmailClickTouchpoint::CAMPAIGN_REF_PREFIX.$mailshot->id)
        ->first();

    expect($campaign)->not->toBeNull();

    $pivot = $this->customer->fresh()->trafficSources()->wherePivot('traffic_source_campaign_id', $campaign->id)->first();

    expect($pivot)->not->toBeNull();
});

it('does not record a duplicate touch for a repeat click on the same mailshot on the same day', function () {
    $outbox   = $this->shop->outboxes()->where('type', OutboxCodeEnum::MARKETING)->first();
    $mailshot = StoreMailshot::make()->action($outbox, Mailshot::factory()->definition());

    RecordEmailClickTouchpoint::run($this->customer, Carbon::parse('2026-01-01 10:00:00'), $mailshot);
    RecordEmailClickTouchpoint::run($this->customer->fresh(), Carbon::parse('2026-01-01 18:00:00'), $mailshot);

    $touches = explode('|', $this->customer->fresh()->traffic_sources);

    expect($touches)->toHaveCount(1);
});

it('caps the touch history, keeping the first touch and dropping the oldest of the rest', function () {
    $history = collect(range(1, RecordEmailClickTouchpoint::MAX_TOUCHES))
        ->map(fn (int $i) => (1700000000 + $i).'a')
        ->implode('|');

    $this->customer->update(['traffic_sources' => $history]);

    RecordEmailClickTouchpoint::run($this->customer->fresh(), Carbon::parse('2026-01-01 10:00:00'));

    $touches = explode('|', $this->customer->fresh()->traffic_sources);

    expect($touches)->toHaveCount(RecordEmailClickTouchpoint::MAX_TOUCHES);
    expect($touches[0])->toBe('1700000001a');
    expect($touches[1])->toBe('1700000003a');
});

it('keeps the attribution model already stamped on the record when recording a click', function () {
    $this->customer->update(['traffic_sources' => '1700000000a']);

    RecalculateTrafficSourceAttribution::run($this->customer->fresh(), ProcessTrafficSourceShare::ATTRIBUTION_FIRST_TOUCH);

    RecordEmailClickTouchpoint::run($this->customer->fresh(), Carbon::parse('2026-01-01 10:00:00'));

    $trafficSources = $this->customer->fresh()->trafficSources()->get();

    expect($trafficSources->pluck('pivot.attribution_model')->unique()->all())
        ->toBe([ProcessTrafficSourceShare::ATTRIBUTION_FIRST_TOUCH]);
});

it('also records an email click as a newsletter touch for a prospect', function () {
    $prospect = StoreProspect::make()->action($this->shop, Prospect::factory()->definition());

    RecordEmailClickTouchpoint::run($prospect, Carbon::parse('2026-01-01 10:00:00'));

    $trafficSources = $prospect->fresh()->trafficSources()->get();

    expect($trafficSources)->toHaveCount(1);
    expect($trafficSources->first()->type)->toBe(TrafficSourcesTypeEnum::NEWSLETTER->value);
});

it('does not record a duplicate touch for a prospect clicking again on the same day', function () {
    $prospect = StoreProspect::make()->action($this->shop, Prospect::factory()->definition());

    RecordEmailClickTouchpoint::run($prospect, Carbon::parse('2026-01-01 10:00:00'));
    RecordEmailClickTouchpoint::run($prospect->fresh(), Carbon::parse('2026-01-01 18:00:00'));

    $touches = explode('|', $prospect->fresh()->traffic_sources);

    expect($touches)->toHaveCount(1);
});
