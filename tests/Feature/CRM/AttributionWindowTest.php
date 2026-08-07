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
