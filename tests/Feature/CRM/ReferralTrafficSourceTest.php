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
use App\Actions\CRM\TrafficSource\RecalculateTrafficSourceAttribution;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

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

/* Primed rather than seeded from the websites table: the test database carries no live domains, and
   what is under test is the exclusion, not the lookup. */
function pretendWeOwn(string $domain): void
{
    Cache::put('marketing:our_own_domains', collect([$domain]), now()->addHour());
}

it('does not record one of our own storefronts as a referral', function () {
    pretendWeOwn('awgifts.eu');

    expect(GetTrafficSourceFromRefererHeader::run('https://awgifts.eu/products'))->toBeNull()
        ->and(GetTrafficSourceFromRefererHeader::run('https://www.awgifts.eu/products'))->toBeNull();
});

it('drops a referral touch for our own storefront instead of crediting it', function () {
    $domain   = 'awgifts.eu';
    $customer = createCustomer($this->shop);
    $customer->trafficSources()->detach();

    pretendWeOwn($domain);

    $customer->update([
        'traffic_sources' => now()->subDay()->timestamp.'q'.$domain.'|'.now()->timestamp.'a',
    ]);
    RecalculateTrafficSourceAttribution::run($customer->fresh());

    $credited = $customer->trafficSources()->get();

    expect($credited)->toHaveCount(1)
        ->and($credited->first()->type)->toBe('organic-google')
        ->and((float) $credited->first()->pivot->share)->toBe(1.0);
});

it('records the search engines we have no channel of their own for as organic search', function () {
    $searchAbbr = TrafficSourcesTypeEnum::abbr()[TrafficSourcesTypeEnum::ORGANIC_SEARCH->value];

    expect(GetTrafficSourceFromRefererHeader::run('https://duckduckgo.com/?q=incense'))->toBe($searchAbbr.'duckduckgo.com')
        ->and(GetTrafficSourceFromRefererHeader::run('https://uk.search.yahoo.com/search?p=x'))->toBe($searchAbbr.'uk.search.yahoo.com')
        ->and(GetTrafficSourceFromRefererHeader::run('https://search.brave.com/search?q=x'))->toBe($searchAbbr.'search.brave.com');
});

it('treats a webmail host as a referral, never as a search engine', function () {
    $searchAbbr   = TrafficSourcesTypeEnum::abbr()[TrafficSourcesTypeEnum::ORGANIC_SEARCH->value];
    $referralAbbr = TrafficSourcesTypeEnum::abbr()[TrafficSourcesTypeEnum::REFERRAL->value];

    expect(GetTrafficSourceFromRefererHeader::run('https://email.seznam.cz/message/1'))->toBe($referralAbbr.'email.seznam.cz')
        ->and(GetTrafficSourceFromRefererHeader::run('https://search.seznam.cz/?q=x'))->toBe($searchAbbr.'search.seznam.cz');
});

it('counts a click arriving through the google ads redirect as paid google', function () {
    expect(GetTrafficSourceFromRefererHeader::run('https://www.googleadservices.com/pagead/aclk?x=1'))
        ->toBe(TrafficSourcesTypeEnum::abbr()[TrafficSourcesTypeEnum::GOOGLE_ADS->value]);
});

it('keeps a search engine that has its own channel out of the generic bucket', function () {
    expect(GetTrafficSourceFromRefererHeader::run('https://www.google.co.uk/search?q=x'))
        ->toBe(TrafficSourcesTypeEnum::abbr()[TrafficSourcesTypeEnum::ORGANIC_GOOGLE->value])
        ->and(GetTrafficSourceFromRefererHeader::run('https://www.bing.com/search?q=x'))
        ->toBe(TrafficSourcesTypeEnum::abbr()[TrafficSourcesTypeEnum::ORGANIC_BING->value]);
});
