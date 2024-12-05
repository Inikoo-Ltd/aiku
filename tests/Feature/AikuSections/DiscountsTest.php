<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 26 Nov 2024 21:37:12 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Analytics\GetSectionRoute;
use App\Actions\Catalogue\Shop\SeedOfferCampaigns;
use App\Actions\Catalogue\Shop\StoreShop;
use App\Actions\Discounts\Offer\StoreOffer;
use App\Actions\Discounts\Offer\UpdateOffer;
use App\Actions\Discounts\OfferCampaign\UpdateOfferCampaign;
use App\Actions\Discounts\OfferComponent\StoreOfferComponent;
use App\Actions\Discounts\OfferComponent\UpdateOfferComponent;
use App\Enums\Analytics\AikuSection\AikuSectionEnum;
use App\Models\Analytics\AikuScopedSection;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferComponent;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeAll(function () {
    loadDB();
});


beforeEach(function () {
    $this->organisation = createOrganisation();
    $this->group        = $this->organisation->group;
    $this->adminGuest   = createAdminGuest($this->organisation->group);

    $shop = Shop::first();
    if (!$shop) {
        $storeData = Shop::factory()->definition();
        $shop      = StoreShop::make()->action(
            $this->organisation,
            $storeData
        );
    }
    $this->shop = $shop;
    Config::set(
        'inertia.testing.page_paths',
        [resource_path('js/Pages/Grp')]
    );
    actingAs($this->adminGuest->getUser());
});


test('seed offer campaigns', function () {
    $shop = $this->shop;
    SeedOfferCampaigns::run($shop);
    $this->artisan('shop:seed-offer-campaigns', [
        'shop' => $shop->slug,
    ])->assertSuccessful();

    $this->group->refresh();
    $this->organisation->refresh();

    expect($this->group->discountsStats->number_offer_campaigns)->toBe(8)
        ->and($this->group->discountsStats->number_current_offer_campaigns)->toBe(0)
        ->and($this->group->discountsStats->number_offer_campaigns_state_in_process)->toBe(8)
        ->and($this->organisation->discountsStats->number_offer_campaigns)->toBe(8)
        ->and($this->organisation->discountsStats->number_current_offer_campaigns)->toBe(0)
        ->and($this->organisation->discountsStats->number_offer_campaigns_state_in_process)->toBe(8)
        ->and($shop->discountsStats->number_offer_campaigns)->toBe(8)
        ->and($shop->discountsStats->number_current_offer_campaigns)->toBe(0)
        ->and($shop->discountsStats->number_offer_campaigns_state_in_process)->toBe(8);
});

test('update offer campaign', function () {
    $shop          = $this->shop;
    $offerCampaign = $shop->offerCampaigns()->first();
    $offerCampaign = UpdateOfferCampaign::make()->action($offerCampaign, [
        'name' => 'New Name',
    ]);
    expect($offerCampaign->name)->toBe('New Name');
});

test('create offer', function () {
    $shop          = $this->shop;
    $offerCampaign = $shop->offerCampaigns()->first();
    $offer         = StoreOffer::make()->action($offerCampaign, $shop, Offer::factory()->definition());
    $offerCampaign->refresh();
    $this->group->refresh();
    $this->organisation->refresh();

    expect($offer)->toBeInstanceOf(Offer::class)
        ->and($offerCampaign->stats->number_offers)->toBe(1)
        ->and($this->group->discountsStats->number_offers)->toBe(2)
        ->and($this->organisation->discountsStats->number_offers)->toBe(2)
        ->and($offerCampaign->shop->discountsStats->number_offers)->toBe(2);

    return $offer;
});

test('update offer', function ($offer) {
    $offer = UpdateOffer::make()->action($offer, ['name' => 'New Name A']);
    expect($offer->name)->toBe('New Name A');
})->depends('create offer');

test('create offer component', function (Offer $offer) {
    $offerComponent = StoreOfferComponent::make()->action($offer, $offer->shop, OfferComponent::factory()->definition());
    $this->assertModelExists($offerComponent);

    return $offerComponent;
})->depends('create offer');

test('update offer component', function ($offerComponent) {
    $offerComponent = UpdateOfferComponent::make()->action($offerComponent, OfferComponent::factory()->definition());
    $this->assertModelExists($offerComponent);
})->depends('create offer component');

test('UI Index offer campaigns', function () {
    $response = get(route('grp.org.shops.show.discounts.campaigns.index', [$this->organisation->slug, $this->shop->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Shop/B2b/Campaigns/Campaigns')
            ->has('title')
            ->has('pageHead')
            ->has('data')
            ->has('breadcrumbs', 3);
    });
});

test('UI show offer campaigns', function () {
    $offerCampaign = $this->shop->offerCampaigns()->first();
    $response      = get(route('grp.org.shops.show.discounts.campaigns.show', [$this->organisation->slug, $this->shop->slug, $offerCampaign->slug]));

    $response->assertInertia(function (AssertableInertia $page) use ($offerCampaign) {
        $page
            ->component('Org/Shop/B2b/Campaigns/Campaign')
            ->has('title')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $offerCampaign->name)
                    ->etc()
            )
            ->has('tabs')
            ->has('navigation')
            ->has('breadcrumbs', 3);
    });
});

test('UI Index offers', function () {
    $response = get(route('grp.org.shops.show.discounts.offers.index', [$this->organisation->slug, $this->shop->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Shop/B2b/Offers/Offers')
            ->has('title')
            ->has('pageHead')
            ->has('data')
            ->has('breadcrumbs', 3);
    });
});

test('UI get section route offer dashboard', function () {
    $sectionScope = GetSectionRoute::make()->handle('grp.org.shops.show.discounts.offers.index', [
        'organisation' => $this->organisation->slug,
        'shop'         => $this->shop->slug
    ]);

    expect($sectionScope)->toBeInstanceOf(AikuScopedSection::class)
        ->and($sectionScope->code)->toBe(AikuSectionEnum::SHOP_OFFER->value)
        ->and($sectionScope->model_slug)->toBe($this->shop->slug);
});
