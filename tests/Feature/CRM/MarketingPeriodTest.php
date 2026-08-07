<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\CRM\TrafficSource\GetShopMarketingOverview;
use App\Actions\CRM\TrafficSource\Hydrator\TrafficSourceHydrateCustomers;
use App\Actions\CRM\TrafficSource\RecalculateTrafficSourceAttribution;
use App\Actions\CRM\TrafficSource\StoreTrafficSourceCost;
use App\Enums\UI\Marketing\MarketingPeriodEnum;
use App\Models\CRM\TrafficSourceCost;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

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

    $this->googleAds = createTrafficSource($this->shop, 'google-ads', 'Google Ads');
    $this->customer  = createCustomer($this->shop);

    $this->customer->trafficSources()->detach();
    // Touch 70 days ago: old enough to precede the fixtures' invoices, recent enough that they
    // fall inside the 90-day attribution window.
    $this->touchedAt = now()->subDays(70);
    $this->customer->update(['traffic_sources' => $this->touchedAt->timestamp.'b']);
    RecalculateTrafficSourceAttribution::run($this->customer->fresh());

    TrafficSourceCost::where('shop_id', $this->shop->id)->delete();
    DB::table('invoices')->where('customer_id', $this->customer->id)->delete();
});

function invoiceOn(string $date, float $net, $customer, $shop, bool $inProcess = false): void
{
    DB::table('invoices')->insert([
        'group_id'        => $shop->group_id,
        'organisation_id' => $shop->organisation_id,
        'shop_id'         => $shop->id,
        'customer_id'     => $customer->id,
        'currency_id'     => $shop->currency_id,
        'tax_category_id' => $shop->taxCategory?->id ?? App\Models\Helpers\TaxCategory::firstOrFail()->id,
        'reference'       => 'INV-'.uniqid(),
        'slug'            => 'inv-'.uniqid(),
        'type'            => 'invoice',
        'net_amount'      => $net,
        'total_amount'    => $net,
        'in_process'      => $inProcess,
        'payment_data'    => '{}',
        'data'            => '{}',
        'date'            => $date,
        'created_at'      => $date,
        'updated_at'      => $date,
    ]);
}

it('counts only revenue and spend inside the selected period', function () {
    invoiceOn(now()->subDays(3)->toDateTimeString(), 400, $this->customer, $this->shop);
    invoiceOn(now()->subDays(60)->toDateTimeString(), 1000, $this->customer, $this->shop);

    StoreTrafficSourceCost::run($this->googleAds, [
        'date'               => now()->subDays(3)->toDateString(),
        'source_amount'      => 100,
        'source_currency_id' => $this->shop->currency_id,
    ]);
    StoreTrafficSourceCost::run($this->googleAds, [
        'date'               => now()->subDays(60)->toDateString(),
        'source_amount'      => 500,
        'source_currency_id' => $this->shop->currency_id,
    ]);

    $recent = GetShopMarketingOverview::run($this->shop, MarketingPeriodEnum::LAST_7);

    expect($recent['totals']['revenue'])->toBe(400.0);
    expect($recent['totals']['spend'])->toBe(100.0);
    expect($recent['totals']['roas'])->toBe(4.0);

    $allTime = GetShopMarketingOverview::run($this->shop, MarketingPeriodEnum::ALL_TIME);

    // The -60d invoice postdates the touch and is inside the window, so all-time sees both.
    expect($allTime['totals']['revenue'])->toBe(1400.0);
    expect($allTime['totals']['spend'])->toBe(600.0);
});

it('reproduces the lifetime stats figure when the period is all time', function () {
    // The 200-day-old invoice predates the touch, so neither the dashboard nor the rollup counts it.
    invoiceOn(now()->subDays(200)->toDateTimeString(), 250, $this->customer, $this->shop);
    invoiceOn(now()->subDay()->toDateTimeString(), 750, $this->customer, $this->shop);

    // sales_all is maintained by its own hydrator; bring it in sync before comparing the two paths.
    App\Actions\CRM\Customer\Hydrators\CustomerHydrateInvoices::run($this->customer->id);
    TrafficSourceHydrateCustomers::run($this->googleAds);

    $allTime = GetShopMarketingOverview::run($this->shop, MarketingPeriodEnum::ALL_TIME);

    expect($allTime['totals']['revenue'])
        ->toBe(round((float) $this->googleAds->stats()->first()->total_customer_revenue, 2));
});

it('splits period revenue between channels by their shares', function () {
    $organic = createTrafficSource($this->shop, 'organic-google', 'Organic Google');

    $this->customer->update(['traffic_sources' => $this->touchedAt->timestamp.'b|'.$this->touchedAt->addSecond()->timestamp.'a']);
    RecalculateTrafficSourceAttribution::run($this->customer->fresh());

    invoiceOn(now()->subDay()->toDateTimeString(), 1000, $this->customer, $this->shop);

    $overview = GetShopMarketingOverview::run($this->shop, MarketingPeriodEnum::LAST_7);
    $channels = collect($overview['channels'])->keyBy('type');

    expect($channels['google-ads']['revenue'])->toBe(500.0);
    expect($channels['organic-google']['revenue'])->toBe(500.0);
    expect($overview['totals']['revenue'])->toBe(1000.0);
});

it('excludes in-process refund invoices', function () {
    invoiceOn(now()->subDay()->toDateTimeString(), 500, $this->customer, $this->shop);
    invoiceOn(now()->subDay()->toDateTimeString(), 9999, $this->customer, $this->shop, inProcess: true);

    expect(GetShopMarketingOverview::run($this->shop, MarketingPeriodEnum::LAST_7)['totals']['revenue'])
        ->toBe(500.0);
});

it('reports the selected period back to the dashboard', function () {
    $overview = GetShopMarketingOverview::run($this->shop, MarketingPeriodEnum::MONTH_TO_DATE);

    expect($overview['period'])->toBe('month_to_date');
    expect($overview['from'])->toBe(now()->startOfMonth()->toDateString());

    expect(GetShopMarketingOverview::run($this->shop, MarketingPeriodEnum::ALL_TIME)['from'])->toBeNull();
});

it('serves the same period numbers through the daily sql view', function () {
    invoiceOn(now()->subDays(3)->toDateTimeString(), 400, $this->customer, $this->shop);
    StoreTrafficSourceCost::run($this->googleAds, [
        'date'               => now()->subDays(3)->toDateString(),
        'source_amount'      => 100,
        'source_currency_id' => $this->shop->currency_id,
    ]);

    $row = collect(DB::select(
        'SELECT SUM(revenue) AS revenue, SUM(cost) AS cost
         FROM marketing_channel_daily
         WHERE shop_id = ? AND date >= ?',
        [$this->shop->id, now()->subDays(7)->toDateString()]
    ))->first();

    expect(round((float) $row->revenue, 2))->toBe(400.0);
    expect(round((float) $row->cost, 2))->toBe(100.0);
});
