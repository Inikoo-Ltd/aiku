<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\CRM\TrafficSource\GetShopMarketingOverview;
use App\Actions\CRM\TrafficSource\Hydrator\TrafficSourceCampaignHydrateStats;
use App\Actions\CRM\TrafficSource\Hydrator\TrafficSourceHydrateCustomers;
use App\Actions\CRM\TrafficSource\StoreTrafficSourceCost;
use App\Enums\UI\Marketing\MarketingPeriodEnum;
use App\Models\CRM\Customer;
use App\Models\CRM\TrafficSourceCampaign;
use App\Models\CRM\TrafficSourceCost;
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

    $this->googleAds = createTrafficSource($this->shop, 'google-ads', 'Google Ads');

    TrafficSourceCost::where('shop_id', $this->shop->id)->delete();
});

function campaignFor($trafficSource, string $reference): TrafficSourceCampaign
{
    return TrafficSourceCampaign::firstOrCreate(
        ['traffic_source_id' => $trafficSource->id, 'reference' => $reference],
        ['name' => 'Campaign '.$reference, 'type' => $trafficSource->type]
    );
}

it('rolls a campaign\'s attributed customers and revenue into its own stats', function () {
    $reference = (string) random_int(10000000000, 99999999999);

    $customer = StoreCustomer::make()->action(
        $this->shop,
        array_merge(Customer::factory()->definition(), [
            'traffic_sources' => now()->subDays(10)->timestamp.'b'.$reference,
        ])
    );

    createInvoiceFor($customer, $this->shop, now()->subDay()->toDateTimeString(), 900);

    foreach (range(1, 3) as $ignored) {
        createDispatchedOrderFor($customer, $this->shop, now()->subDay()->toDateTimeString());
    }

    $campaign = TrafficSourceCampaign::where('reference', $reference)->firstOrFail();
    TrafficSourceCampaignHydrateStats::run($campaign);

    $stats = $campaign->stats()->first();

    expect((float) $stats->number_customers)->toBe(1.0);
    expect((float) $stats->total_customer_revenue)->toBe(900.0);
    expect((float) $stats->number_customer_purchases)->toBe(3.0);
});

it('rolls campaign level spend into the campaign stats', function () {
    $campaign = campaignFor($this->googleAds, (string) random_int(10000000000, 99999999999));

    StoreTrafficSourceCost::run($this->googleAds, [
        'date'                       => now()->subDay()->toDateString(),
        'source_amount'              => 120.00,
        'source_currency_id'         => $this->shop->currency_id,
        'traffic_source_campaign_id' => $campaign->id,
    ]);

    TrafficSourceCampaignHydrateStats::run($campaign);

    expect((float) $campaign->stats()->first()->total_cost)->toBe(120.0);
});

it('keeps a campaign with spend but no customers visible', function () {
    $campaign = campaignFor($this->googleAds, (string) random_int(10000000000, 99999999999));

    StoreTrafficSourceCost::run($this->googleAds, [
        'date'                       => now()->subDay()->toDateString(),
        'source_amount'              => 75.00,
        'source_currency_id'         => $this->shop->currency_id,
        'traffic_source_campaign_id' => $campaign->id,
    ]);

    TrafficSourceCampaignHydrateStats::run($campaign);
    $stats = $campaign->stats()->first();

    expect((float) $stats->total_cost)->toBe(75.0);
    expect((float) $stats->number_customers)->toBe(0.0);
    expect((float) $stats->total_customer_revenue)->toBe(0.0);
});

it('never lets a source\'s campaigns claim more than the source itself', function () {
    $first  = (string) random_int(10000000000, 99999999999);
    $second = (string) random_int(10000000000, 99999999999);

    $customer = StoreCustomer::make()->action(
        $this->shop,
        array_merge(Customer::factory()->definition(), [
            'traffic_sources' => now()->subDays(10)->timestamp.'b'.$first.'|'.now()->subDays(9)->timestamp.'b'.$second,
        ])
    );

    createInvoiceFor($customer, $this->shop, now()->subDay()->toDateTimeString(), 1000);

    TrafficSourceHydrateCustomers::run($this->googleAds);

    $campaignRevenue = 0.0;
    foreach ([$first, $second] as $reference) {
        $campaign = TrafficSourceCampaign::where('reference', $reference)->firstOrFail();
        TrafficSourceCampaignHydrateStats::run($campaign);
        $campaignRevenue += (float) $campaign->stats()->first()->total_customer_revenue;
    }

    // The two campaigns split this customer's revenue between them, never duplicating it...
    expect($campaignRevenue)->toBe(1000.0);

    // ...and campaigns can never claim more than the source they belong to. (Other tests in this file
    // share the shop, so the source legitimately carries their customers too - hence not equality.)
    expect($campaignRevenue)
        ->toBeLessThanOrEqual(round((float) $this->googleAds->stats()->first()->total_customer_revenue, 2));
});

it('hydrates every campaign through the artisan command', function () {
    $campaign = campaignFor($this->googleAds, (string) random_int(10000000000, 99999999999));

    StoreTrafficSourceCost::run($this->googleAds, [
        'date'                       => now()->subDay()->toDateString(),
        'source_amount'              => 33.00,
        'source_currency_id'         => $this->shop->currency_id,
        'traffic_source_campaign_id' => $campaign->id,
    ]);

    Artisan::call('traffic-source:hydrate-campaign-stats', ['--shop' => $this->shop->slug]);

    expect((float) $campaign->stats()->first()->total_cost)->toBe(33.0);
});

it('reports campaign performance for the selected period on the dashboard', function () {
    $reference = (string) random_int(10000000000, 99999999999);

    $customer = StoreCustomer::make()->action(
        $this->shop,
        array_merge(Customer::factory()->definition(), [
            'traffic_sources' => now()->subDays(10)->timestamp.'b'.$reference,
        ])
    );

    $campaign = TrafficSourceCampaign::where('reference', $reference)->firstOrFail();

    createInvoiceFor($customer, $this->shop, now()->subDay()->toDateTimeString(), 400);

    StoreTrafficSourceCost::run($this->googleAds, [
        'date'                       => now()->subDay()->toDateString(),
        'source_amount'              => 100.00,
        'source_currency_id'         => $this->shop->currency_id,
        'traffic_source_campaign_id' => $campaign->id,
    ]);

    $campaigns = collect(GetShopMarketingOverview::run($this->shop, MarketingPeriodEnum::LAST_7)['campaigns'])
        ->keyBy('name');

    expect($campaigns[$campaign->name]['spend'])->toBe(100.0);
    expect($campaigns[$campaign->name]['revenue'])->toBe(400.0);
    expect($campaigns[$campaign->name]['roas'])->toBe(4.0);
});

it('counts a campaign\'s registrations as customers rather than as invoices', function () {
    $reference = (string) random_int(10000000000, 99999999999);

    $customer = StoreCustomer::make()->action(
        $this->shop,
        array_merge(Customer::factory()->definition(), [
            'traffic_sources' => now()->subDays(10)->timestamp.'b'.$reference,
        ])
    );

    $campaign = TrafficSourceCampaign::where('reference', $reference)->firstOrFail();

    createInvoiceFor($customer, $this->shop, now()->subDay()->toDateTimeString(), 400);
    createInvoiceFor($customer, $this->shop, now()->subDay()->toDateTimeString(), 200);

    $campaigns = collect(GetShopMarketingOverview::run($this->shop, MarketingPeriodEnum::LAST_7)['campaigns'])
        ->keyBy('name');

    expect($campaigns[$campaign->name]['registrations'])->toBe(1.0)
        ->and($campaigns[$campaign->name]['revenue'])->toBe(600.0);
});

it('does not credit a campaign with a registration that preceded its touch', function () {
    $reference = (string) random_int(10000000000, 99999999999);

    $customer = StoreCustomer::make()->action(
        $this->shop,
        array_merge(Customer::factory()->definition(), [
            'traffic_sources' => now()->subDay()->timestamp.'b'.$reference,
        ])
    );

    $customer->update(['created_at' => now()->subDays(5)]);
    $campaign = TrafficSourceCampaign::where('reference', $reference)->firstOrFail();

    createInvoiceFor($customer, $this->shop, now()->toDateTimeString(), 400);

    $campaigns = collect(GetShopMarketingOverview::run($this->shop, MarketingPeriodEnum::LAST_7)['campaigns'])
        ->keyBy('name');

    expect($campaigns[$campaign->name]['registrations'])->toBe(0.0);
});
