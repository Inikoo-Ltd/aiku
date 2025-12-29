<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 26 Nov 2024 21:37:12 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Analytics\GetSectionRoute;
use App\Actions\Catalogue\Shop\Seeders\SeedShopOfferCampaigns;
use App\Actions\Catalogue\Shop\StoreShop;
use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\Discounts\Offer\DeleteOffer;
use App\Actions\Discounts\Offer\HydrateOffers;
use App\Actions\Discounts\Offer\Search\ReindexOfferSearch;
use App\Actions\Discounts\Offer\StoreOffer;
use App\Actions\Discounts\Offer\StoreProductCategoryDiscount;
use App\Actions\Discounts\Offer\StoreVolumeGRDiscount;
use App\Actions\Discounts\Offer\UpdateOfferAllowanceSignature;
use App\Actions\Discounts\Offer\UpdateOffer;
use App\Actions\Discounts\Offer\SuspendPermanentOffer;
use App\Actions\Discounts\OfferCampaign\HydrateOfferCampaigns;
use App\Actions\Discounts\OfferCampaign\Search\ReindexOfferCampaignSearch;
use App\Actions\Discounts\OfferCampaign\UpdateOfferCampaign;
use App\Actions\Discounts\OfferAllowance\StoreOfferAllowance;
use App\Actions\Discounts\OfferAllowance\UpdateOfferAllowance;
use App\Actions\Ordering\Order\CalculateOrderDiscounts;
use App\Enums\Discounts\Offer\OfferDurationEnum;
use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Analytics\AikuSection\AikuSectionEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceStateEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceTargetTypeEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceType;
use App\Enums\Discounts\OfferCampaign\OfferCampaignTypeEnum;
use App\Models\Analytics\AikuScopedSection;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferCampaign;
use App\Models\Discounts\OfferAllowance;
use App\Models\Ordering\Order;
use Illuminate\Support\Facades\DB;
use App\Models\Helpers\TaxCategory;
use Inertia\Testing\AssertableInertia;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
    SeedShopOfferCampaigns::run($shop);
    $this->artisan('shop:seed_offer_campaigns')->assertSuccessful();

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
    $offer         = StoreOffer::make()->action($offerCampaign, Offer::factory()->definition());
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

test('create offer allowance', function (Offer $offer) {
    $allowanceData = OfferAllowance::factory()->definition();
    data_set($allowanceData, 'trigger_type', 'Shop');
    data_set($allowanceData, 'trigger_id', $offer->shop->id);

    $offerAllowance = StoreOfferAllowance::make()->action($offer, $allowanceData);
    $this->assertModelExists($offerAllowance);

    return $offerAllowance;
})->depends('create offer');

test('update offer allowance', function ($offerAllowance) {
    $offerAllowance = UpdateOfferAllowance::make()->action($offerAllowance, OfferAllowance::factory()->definition());
    $this->assertModelExists($offerAllowance);
})->depends('create offer allowance');

test('UI Discount Dashboard', function () {
    $response = get(route('grp.org.shops.show.discounts.dashboard', [$this->organisation->slug, $this->shop->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Discounts/DiscountsDashboard')
            ->has('title')
            ->has('pageHead')
            ->has('stats')
            ->has('tabs')
            ->has('breadcrumbs', 3);
    });
});

test('UI Index offer campaigns', function () {
    $response = get(route('grp.org.shops.show.discounts.campaigns.index', [$this->organisation->slug, $this->shop->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Discounts/Campaigns')
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
            ->component('Org/Discounts/Campaign')
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
            ->component('Org/Discounts/Offers')
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

test('offers search', function () {
    $this->artisan('search:offers')->assertExitCode(0);

    $offer = Offer::first();
    ReindexOfferSearch::run($offer);
    expect($offer->universalSearch()->count())->toBe(1);
});

test('offer campaigns search', function () {
    $this->artisan('search:offer_campaigns')->assertExitCode(0);
    $offerCampaign = OfferCampaign::first();
    ReindexOfferCampaignSearch::run($offerCampaign);
    expect($offerCampaign->universalSearch()->count())->toBe(1);
});

test('offer campaigns hydrator', function () {
    $this->artisan('hydrate:offer_campaigns')->assertExitCode(0);
    HydrateOfferCampaigns::run(OfferCampaign::first());
});

test('offer hydrator', function () {
    $this->artisan('hydrate:offers')->assertExitCode(0);
    HydrateOffers::run(Offer::first());
});

test('Discounts hydrator', function () {
    $this->artisan('hydrate -s disc')->assertExitCode(0);
});

test('delete offer', function () {
    $shop          = $this->shop;
    $offerCampaign = $shop->offerCampaigns()->first();
    $offer         = StoreOffer::make()->action($offerCampaign, Offer::factory()->definition());

    $allowanceData = OfferAllowance::factory()->definition();
    data_set($allowanceData, 'trigger_type', 'Shop');
    data_set($allowanceData, 'trigger_id', $offer->shop->id);

    StoreOfferAllowance::make()->action($offer, $allowanceData);

    DeleteOffer::make()->action($offer);

    $this->assertSoftDeleted($offer);
    $this->assertSoftDeleted($offer->offerAllowances()->withTrashed()->first());
});

test('force delete offer', function () {
    $shop          = $this->shop;
    $offerCampaign = $shop->offerCampaigns()->first();
    $offer         = StoreOffer::make()->action($offerCampaign, Offer::factory()->definition());

    $allowanceData = OfferAllowance::factory()->definition();
    data_set($allowanceData, 'trigger_type', 'Shop');
    data_set($allowanceData, 'trigger_id', $offer->shop->id);

    StoreOfferAllowance::make()->action($offer, $allowanceData);
    $offerAllowance = $offer->offerAllowances()->first();

    DeleteOffer::make()->action($offer, true);

    $this->assertModelMissing($offer);
    $this->assertModelMissing($offerAllowance);
});

test('create first order bonus', function () {
    $shop = $this->shop;
    if (!$shop->offerCampaigns()->where('type', \App\Enums\Discounts\OfferCampaign\OfferCampaignTypeEnum::FIRST_ORDER)->exists()) {
        SeedShopOfferCampaigns::run($shop);
    }

    $this->artisan('offer:create_first_order_bonus', [
        'shop'     => $shop->slug,
        'amount'   => 100,
        'discount' => 0.10
    ])->assertExitCode(0);

    $offer = \App\Models\Discounts\Offer::where('shop_id', $shop->id)
        ->where('type', 'Amount AND Order Number')
        ->latest()
        ->first();

    expect($offer)->toBeInstanceOf(Offer::class)
        ->and($offer->trigger_data['min_amount'])->toBe(100)
        ->and($offer->offerAllowances->first()->data['percentage_off'])->toBe(0.10);
});

test('update offer allowance signature', function () {
    $shop          = $this->shop;
    $offerCampaign = $shop->offerCampaigns()->first();
    $offer         = StoreOffer::make()->action($offerCampaign, Offer::factory()->definition());

    $allowanceData = OfferAllowance::factory()->definition();
    data_set($allowanceData, 'type', OfferAllowanceType::PERCENTAGE_OFF);
    data_set($allowanceData, 'target_type', OfferAllowanceTargetTypeEnum::ALL_PRODUCTS_IN_ORDER);
    data_set($allowanceData, 'data.percentage_off', 10);
    // Ensure status is true via state
    data_set($allowanceData, 'state', OfferAllowanceStateEnum::ACTIVE);

    $allowance1 = StoreOfferAllowance::make()->action($offer, $allowanceData);
    $allowance1->update(['status' => true]);

    UpdateOfferAllowanceSignature::run($offer);
    $offer->refresh();

    $expectedSignature1 = 'all_products_in_order:percentage_off:10';
    expect($offer->allowance_signature)->toBe($expectedSignature1);

    // Add a second allowance
    $allowanceData2 = OfferAllowance::factory()->definition();
    data_set($allowanceData2, 'type', OfferAllowanceType::PERCENTAGE_OFF);
    data_set($allowanceData2, 'target_type', OfferAllowanceTargetTypeEnum::ALL_PRODUCTS_IN_PRODUCT_CATEGORY);
    data_set($allowanceData2, 'data.percentage_off', 20);
    data_set($allowanceData2, 'state', OfferAllowanceStateEnum::ACTIVE);

    $allowance2 = StoreOfferAllowance::make()->action($offer, $allowanceData2);
    $allowance2->update(['status' => true]);

    UpdateOfferAllowanceSignature::run($offer);
    $offer->refresh();

    // Order depends on DB retrieval order (default ID asc).
    $expectedSignature2 = 'all_products_in_order:percentage_off:10|all_products_in_product_category:percentage_off:20';
    expect($offer->allowance_signature)->toBe($expectedSignature2);

    // Deactivate the first allowance
    $allowance1->update(['status' => false]);
    UpdateOfferAllowanceSignature::run($offer);
    $offer->refresh();

    $expectedSignature3 = 'all_products_in_product_category:percentage_off:20';
    expect($offer->allowance_signature)->toBe($expectedSignature3);

    // Test Command with ID
    $allowance1->update(['status' => true]); // Re-enable
    $offer->update(['allowance_signature' => '']);

    $this->artisan('offer:update_allowance_signature', ['offer' => $offer->id])
        ->assertExitCode(0);

    $offer->refresh();
    expect($offer->allowance_signature)->toBe($expectedSignature2);

    // Test Command without ID (All offers)
    $offer->update(['allowance_signature' => '']);
    $this->artisan('offer:update_allowance_signature')
        ->assertExitCode(0);

    $offer->refresh();
    expect($offer->allowance_signature)->toBe($expectedSignature2);
});

test('force delete offer with soft deleted allowances', function () {
    $shop          = $this->shop;
    $offerCampaign = $shop->offerCampaigns()->first();
    $offer         = StoreOffer::make()->action($offerCampaign, Offer::factory()->definition());

    $allowanceData = OfferAllowance::factory()->definition();
    data_set($allowanceData, 'trigger_type', 'Shop');
    data_set($allowanceData, 'trigger_id', $offer->shop->id);

    StoreOfferAllowance::make()->action($offer, $allowanceData);

    /** @var OfferAllowance $offerAllowance */
    $offerAllowance = $offer->offerAllowances()->first();

    // Soft delete the allowance first
    $offerAllowance->delete();
    $this->assertSoftDeleted($offerAllowance);

    // Force to delete the offer
    DeleteOffer::make()->action($offer, true);

    // Check if the offer is gone
    $this->assertModelMissing($offer);
    // Check if the allowance is gone (force deleted)
    $this->assertModelMissing($offerAllowance);
});

test('create volume discount', function () {
    $shop = $this->shop;
    if (!$shop->offerCampaigns()->where('type', \App\Enums\Discounts\OfferCampaign\OfferCampaignTypeEnum::VOLUME_DISCOUNT)->exists()) {
        SeedShopOfferCampaigns::run($shop);
    }

    /** @var ProductCategory $category */
    $category = ProductCategory::factory()->create([
        'shop_id'         => $shop->id,
        'organisation_id' => $shop->organisation_id,
        'group_id'        => $shop->group_id,
        'code'            => 'TEST-CAT',
        'type'            => ProductCategoryTypeEnum::FAMILY->value
    ]);

    $this->artisan('offer:create_volume_gr_discount', [
        'family'        => $category->slug,
        'item_quantity' => 5,
        'days'          => 0,
        'discount'      => .20
    ])->assertExitCode(0);

    $offer = Offer::where('shop_id', $shop->id)
        ->where('trigger_type', 'ProductCategory')
        ->where('trigger_id', $category->id)
        ->first();

    expect($offer)->toBeInstanceOf(Offer::class)
        ->and($offer->status)->toBeTrue()
        ->and($offer->offerAllowances->first()->status)->toBeTrue()
        ->and($offer->allowance_signature)->toBe('all_products_in_product_category:1:percentage_off:0.2')
        ->and($offer->trigger_data['item_quantity'])->toBe(5)
        ->and($offer->offerAllowances->first()->data['percentage_off'])->toBe(0.2);
});

test('create product category discount', function () {
    $shop = $this->shop;
    if (!$shop->offerCampaigns()->where('type', OfferCampaignTypeEnum::CATEGORY_OFFERS)->exists()) {
        SeedShopOfferCampaigns::run($shop);
    }

    /** @var ProductCategory $category */
    $category = ProductCategory::factory()->create([
        'shop_id'         => $shop->id,
        'organisation_id' => $shop->organisation_id,
        'group_id'        => $shop->group_id,
        'code'            => 'CAT-DISC',
        'type'            => ProductCategoryTypeEnum::FAMILY->value
    ]);

    $this->artisan('offer:create_category_discount', [
        'category'      => $category->slug,
        'item_quantity' => 3,
        'discount'      => .15,
        'end_at'        => now()->addDays(7)->toDateTimeString(),
    ])->assertExitCode(0);

    $offer = Offer::where('shop_id', $shop->id)
        ->where('trigger_type', 'ProductCategory')
        ->where('trigger_id', $category->id)
        ->first();

    expect($offer)->toBeInstanceOf(Offer::class)
        ->and($offer->status)->toBeTrue()
        ->and($offer->offerAllowances->first()->status)->toBeTrue()
        ->and($offer->duration)->toBe(OfferDurationEnum::INTERVAL)
        ->and($offer->trigger_data['item_quantity'])->toBe(3)
        ->and($offer->offerAllowances->first()->data['percentage_off'])->toBe(0.15);

    $offer2 = StoreProductCategoryDiscount::make()->action(
        $category,
        [
            'trigger_data_item_quantity' => 2,
            'percentage_off'             => .25,
            'end_at'                     => now()->addDays(14)->toDateTimeString(),
        ]
    );

    expect($offer2)->toBeInstanceOf(Offer::class)
        ->and($offer2->offerAllowances->count())->toBe(1);
});

test('create volume gr discount', function () {
    $shop = $this->shop;
    if (!$shop->offerCampaigns()->where('type', OfferCampaignTypeEnum::VOLUME_DISCOUNT)->exists()) {
        SeedShopOfferCampaigns::run($shop);
    }

    /** @var ProductCategory $category */
    $category = ProductCategory::factory()->create([
        'shop_id'         => $shop->id,
        'organisation_id' => $shop->organisation_id,
        'group_id'        => $shop->group_id,
        'code'            => 'VOL-GR',
        'type'            => ProductCategoryTypeEnum::FAMILY->value
    ]);

    $this->artisan('offer:create_volume_gr_discount', [
        'family'        => $category->slug,
        'item_quantity' => 5,
        'days'          => 30,
        'discount'      => .20,
        'end_at'        => now()->addDays(60)->toDateTimeString(),
    ])->assertExitCode(0);

    $offer = Offer::where('shop_id', $shop->id)
        ->where('trigger_type', 'ProductCategory')
        ->where('trigger_id', $category->id)
        ->first();

    expect($offer)->toBeInstanceOf(Offer::class)
        ->and($offer->status)->toBeTrue()
        ->and($offer->offerAllowances->first()->status)->toBeTrue()
        ->and($offer->duration)->toBe(OfferDurationEnum::INTERVAL)
        ->and($offer->trigger_data['item_quantity'])->toBe(5)
        ->and($offer->trigger_data['interval'])->toBe(30)
        ->and($offer->offerAllowances->first()->data['percentage_off'])->toBe(0.20);

    $offer2 = StoreVolumeGRDiscount::make()->action(
        $category,
        [
            'trigger_data_item_quantity' => 10,
            'percentage_off'             => .30,
            'interval'                   => 60,
        ]
    );

    expect($offer2)->toBeInstanceOf(Offer::class)
        ->and($offer2->offerAllowances->count())->toBe(1)
        ->and($offer2->duration)->toBe(OfferDurationEnum::PERMANENT)
        ->and($offer2->trigger_data['item_quantity'])->toBe(10)
        ->and($offer2->trigger_data['interval'])->toBe(60)
        ->and($offer2->offerAllowances->first()->data['percentage_off'])->toBe(0.30);
});

test('suspend permanent offer suspends offer and active allowances', function () {
    $shop          = $this->shop;
    $offerCampaign = $shop->offerCampaigns()->first();

    $offerData = Offer::factory()->definition();
    $offer     = StoreOffer::make()->action($offerCampaign, $offerData);
    // set required flags directly to avoid validation enum rule
    $offer->duration = OfferDurationEnum::PERMANENT;
    $offer->state    = OfferStateEnum::ACTIVE;
    $offer->status   = true;
    $offer->save();

    $allowanceData = OfferAllowance::factory()->definition();
    data_set($allowanceData, 'trigger_type', 'Shop');
    data_set($allowanceData, 'trigger_id', $offer->shop->id);
    $allowance           = StoreOfferAllowance::make()->action($offer, $allowanceData);
    $allowance->duration = OfferDurationEnum::PERMANENT;
    $allowance->state    = OfferAllowanceStateEnum::ACTIVE;
    $allowance->status   = true;
    $allowance->save();

    $offer->refresh();
    expect($offer->state)->toBe(OfferStateEnum::ACTIVE)
        ->and($offer->status)->toBeTrue();

    $suspended = SuspendPermanentOffer::run($offer);

    $suspended->refresh();
    expect($suspended->state)->toBe(OfferStateEnum::SUSPENDED)
        ->and($suspended->status)->toBeFalse()
        ->and($suspended->end_at)->not->toBeNull();

    $allowance = $suspended->offerAllowances()->first();
    expect($allowance->state)->toBe(OfferAllowanceStateEnum::SUSPENDED)
        ->and($allowance->status)->toBeFalse()
        ->and($allowance->end_at)->not->toBeNull();
});

test('suspend permanent offer is safe to run twice', function () {
    $shop          = $this->shop;
    $offerCampaign = $shop->offerCampaigns()->first();

    $offerData       = Offer::factory()->definition();
    $offer           = StoreOffer::make()->action($offerCampaign, $offerData);
    $offer->duration = OfferDurationEnum::PERMANENT;
    $offer->state    = OfferStateEnum::ACTIVE;
    $offer->status   = true;
    $offer->save();

    SuspendPermanentOffer::run($offer);
    $offer->refresh();
    expect($offer->state)->toBe(OfferStateEnum::SUSPENDED);

    // Run again should remain suspended without error
    SuspendPermanentOffer::run($offer);
    $offer->refresh();
    expect($offer->state)->toBe(OfferStateEnum::SUSPENDED)
        ->and($offer->status)->toBeFalse();
});

test('suspend permanent offer aborts on non-permanent offer', function () {
    $shop          = $this->shop;
    $offerCampaign = $shop->offerCampaigns()->first();

    $offerData       = Offer::factory()->definition();
    $offer           = StoreOffer::make()->action($offerCampaign, $offerData);
    $offer->duration = OfferDurationEnum::INTERVAL;
    $offer->save();

    expect(fn () => SuspendPermanentOffer::run($offer))
        ->toThrow(HttpException::class);
});

describe('calculate order discounts', function () {

    test('CalculateOrderDiscounts: Amount AND Order Number trigger applies discount', function () {
        $shop = $this->shop;
        $customer = StoreCustomer::make()->action($shop, array_merge(Customer::factory()->definition(), ['email' => 'c1@example.com']), strict: false);

        if (!$shop->offerCampaigns()->exists()) {
            SeedShopOfferCampaigns::run($shop);
        }
        $offerCampaign = $shop->offerCampaigns()->first();
        $offer = StoreOffer::run($offerCampaign, [
            'code' => 'FIRSTORDER_'.uniqid(),
            'name' => 'First Order Discount',
            'type' => 'Amount AND Order Number',
            'trigger_type' => 'Customer',
            'state' => OfferStateEnum::ACTIVE,
            'trigger_data' => [
                'min_amount' => 100,
                'order_number' => 1
            ]
        ]);
        $offer->update(['status' => true]);

        $allowance = StoreOfferAllowance::run($offer, [
            'type' => OfferAllowanceType::PERCENTAGE_OFF,
            'target_type' => OfferAllowanceTargetTypeEnum::ALL_PRODUCTS_IN_ORDER,
            'data' => ['percentage_off' => 0.10],
            'state' => OfferAllowanceStateEnum::ACTIVE,
            'trigger_scope' => 'NA'
        ]);
        $allowance->update(['status' => true]);
        UpdateOfferAllowanceSignature::run($offer);

        $order = Order::factory()->create([
            'shop_id' => $shop->id,
            'organisation_id' => $shop->organisation_id,
            'group_id' => $shop->group_id,
            'customer_id' => $customer->id,
            'currency_id' => $shop->currency_id,
            'tax_category_id' => TaxCategory::first()?->id,
            'grp_exchange' => 1,
            'org_exchange' => 1,
            'gross_amount' => 150,
            'categories_data' => ['family_ids' => []],
            'state' => OrderStateEnum::CREATING
        ]);
        $order->stats()->create();

        DB::table('transactions')->insert([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'model_type' => 'Product',
            'model_id' => 1,
            'gross_amount' => 150,
            'net_amount' => 150,
            'quantity_ordered' => 1,
            'group_id' => $shop->group_id,
            'organisation_id' => $shop->organisation_id,
            'shop_id' => $shop->id,
            'tax_category_id' => TaxCategory::first()?->id,
            'date' => now(),
            'data' => '{}',
        ]);

        CalculateOrderDiscounts::run($order);

        $transaction = DB::table('transactions')->where('order_id', $order->id)->first();
        expect((float)$transaction->net_amount)->toBe(135.0)
            ->and($transaction->offers_data)->not->toBeEmpty();
    });

    test('CalculateOrderDiscounts: Amount AND Order Number trigger fails if amount too low', function () {
        $shop = $this->shop;
        $customer = StoreCustomer::make()->action($shop, array_merge(Customer::factory()->definition(), ['email' => 'c2@example.com']), strict: false);

        $offerCampaign = $shop->offerCampaigns()->first();
        $offer = StoreOffer::run($offerCampaign, [
            'code' => 'HIGHVAL_'.uniqid(),
            'name' => 'High Value First Order',
            'type' => 'Amount AND Order Number',
            'trigger_type' => 'Customer',
            'state' => OfferStateEnum::ACTIVE,
            'trigger_data' => [
                'min_amount' => 1000,
                'order_number' => 1
            ]
        ]);
        $offer->update(['status' => true]);

        $allowance = StoreOfferAllowance::run($offer, [
            'type' => OfferAllowanceType::PERCENTAGE_OFF,
            'target_type' => OfferAllowanceTargetTypeEnum::ALL_PRODUCTS_IN_ORDER,
            'data' => ['percentage_off' => 0.50],
            'state' => OfferAllowanceStateEnum::ACTIVE,
            'trigger_scope' => 'NA'
        ]);
        $allowance->update(['status' => true]);
        UpdateOfferAllowanceSignature::run($offer);

        $order = Order::factory()->create([
            'shop_id' => $shop->id,
            'organisation_id' => $shop->organisation_id,
            'group_id' => $shop->group_id,
            'customer_id' => $customer->id,
            'currency_id' => $shop->currency_id,
            'tax_category_id' => TaxCategory::first()?->id,
            'grp_exchange' => 1,
            'org_exchange' => 1,
            'gross_amount' => 150,
            'categories_data' => ['family_ids' => []],
            'state' => OrderStateEnum::CREATING
        ]);
        $order->stats()->create();

        DB::table('transactions')->insert([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'model_type' => 'Product',
            'model_id' => 1,
            'gross_amount' => 150,
            'net_amount' => 150,
            'quantity_ordered' => 1,
            'group_id' => $shop->group_id,
            'organisation_id' => $shop->organisation_id,
            'shop_id' => $shop->id,
            'tax_category_id' => TaxCategory::first()?->id,
            'date' => now(),
            'data' => '{}',
        ]);

        CalculateOrderDiscounts::run($order);

        $transaction = DB::table('transactions')->where('order_id', $order->id)->first();
        expect((float)$transaction->net_amount)->toBe(150.0);
    });

    test('CalculateOrderDiscounts: Category Ordered trigger', function () {
        $shop = $this->shop;
        $customer = StoreCustomer::make()->action($shop, array_merge(Customer::factory()->definition(), ['email' => 'c3@example.com']), strict: false);

        $category = ProductCategory::factory()->create([
            'shop_id' => $shop->id,
            'organisation_id' => $shop->organisation_id,
            'group_id' => $shop->group_id,
            'type' => ProductCategoryTypeEnum::FAMILY->value,
            'code' => 'CAT_'.uniqid()
        ]);

        $offerCampaign = $shop->offerCampaigns()->first();
        $offer = StoreOffer::run($offerCampaign, [
            'code' => 'FAMDISC_'.uniqid(),
            'name' => 'Family Discount',
            'type' => 'Category Ordered',
            'trigger_type' => 'ProductCategory',
            'trigger_id' => $category->id,
            'state' => OfferStateEnum::ACTIVE,
        ]);
        $offer->update(['status' => true]);

        $allowance = StoreOfferAllowance::run($offer, [
            'type' => OfferAllowanceType::PERCENTAGE_OFF,
            'target_type' => OfferAllowanceTargetTypeEnum::ALL_PRODUCTS_IN_PRODUCT_CATEGORY,
            'data' => ['percentage_off' => 0.20, 'category_id' => $category->id],
            'state' => OfferAllowanceStateEnum::ACTIVE,
            'trigger_scope' => 'NA'
        ]);
        $allowance->update(['status' => true]);
        UpdateOfferAllowanceSignature::run($offer);

        $order = Order::factory()->create([
            'shop_id' => $shop->id,
            'organisation_id' => $shop->organisation_id,
            'group_id' => $shop->group_id,
            'customer_id' => $customer->id,
            'currency_id' => $shop->currency_id,
            'tax_category_id' => TaxCategory::first()?->id,
            'grp_exchange' => 1,
            'org_exchange' => 1,
            'categories_data' => ['family_ids' => [$category->id]],
            'state' => OrderStateEnum::CREATING
        ]);
        $order->stats()->create();

        DB::table('transactions')->insert([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'model_type' => 'Product',
            'model_id' => 1,
            'family_id' => $category->id,
            'gross_amount' => 100,
            'net_amount' => 100,
            'quantity_ordered' => 1,
            'group_id' => $shop->group_id,
            'organisation_id' => $shop->organisation_id,
            'shop_id' => $shop->id,
            'tax_category_id' => TaxCategory::first()?->id,
            'date' => now(),
            'data' => '{}',
        ]);

        CalculateOrderDiscounts::run($order);

        $transaction = DB::table('transactions')->where('order_id', $order->id)->first();
        expect((float)$transaction->net_amount)->toBe(80.0);
    });

    test('CalculateOrderDiscounts: Precedence logic', function () {
        $shop = $this->shop;
        $customer = StoreCustomer::make()->action($shop, array_merge(Customer::factory()->definition(), ['email' => 'c4@example.com']), strict: false);

        $offerCampaign = $shop->offerCampaigns()->first();

        // 10% discount
        $offer1 = StoreOffer::run($offerCampaign, [
            'code' => '10OFF_'.uniqid(),
            'name' => '10% Off',
            'type' => 'Amount AND Order Number',
            'trigger_type' => 'Customer',
            'state' => OfferStateEnum::ACTIVE,
            'trigger_data' => ['min_amount' => 0, 'order_number' => 1]
        ]);
        $offer1->update(['status' => true]);

        $allowance1 = StoreOfferAllowance::run($offer1, [
            'type' => OfferAllowanceType::PERCENTAGE_OFF,
            'target_type' => OfferAllowanceTargetTypeEnum::ALL_PRODUCTS_IN_ORDER,
            'data' => ['percentage_off' => 0.10],
            'state' => OfferAllowanceStateEnum::ACTIVE,
            'trigger_scope' => 'NA'
        ]);
        $allowance1->update(['status' => true]);
        UpdateOfferAllowanceSignature::run($offer1);

        // 20% discount
        $offer2 = StoreOffer::run($offerCampaign, [
            'code' => '20OFF_'.uniqid(),
            'name' => '20% Off',
            'type' => 'Amount AND Order Number',
            'trigger_type' => 'Customer',
            'state' => OfferStateEnum::ACTIVE,
            'trigger_data' => ['min_amount' => 0, 'order_number' => 1]
        ]);
        $offer2->update(['status' => true]);

        $allowance2 = StoreOfferAllowance::run($offer2, [
            'type' => OfferAllowanceType::PERCENTAGE_OFF,
            'target_type' => OfferAllowanceTargetTypeEnum::ALL_PRODUCTS_IN_ORDER,
            'data' => ['percentage_off' => 0.20],
            'state' => OfferAllowanceStateEnum::ACTIVE,
            'trigger_scope' => 'NA'
        ]);
        $allowance2->update(['status' => true]);
        UpdateOfferAllowanceSignature::run($offer2);

        $order = Order::factory()->create([
            'shop_id' => $shop->id,
            'organisation_id' => $shop->organisation_id,
            'group_id' => $shop->group_id,
            'customer_id' => $customer->id,
            'currency_id' => $shop->currency_id,
            'tax_category_id' => TaxCategory::first()?->id,
            'grp_exchange' => 1,
            'org_exchange' => 1,
            'gross_amount' => 100,
            'categories_data' => ['family_ids' => []],
            'state' => OrderStateEnum::CREATING
        ]);
        $order->stats()->create();

        DB::table('transactions')->insert([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'model_type' => 'Product',
            'model_id' => 1,
            'gross_amount' => 100,
            'net_amount' => 100,
            'quantity_ordered' => 1,
            'group_id' => $shop->group_id,
            'organisation_id' => $shop->organisation_id,
            'shop_id' => $shop->id,
            'tax_category_id' => TaxCategory::first()?->id,
            'date' => now(),
            'data' => '{}',
        ]);

        CalculateOrderDiscounts::run($order);

        $transaction = DB::table('transactions')->where('order_id', $order->id)->first();
        expect((float)$transaction->net_amount)->toBe(80.0); // 20% should win
    });

    test('CalculateOrderDiscounts: Category Quantity Ordered Order Interval trigger applies discount on quantity', function () {
        $shop = $this->shop;
        $customer = StoreCustomer::make()->action($shop, array_merge(Customer::factory()->definition(), ['email' => 'c5@example.com']), strict: false);

        $category = ProductCategory::factory()->create([
            'shop_id' => $shop->id,
            'organisation_id' => $shop->organisation_id,
            'group_id' => $shop->group_id,
            'type' => ProductCategoryTypeEnum::FAMILY->value,
            'code' => 'QTYCAT_'.uniqid()
        ]);

        $offerCampaign = $shop->offerCampaigns()->first();
        $offer = StoreOffer::run($offerCampaign, [
            'code' => 'BULKFAM_'.uniqid(),
            'name' => 'Bulk Family Discount',
            'type' => 'Category Quantity Ordered Order Interval',
            'trigger_type' => 'ProductCategory',
            'trigger_id' => $category->id,
            'state' => OfferStateEnum::ACTIVE,
            'trigger_data' => [
                'item_quantity' => 10,
                'interval' => 30
            ]
        ]);
        $offer->update(['status' => true]);

        $allowance = StoreOfferAllowance::run($offer, [
            'type' => OfferAllowanceType::PERCENTAGE_OFF,
            'target_type' => OfferAllowanceTargetTypeEnum::ALL_PRODUCTS_IN_PRODUCT_CATEGORY,
            'data' => ['percentage_off' => 0.25, 'category_id' => $category->id],
            'state' => OfferAllowanceStateEnum::ACTIVE,
            'trigger_scope' => 'NA'
        ]);
        $allowance->update(['status' => true]);
        UpdateOfferAllowanceSignature::run($offer);

        $order = Order::factory()->create([
            'shop_id' => $shop->id,
            'organisation_id' => $shop->organisation_id,
            'group_id' => $shop->group_id,
            'customer_id' => $customer->id,
            'currency_id' => $shop->currency_id,
            'tax_category_id' => TaxCategory::first()?->id,
            'grp_exchange' => 1,
            'org_exchange' => 1,
            'categories_data' => [
                'family_ids' => [$category->id],
                'family' => [
                    $category->id => ['quantity' => 12]
                ]
            ],
            'state' => OrderStateEnum::CREATING
        ]);

        DB::table('transactions')->insert([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'model_type' => 'Product',
            'model_id' => 1,
            'family_id' => $category->id,
            'gross_amount' => 1000,
            'net_amount' => 1000,
            'quantity_ordered' => 12,
            'group_id' => $shop->group_id,
            'organisation_id' => $shop->organisation_id,
            'shop_id' => $shop->id,
            'tax_category_id' => TaxCategory::first()?->id,
            'date' => now(),
            'data' => '{}',
        ]);

        CalculateOrderDiscounts::run($order);

        $transaction = DB::table('transactions')->where('order_id', $order->id)->first();
        expect((float)$transaction->net_amount)->toBe(750.0)
            ->and($transaction->offers_data)->not->toBeEmpty();
    });

    test('CalculateOrderDiscounts: Category Quantity Ordered Order Interval trigger applies discount on interval', function () {
        $shop = $this->shop;

        // Mock customer last_invoiced_at
        $customer = StoreCustomer::make()->action($shop, array_merge(Customer::factory()->definition(), [
            'email' => 'c6@example.com',
            'last_invoiced_at' => now()->subDays(5)
        ]), strict: false);

        $category = ProductCategory::factory()->create([
            'shop_id' => $shop->id,
            'organisation_id' => $shop->organisation_id,
            'group_id' => $shop->group_id,
            'type' => ProductCategoryTypeEnum::FAMILY->value,
            'code' => 'INTCAT_'.uniqid()
        ]);

        $offerCampaign = $shop->offerCampaigns()->first();
        $offer = StoreOffer::run($offerCampaign, [
            'code' => 'RECENT_'.uniqid(),
            'name' => 'Recent Order Discount',
            'type' => 'Category Quantity Ordered Order Interval',
            'trigger_type' => 'ProductCategory',
            'trigger_id' => $category->id,
            'state' => OfferStateEnum::ACTIVE,
            'trigger_data' => [
                'item_quantity' => 100, // Very high, won't trigger by quantity
                'interval' => 10 // Within 10 days
            ]
        ]);
        $offer->update(['status' => true]);

        $allowance = StoreOfferAllowance::run($offer, [
            'type' => OfferAllowanceType::PERCENTAGE_OFF,
            'target_type' => OfferAllowanceTargetTypeEnum::ALL_PRODUCTS_IN_PRODUCT_CATEGORY,
            'data' => ['percentage_off' => 0.15, 'category_id' => $category->id],
            'state' => OfferAllowanceStateEnum::ACTIVE,
            'trigger_scope' => 'NA'
        ]);
        $allowance->update(['status' => true]);
        UpdateOfferAllowanceSignature::run($offer);

        $order = Order::factory()->create([
            'shop_id' => $shop->id,
            'organisation_id' => $shop->organisation_id,
            'group_id' => $shop->group_id,
            'customer_id' => $customer->id,
            'currency_id' => $shop->currency_id,
            'tax_category_id' => TaxCategory::first()?->id,
            'grp_exchange' => 1,
            'org_exchange' => 1,
            'categories_data' => [
                'family_ids' => [$category->id],
                'family' => [
                    $category->id => ['quantity' => 1] // Low quantity
                ]
            ],
            'state' => OrderStateEnum::CREATING
        ]);

        DB::table('transactions')->insert([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'model_type' => 'Product',
            'model_id' => 1,
            'family_id' => $category->id,
            'gross_amount' => 100,
            'net_amount' => 100,
            'quantity_ordered' => 1,
            'group_id' => $shop->group_id,
            'organisation_id' => $shop->organisation_id,
            'shop_id' => $shop->id,
            'tax_category_id' => TaxCategory::first()?->id,
            'date' => now(),
            'data' => '{}',
        ]);

        CalculateOrderDiscounts::run($order);

        $transaction = DB::table('transactions')->where('order_id', $order->id)->first();
        expect((float)$transaction->net_amount)->toBe(85.0);
    });

})->todo();
