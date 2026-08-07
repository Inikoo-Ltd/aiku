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
        ->and($overview['currency_code'])->toBe($this->organisation->currency->code)
        ->and($overview['has_spend'])->toBeTrue();

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

it('reports the group total in the group currency and offers no spend', function () {
    $overview = GetAggregatedMarketingOverview::run($this->organisation->group, MarketingPeriodEnum::LAST_7);

    expect($overview['currency_code'])->toBe($this->organisation->group->currency->code)
        ->and($overview['has_spend'])->toBeFalse()
        ->and($overview['totals']['spend'])->toBe(0.0);
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
