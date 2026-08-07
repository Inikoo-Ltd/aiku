<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Accounting\Invoice\StoreInvoice;
use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\CRM\Customer\UI\GetCustomerJourney;
use App\Models\Accounting\Invoice;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
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

    createTrafficSource($this->shop, 'organic-google', 'Organic Google');
    createTrafficSource($this->shop, 'google-ads', 'Google Ads');
});

function journeyCustomer(Shop $shop, ?string $trafficSources): Customer
{
    return StoreCustomer::make()->action(
        $shop,
        array_merge(Customer::factory()->definition(), [
            'traffic_sources' => $trafficSources,
        ])
    );
}

function journeyInvoice(Customer $customer, string $date, float $netAmount): Invoice
{
    return StoreInvoice::make()->action(
        $customer,
        array_merge(Invoice::factory()->definition(), [
            'date'       => $date,
            'net_amount' => $netAmount,
            'in_process' => false,
        ])
    );
}

it('interleaves touches and invoices in chronological order', function () {
    $touchA = now()->subDays(20)->timestamp;
    $touchB = now()->subDays(5)->timestamp;

    $customer = journeyCustomer($this->shop, $touchA.'a|'.$touchB.'b');
    journeyInvoice($customer, now()->subDays(10)->toDateTimeString(), 100);

    $journey = GetCustomerJourney::run($customer->refresh());

    expect(array_column($journey['events'], 'type'))->toBe(['touch', 'invoice', 'touch']);
    expect($journey['events'][1]['net_amount'])->toBe(100.0);
    expect($journey['events'][0]['label'])->toBe('Organic Google');
});

it('returns an empty journey for a customer with no touches and no invoices', function () {
    $customer = journeyCustomer($this->shop, null);

    $journey = GetCustomerJourney::run($customer->refresh());

    expect($journey['events'])->toBe([])
        ->and($journey['attribution'])->toBe([])
        ->and($journey['attribution_window_days'])->toBe(90);
});

it('flags a touch that falls outside the attribution window of the following purchase', function () {
    $staleTouch  = now()->subDays(200)->timestamp;
    $recentTouch = now()->subDays(3)->timestamp;

    $customer = journeyCustomer($this->shop, $staleTouch.'a|'.$recentTouch.'b');
    journeyInvoice($customer, now()->toDateTimeString(), 50);

    $journey = GetCustomerJourney::run($customer->refresh());

    $touches = array_values(array_filter($journey['events'], fn ($event) => $event['type'] === 'touch'));

    expect($touches[0]['in_window'])->toBeFalse()
        ->and($touches[1]['in_window'])->toBeTrue();
});

it('caps the timeline at the most recent events and reports how many were omitted', function () {
    $cap    = GetCustomerJourney::MAX_EVENTS;
    $extra  = 5;
    $oldest = now()->subDays($cap + $extra + 1);

    $touches = collect(range(0, $cap + $extra - 1))
        ->map(fn (int $offset) => $oldest->copy()->addDays($offset)->timestamp.'a')
        ->implode('|');

    $customer = journeyCustomer($this->shop, $touches);

    $journey = GetCustomerJourney::run($customer->refresh());

    expect($journey['events'])->toHaveCount($cap)
        ->and($journey['omitted_events'])->toBe($extra)
        ->and($journey['events'][0]['datetime'])->toBe($oldest->copy()->addDays($extra)->toIso8601String())
        ->and($journey['events'][$cap - 1]['datetime'])->toBe($oldest->copy()->addDays($cap + $extra - 1)->toIso8601String());
});

it('reports no omitted events for a short journey', function () {
    $customer = journeyCustomer($this->shop, now()->subDays(2)->timestamp.'a');

    expect(GetCustomerJourney::run($customer->refresh())['omitted_events'])->toBe(0);
});

it('honours the per shop attribution window override', function () {
    $touch = now()->subDays(30)->timestamp;

    $this->shop->settings = array_merge($this->shop->settings ?? [], [
        'marketing' => ['attribution_window_days' => 7],
    ]);
    $this->shop->save();

    $customer = journeyCustomer($this->shop, $touch.'a');

    $journey = GetCustomerJourney::run($customer->refresh());

    expect($journey['attribution_window_days'])->toBe(7)
        ->and($journey['events'][0]['in_window'])->toBeFalse();
});
