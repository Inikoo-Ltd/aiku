<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\CRM\TrafficSource\GetShopMarketingOverview;
use App\Actions\CRM\TrafficSource\GetTrafficSourceFromRefererHeader;
use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Enums\UI\Marketing\MarketingPeriodEnum;
use App\Models\CRM\Customer;
use App\Models\CRM\TrafficSourceCampaign;
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

    $this->referral = createTrafficSource($this->shop, 'referral', 'Referral');
});

it('records an unrecognised external referrer as a referral touch carrying its host', function () {
    $touch = GetTrafficSourceFromRefererHeader::run('https://www.esources.co.uk/directory/x');

    expect($touch)->toBe(TrafficSourcesTypeEnum::abbr()[TrafficSourcesTypeEnum::REFERRAL->value].'esources.co.uk');
});

it('still prefers a known channel over the referral fallback', function () {
    expect(GetTrafficSourceFromRefererHeader::run('https://www.google.com/search?q=x'))
        ->toBe(TrafficSourcesTypeEnum::abbr()[TrafficSourcesTypeEnum::ORGANIC_GOOGLE->value]);
});

it('does not treat our own systems as a referral', function () {
    expect(GetTrafficSourceFromRefererHeader::run('https://app.aiku.io/org/aw'))->toBeNull()
        ->and(GetTrafficSourceFromRefererHeader::normaliseHost('not a host'))->toBeNull();
});

it('creates a campaign per referring host and shows it in the dashboard top referrers', function () {
    $customer = StoreCustomer::make()->action(
        $this->shop,
        array_merge(Customer::factory()->definition(), [
            'traffic_sources' => now()->subDay()->timestamp.'qesources.co.uk',
        ])
    );

    createInvoiceFor($customer, $this->shop, now()->toDateTimeString(), 250);

    $campaign = TrafficSourceCampaign::where('traffic_source_id', $this->referral->id)
        ->where('reference', 'esources.co.uk')
        ->first();

    expect($campaign)->not->toBeNull();

    $referrers = collect(GetShopMarketingOverview::run($this->shop, MarketingPeriodEnum::LAST_7)['referrers'])
        ->keyBy('host');

    expect($referrers['esources.co.uk']['registrations'])->toBe(1.0)
        ->and($referrers['esources.co.uk']['revenue'])->toBe(250.0);
});

it('refuses a referral campaign whose reference is not a hostname', function () {
    StoreCustomer::make()->action(
        $this->shop,
        array_merge(Customer::factory()->definition(), [
            'traffic_sources' => now()->subDay()->timestamp.'qnot-a-host',
        ])
    );

    /* Asserted by reference, not by count: createShop() reuses the shop across the file, so the
       referral source still carries the campaign the earlier test created. */
    expect(TrafficSourceCampaign::where('traffic_source_id', $this->referral->id)
        ->where('reference', 'not-a-host')->exists())->toBeFalse();
});
