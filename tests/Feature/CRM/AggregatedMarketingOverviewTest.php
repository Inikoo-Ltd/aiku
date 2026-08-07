<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\CRM\TrafficSource\GetAggregatedMarketingOverview;
use App\Actions\CRM\TrafficSource\RecalculateTrafficSourceAttribution;
use App\Enums\UI\Marketing\MarketingPeriodEnum;
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

    $this->customer->update([
        'created_at'      => now()->subDays(3),
        'traffic_sources' => now()->subDays(4)->timestamp.'b',
    ]);
    RecalculateTrafficSourceAttribution::run($this->customer->fresh());

    DB::table('orders')->where('customer_id', $this->customer->id)->delete();
    DB::table('invoices')->where('customer_id', $this->customer->id)->delete();
});

it('reports the organisation total in the organisation currency, not the shop currency', function () {
    DB::table('invoices')->insert([
        'group_id'        => $this->shop->group_id,
        'organisation_id' => $this->shop->organisation_id,
        'shop_id'         => $this->shop->id,
        'customer_id'     => $this->customer->id,
        'currency_id'     => $this->shop->currency_id,
        'tax_category_id' => App\Models\Helpers\TaxCategory::firstOrFail()->id,
        'reference'       => 'INV-'.uniqid(),
        'slug'            => 'inv-'.uniqid(),
        'type'            => 'invoice',
        'net_amount'      => 100,
        'org_net_amount'  => 250,
        'grp_net_amount'  => 400,
        'total_amount'    => 100,
        'in_process'      => false,
        'payment_data'    => '{}',
        'data'            => '{}',
        'date'            => now()->subDay()->toDateTimeString(),
        'created_at'      => now()->subDay()->toDateTimeString(),
        'updated_at'      => now()->subDay()->toDateTimeString(),
    ]);

    $overview = GetAggregatedMarketingOverview::run($this->organisation, MarketingPeriodEnum::LAST_7);

    expect($overview['totals']['revenue'])->toBe(250.0)
        ->and($overview['currency_code'])->toBe($this->organisation->currency->code);

    $channel = collect($overview['channels'])->firstWhere('type', 'google-ads');

    expect($channel['revenue'])->toBe(250.0)
        ->and($channel['registrations'])->toBe(1.0);
});

it('links each shop of the organisation to its own dashboard instead of repeating it', function () {
    $overview = GetAggregatedMarketingOverview::run($this->organisation, MarketingPeriodEnum::LAST_7);

    $child = collect($overview['children'])->firstWhere('slug', $this->shop->slug);

    expect($child)->not->toBeNull()
        ->and($child['route']['name'])->toBe('grp.org.shops.show.marketing.dashboard')
        ->and($child['route']['parameters'])->toBe([$this->organisation->slug, $this->shop->slug]);
});

it('reports the group total and its spend in the group currency', function () {
    App\Actions\CRM\TrafficSource\StoreTrafficSourceCost::run($this->googleAds, [
        'date'               => now()->subDay()->toDateString(),
        'source_amount'      => 100,
        'source_currency_id' => $this->shop->currency_id,
    ]);

    $group    = $this->organisation->group;
    $overview = GetAggregatedMarketingOverview::run($group, MarketingPeriodEnum::LAST_7);

    $expected = (float) App\Models\CRM\TrafficSourceCost::where('traffic_source_id', $this->googleAds->id)
        ->sum('grp_amount');

    expect($overview['currency_code'])->toBe($group->currency->code)
        ->and($expected)->toBeGreaterThan(0.0)
        ->and($overview['totals']['spend'])->toBe(round($expected, 2));
});

it('links the group children to each organisation dashboard', function () {
    $overview = GetAggregatedMarketingOverview::run($this->organisation->group, MarketingPeriodEnum::LAST_7);

    $child = collect($overview['children'])->firstWhere('slug', $this->organisation->slug);

    expect($child)->not->toBeNull()
        ->and($child['route']['name'])->toBe('grp.org.marketing.dashboard');
});

it('does not credit a registration that preceded the touch in the aggregate either', function () {
    /* createCustomer() reuses the shop's customer, so this rewrites the one the aggregate would
       otherwise have counted: its touch now lands after it registered. */
    $this->customer->trafficSources()->detach();
    $this->customer->update(['created_at' => now()->subDays(30)]);
    $this->customer->trafficSources()->attach($this->googleAds->id, [
        'share'          => 1,
        'first_touch_at' => now()->subDay(),
        'last_touch_at'  => now()->subDay(),
    ]);

    $overview = GetAggregatedMarketingOverview::run($this->organisation, MarketingPeriodEnum::LAST_7);
    $channel  = collect($overview['channels'])->firstWhere('type', 'google-ads');

    expect($channel['registrations'] ?? 0.0)->toBe(0.0);
});

it('shows pending order value in the aggregate, in the parent currency', function () {
    createDispatchedOrderFor($this->customer, $this->shop, now()->subHours(2)->toDateTimeString(), 'submitted', 100);

    $overview = GetAggregatedMarketingOverview::run($this->organisation, MarketingPeriodEnum::LAST_7);

    expect($overview['totals']['pending'])->toBe(100.0);

    $channel = collect($overview['channels'])->firstWhere('type', 'google-ads');
    expect($channel['pending'])->toBe(100.0);
});

it('counts a submitted order in the orders figure without waiting for dispatch', function () {
    createDispatchedOrderFor($this->customer, $this->shop, now()->subHours(2)->toDateTimeString(), 'submitted');

    $overview = GetAggregatedMarketingOverview::run($this->organisation, MarketingPeriodEnum::LAST_7);
    $channel  = collect($overview['channels'])->firstWhere('type', 'google-ads');

    expect($channel['orders'])->toBe(1.0);
});

it('lets a user who can see marketing on a shop open the organisation dashboard', function () {
    $shopId = $this->shop->id;

    setPermissionsTeamId($this->organisation->group_id);
    $this->user->refresh();

    expect($this->user->authTo("marketing.$shopId.view"))->toBeTrue()
        ->and($this->user->authTo('marketing.view'))->toBeFalse();
});

it('reports what happened without marketing, so nought attributed can be told from nought happening', function () {
    $overview = GetAggregatedMarketingOverview::run($this->organisation, MarketingPeriodEnum::LAST_7);

    expect($overview['baseline']['registrations'])->toBeGreaterThan(0.0)
        ->and($overview['baseline'])->toHaveKeys(['registrations', 'orders', 'revenue']);
});

it('does not repeat invoiced revenue in the pending figure', function () {
    createDispatchedOrderFor($this->customer, $this->shop, now()->subHours(2)->toDateTimeString(), 'dispatched', 100);

    $orderId = DB::table('orders')->where('customer_id', $this->customer->id)->orderByDesc('id')->value('id');

    DB::table('invoices')->insert([
        'group_id'        => $this->shop->group_id,
        'organisation_id' => $this->shop->organisation_id,
        'shop_id'         => $this->shop->id,
        'customer_id'     => $this->customer->id,
        'order_id'        => $orderId,
        'currency_id'     => $this->shop->currency_id,
        'tax_category_id' => App\Models\Helpers\TaxCategory::firstOrFail()->id,
        'reference'       => 'INV-'.uniqid(),
        'slug'            => 'inv-'.uniqid(),
        'type'            => 'invoice',
        'net_amount'      => 100,
        'org_net_amount'  => 100,
        'grp_net_amount'  => 100,
        'total_amount'    => 100,
        'in_process'      => false,
        'payment_data'    => '{}',
        'data'            => '{}',
        'date'            => now()->subHour()->toDateTimeString(),
        'created_at'      => now()->subHour()->toDateTimeString(),
        'updated_at'      => now()->subHour()->toDateTimeString(),
    ]);

    $overview = GetAggregatedMarketingOverview::run($this->organisation, MarketingPeriodEnum::LAST_7);

    expect($overview['totals']['revenue'])->toBe(100.0)
        ->and($overview['totals']['pending'])->toBe(0.0);
});

it('pools referring sites across every shop underneath', function () {
    $referral = createTrafficSource($this->shop, 'referral', 'Referral');

    $campaignId = DB::table('traffic_source_campaigns')->insertGetId([
        'traffic_source_id' => $referral->id,
        'slug'              => 'r-'.uniqid(),
        'reference'         => 'esources.co.uk-'.uniqid(),
        'name'              => 'esources.co.uk',
        'type'              => 'referral',
        'created_at'        => now(),
        'updated_at'        => now(),
    ]);

    $this->customer->trafficSources()->detach();
    $this->customer->trafficSources()->attach($referral->id, [
        'share'                      => 1,
        'traffic_source_campaign_id' => $campaignId,
        'first_touch_at'             => now()->subDays(2),
        'last_touch_at'              => now()->subDays(2),
    ]);

    $referrers = GetAggregatedMarketingOverview::run($this->organisation, MarketingPeriodEnum::LAST_7)['referrers'];

    expect(collect($referrers)->firstWhere('host', 'esources.co.uk'))->not->toBeNull()
        ->and(collect($referrers)->firstWhere('host', 'esources.co.uk')['visitors'])->toBe(1.0);
});

it('gives each child the total it is a share of, so a zero can be read', function () {
    $child = collect(GetAggregatedMarketingOverview::run($this->organisation, MarketingPeriodEnum::LAST_7)['children'])
        ->firstWhere('slug', $this->shop->slug);

    expect($child)->toHaveKeys(['registrations_total', 'orders_total'])
        ->and($child['registrations_total'])->toBeGreaterThanOrEqual($child['registrations']);
});

it('lists search engines alongside referring sites, marked as searches', function () {
    $search = createTrafficSource($this->shop, 'organic-search', 'Organic Search (other)');

    $campaignId = DB::table('traffic_source_campaigns')->insertGetId([
        'traffic_source_id' => $search->id,
        'slug'              => 's-'.uniqid(),
        'reference'         => 'duckduckgo.com-'.uniqid(),
        'name'              => 'duckduckgo.com',
        'type'              => 'organic-search',
        'created_at'        => now(),
        'updated_at'        => now(),
    ]);

    $this->customer->trafficSources()->detach();
    $this->customer->trafficSources()->attach($search->id, [
        'share'                      => 1,
        'traffic_source_campaign_id' => $campaignId,
        'first_touch_at'             => now()->subDays(2),
        'last_touch_at'              => now()->subDays(2),
    ]);

    $entry = collect(GetAggregatedMarketingOverview::run($this->organisation, MarketingPeriodEnum::LAST_7)['referrers'])
        ->firstWhere('host', 'duckduckgo.com');

    expect($entry)->not->toBeNull()
        ->and($entry['kind'])->toBe('search');
});
