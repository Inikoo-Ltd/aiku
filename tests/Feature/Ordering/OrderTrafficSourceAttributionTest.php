<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 05 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\CRM\TrafficSource\RecalculateTrafficSourceAttribution;
use App\Actions\Ordering\Order\ProcessOrderTrafficSource;
use App\Actions\Ordering\Order\UpdateState\CancelOrder;
use App\Actions\Ordering\Order\UpdateState\SubmitOrder;
use App\Enums\Ordering\Order\OrderPayStatusEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Order\OrderStatusEnum;
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

    list(
        $this->tradeUnit,
        $this->product
    ) = createProduct($this->shop);

    $this->customer = createCustomer($this->shop);
    $this->order     = createOrder($this->customer, $this->product);

    // The shared fixtures above are reused across tests, reset any state left by a previous test.
    $this->customer->trafficSources()->detach();
    $this->order->trafficSources()->detach();
    $this->customer->update(['traffic_sources' => null]);
    $this->order->update([
        'traffic_sources' => null,
        'state'           => OrderStateEnum::CREATING,
        'status'          => OrderStatusEnum::CREATING,
        'pay_status'      => OrderPayStatusEnum::UNPAID,
        'submitted_at'    => null,
        'cancelled_at'    => null,
    ]);
});

it('exposes a traffic sources relationship on the order model', function () {
    expect($this->order->trafficSources()->count())->toBe(0);
});

it('attributes an order from the customer touch history when the order has none of its own', function () {
    createTrafficSource($this->shop, 'google-ads', 'Google Ads');

    $this->customer->update(['traffic_sources' => '1700000000b']);

    ProcessOrderTrafficSource::run($this->order->fresh());

    expect($this->order->trafficSources()->count())->toBe(1);
    expect((float) $this->order->trafficSources()->first()->pivot->share)->toBe(1.0);
});

it('prefers the order own touch history over the customer one when present', function () {
    createTrafficSource($this->shop, 'organic-google', 'Organic Google');

    createTrafficSource($this->shop, 'google-ads', 'Google Ads');

    $this->customer->update(['traffic_sources' => '1700000000a']);
    $this->order->update(['traffic_sources' => '1700000100b']);

    ProcessOrderTrafficSource::run($this->order->fresh());

    $trafficSources = $this->order->trafficSources()->get();
    expect($trafficSources)->toHaveCount(1);
    expect($trafficSources->first()->type)->toBe('google-ads');
});

it('does nothing when there is no touch history at all', function () {
    ProcessOrderTrafficSource::run($this->order->fresh());

    expect($this->order->trafficSources()->count())->toBe(0);
});

it('attributes the order automatically when it is submitted', function () {
    createTrafficSource($this->shop, 'meta-ads', 'Meta Ads');

    $this->customer->update(['traffic_sources' => '1700000000f']);

    $order = SubmitOrder::make()->action($this->order);

    expect($order->state)->toBe(OrderStateEnum::SUBMITTED);
    expect($order->trafficSources()->count())->toBe(1);
});

it('leaves the submit time attribution intact when an order without its own touch history is recalculated', function () {
    createTrafficSource($this->shop, 'meta-ads', 'Meta Ads');

    $this->customer->update(['traffic_sources' => '1700000000f']);

    ProcessOrderTrafficSource::run($this->order->fresh());
    expect($this->order->trafficSources()->count())->toBe(1);

    RecalculateTrafficSourceAttribution::run($this->order->fresh());

    expect($this->order->trafficSources()->count())->toBe(1);
});

it('refreshes the traffic source stats but keeps the attribution audit trail when an order is cancelled', function () {
    $trafficSource = createTrafficSource($this->shop, 'meta-ads', 'Meta Ads');

    $this->customer->update(['traffic_sources' => '1700000000f']);

    $order = SubmitOrder::make()->action($this->order);
    expect($order->trafficSources()->count())->toBe(1);

    $order = CancelOrder::make()->action($order->fresh());

    expect($order->state)->toBe(OrderStateEnum::CANCELLED);
    expect($order->trafficSources()->count())->toBe(1);
    expect($order->trafficSources()->first()->id)->toBe($trafficSource->id);
    expect((float) $order->trafficSources()->first()->pivot->share)->toBe(1.0);
});
