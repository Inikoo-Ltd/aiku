<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\CRM\TrafficSource\GetAttributionWindow;
use App\Actions\CRM\TrafficSource\GetShopMarketingOverview;
use App\Actions\CRM\TrafficSource\RecalculateTrafficSourceAttribution;
use App\Enums\UI\Marketing\MarketingPeriodEnum;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    Artisan::call('migrate');
    config()->set('marketing.attribution_window_days', 90);

    list(
        $this->organisation,
        $this->user,
        $this->shop
    ) = createShop();

    $this->googleAds = createTrafficSource($this->shop, 'google-ads', 'Google Ads');
    $this->customer  = createCustomer($this->shop);

    $this->customer->trafficSources()->detach();
    DB::table('invoices')->where('customer_id', $this->customer->id)->delete();

    // One touch, 10 days ago.
    $this->touchedAt = now()->subDays(10);
    $this->customer->update(['traffic_sources' => $this->touchedAt->timestamp.'b']);
    RecalculateTrafficSourceAttribution::run($this->customer->fresh());
});

function windowInvoice(string $date, float $net, $customer, $shop): void
{
    DB::table('invoices')->insert([
        'group_id'        => $shop->group_id,
        'organisation_id' => $shop->organisation_id,
        'shop_id'         => $shop->id,
        'customer_id'     => $customer->id,
        'currency_id'     => $shop->currency_id,
        'tax_category_id' => App\Models\Helpers\TaxCategory::firstOrFail()->id,
        'reference'       => 'INV-'.uniqid(),
        'slug'            => 'inv-'.uniqid(),
        'type'            => 'invoice',
        'net_amount'      => $net,
        'total_amount'    => $net,
        'in_process'      => false,
        'payment_data'    => '{}',
        'data'            => '{}',
        'date'            => $date,
        'created_at'      => $date,
        'updated_at'      => $date,
    ]);
}

it('records when each touch happened', function () {
    $pivot = $this->customer->trafficSources()->first()->pivot;

    expect($pivot->first_touch_at)->not->toBeNull();
    expect(substr((string) $pivot->first_touch_at, 0, 10))->toBe($this->touchedAt->toDateString());
});

it('ignores revenue invoiced before the touch', function () {
    windowInvoice(now()->subDays(400)->toDateTimeString(), 5000, $this->customer, $this->shop);
    windowInvoice(now()->subDays(2)->toDateTimeString(), 300, $this->customer, $this->shop);

    $overview = GetShopMarketingOverview::run($this->shop, MarketingPeriodEnum::ALL_TIME);

    // Only the invoice raised after the click counts; the 5,000 predates it.
    expect($overview['totals']['revenue'])->toBe(300.0);
});

it('ignores revenue invoiced after the window closes', function () {
    // Touch was 10 days ago, so a 5-day window has already expired.
    config()->set('marketing.attribution_window_days', 5);

    windowInvoice(now()->subDays(2)->toDateTimeString(), 300, $this->customer, $this->shop);

    expect(GetShopMarketingOverview::run($this->shop, MarketingPeriodEnum::ALL_TIME)['totals']['revenue'])
        ->toBe(0.0);
});

it('counts revenue inside the window', function () {
    config()->set('marketing.attribution_window_days', 90);

    windowInvoice(now()->subDays(2)->toDateTimeString(), 300, $this->customer, $this->shop);

    expect(GetShopMarketingOverview::run($this->shop, MarketingPeriodEnum::ALL_TIME)['totals']['revenue'])
        ->toBe(300.0);
});

it('lets a shop override the group window', function () {
    expect(GetAttributionWindow::run($this->shop))->toBe(90);

    $this->shop->update(['settings' => array_merge($this->shop->settings ?? [], [
        'marketing' => ['attribution_window_days' => 30],
    ])]);

    expect(GetAttributionWindow::run($this->shop->fresh()))->toBe(30);
});

it('disables the window when it is set to zero', function () {
    config()->set('marketing.attribution_window_days', 0);

    windowInvoice(now()->subDays(400)->toDateTimeString(), 5000, $this->customer, $this->shop);
    windowInvoice(now()->subDays(2)->toDateTimeString(), 300, $this->customer, $this->shop);

    // Causality still applies: the pre-touch invoice never counts, window or no window.
    expect(GetShopMarketingOverview::run($this->shop, MarketingPeriodEnum::ALL_TIME)['totals']['revenue'])
        ->toBe(300.0);
});

it('credits revenue by the date the customer ordered, not the date the invoice was raised', function () {
    $customer = $this->customer;
    $customer->trafficSources()->detach();
    DB::table('invoices')->where('customer_id', $customer->id)->delete();

    /* Ordered yesterday, touched this morning, invoiced after that: the touch cannot have caused an
       order that was already placed. */
    $customer->trafficSources()->attach($this->googleAds->id, [
        'share'          => 1,
        'first_touch_at' => now()->subHours(6),
        'last_touch_at'  => now()->subHours(6),
    ]);

    $orderId = DB::table('orders')->insertGetId([
        'group_id'        => $this->shop->group_id,
        'organisation_id' => $this->shop->organisation_id,
        'shop_id'         => $this->shop->id,
        'customer_id'     => $customer->id,
        'currency_id'     => $this->shop->currency_id,
        'tax_category_id' => App\Models\Helpers\TaxCategory::firstOrFail()->id,
        'slug'            => 'ord-'.uniqid(),
        'state'           => 'dispatched',
        'status'          => 'settled',
        'payment_data'    => '{}',
        'data'            => '{}',
        'date'            => now()->subDay()->toDateTimeString(),
        'created_at'      => now()->subDay()->toDateTimeString(),
        'updated_at'      => now()->subDay()->toDateTimeString(),
    ]);

    DB::table('invoices')->insert([
        'group_id'        => $this->shop->group_id,
        'organisation_id' => $this->shop->organisation_id,
        'shop_id'         => $this->shop->id,
        'customer_id'     => $customer->id,
        'order_id'        => $orderId,
        'currency_id'     => $this->shop->currency_id,
        'tax_category_id' => App\Models\Helpers\TaxCategory::firstOrFail()->id,
        'reference'       => 'INV-'.uniqid(),
        'slug'            => 'inv-'.uniqid(),
        'type'            => 'invoice',
        'net_amount'      => 500,
        'org_net_amount'  => 500,
        'grp_net_amount'  => 500,
        'total_amount'    => 500,
        'in_process'      => false,
        'payment_data'    => '{}',
        'data'            => '{}',
        'date'            => now()->toDateTimeString(),
        'created_at'      => now()->toDateTimeString(),
        'updated_at'      => now()->toDateTimeString(),
    ]);

    $overview = GetShopMarketingOverview::run($this->shop, MarketingPeriodEnum::LAST_7);
    $channel  = collect($overview['channels'])->firstWhere('type', $this->googleAds->type);

    expect($channel['revenue'] ?? 0.0)->toBe(0.0);
});

it('lets the recording start date be set, so a fix to capture is not judged by what came before it', function () {
    config()->set('marketing.attribution_started_at', '2026-08-07 19:30:00');

    expect(App\Actions\CRM\TrafficSource\GetAttributionStartedAt::run()->toDateTimeString())
        ->toBe('2026-08-07 19:30:00');

    config()->set('marketing.attribution_started_at', null);
});
