<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 26 Nov 2024 21:37:12 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Analytics\GetSectionRoute;
use App\Actions\Catalogue\Shop\Seeders\SeedShopOfferCampaigns;
use App\Actions\CRM\Customer\UpdateCustomerLastInvoicedDate;
use App\Actions\Discounts\Offer\DeleteOffer;
use App\Actions\Discounts\Offer\HydrateOffers;
use App\Actions\Discounts\Offer\Search\ReindexOfferSearch;
use App\Actions\Discounts\Offer\StoreOffer;
use App\Actions\Discounts\Offer\StoreProductCategoryDiscount;
use App\Actions\Discounts\Offer\StoreVolumeGRDiscount;
use App\Actions\Discounts\Offer\UpdateOfferAllowanceSignature;
use App\Actions\Discounts\Offer\UpdateOffer;
use App\Actions\Discounts\Offer\SuspendOffer;
use App\Actions\Discounts\OfferCampaign\HydrateOfferCampaigns;
use App\Actions\Discounts\OfferCampaign\Search\ReindexOfferCampaignSearch;
use App\Actions\Discounts\OfferCampaign\UpdateOfferCampaign;
use App\Actions\Discounts\OfferAllowance\StoreOfferAllowance;
use App\Actions\Discounts\OfferAllowance\UpdateOfferAllowance;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\Ordering\Transaction\UpdateTransaction;
use App\Enums\Discounts\Offer\OfferDurationEnum;
use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Enums\Analytics\AikuSection\AikuSectionEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceStateEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceTargetTypeEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceType;
use App\Enums\Discounts\OfferCampaign\OfferCampaignTypeEnum;
use App\Models\Analytics\AikuScopedSection;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferCampaign;
use App\Models\Discounts\OfferAllowance;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use App\Actions\Discounts\Offer\StoreFirstOrderBonus;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeAll(function () {
    loadDB();
});


beforeEach(function () {
    list(
        $this->organisation,
        $this->user,
        $this->shop
    ) = createShop();

    $this->group      = $this->organisation->group;
    $this->adminGuest = createAdminGuest($this->organisation->group);

    list(
        $this->tradeUnit,
        $this->product
    ) = createProduct($this->shop);

    $this->customer = createCustomer($this->shop);


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
        ->and($offer->allowance_signature)->toBe('all_products_in_product_category:3:percentage_off:0.2')
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

    $suspended = SuspendOffer::run($offer);

    $suspended->refresh();
    expect($suspended->state)->toBe(OfferStateEnum::SUSPENDED)
        ->and($suspended->status)->toBeFalse();

    $allowance = $suspended->offerAllowances()->first();
    expect($allowance->state)->toBe(OfferAllowanceStateEnum::SUSPENDED)
        ->and($allowance->status)->toBeFalse();
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

    SuspendOffer::run($offer);
    $offer->refresh();
    expect($offer->state)->toBe(OfferStateEnum::SUSPENDED);

    // Run again should remain suspended without error
    SuspendOffer::run($offer);
    $offer->refresh();
    expect($offer->state)->toBe(OfferStateEnum::SUSPENDED)
        ->and($offer->status)->toBeFalse();
});


test('delete all active offers', function () {
    foreach (Offer::where('status', true)->get() as $offer) {
        DeleteOffer::make()->action($offer, true);
    }
    foreach (Offer::where('state', OfferStateEnum::IN_PROCESS)->get() as $offer) {
        DeleteOffer::make()->action($offer, true);
    }
    expect(Offer::where('status', true)->count())->toBe(0);
});

describe('calculate order discounts', function () {
    test('CalculateOrderDiscounts: Amount AND Order Number trigger applies discount', function () {
        $shop     = $this->shop;
        $customer = $this->customer;

        if (!$shop->offerCampaigns()->exists()) {
            SeedShopOfferCampaigns::run($shop);
        }


        $firstOrderBonusOffer =
            StoreFirstOrderBonus::make()->action(
                $shop,
                [
                    'trigger_data_min_amount' => 150.0,
                    'percentage_off'          => 0.10
                ]
            );

        expect($firstOrderBonusOffer)->toBeInstanceOf(Offer::class)
            ->and($firstOrderBonusOffer->status)->toBeTrue();


        $order = StoreOrder::make()->action($customer, []);

        expect($order)->toBeInstanceOf(Order::class);

        $product = $shop->products()->first();

        expect($product)->toBeInstanceOf(Product::class)
            ->and((float)$product->price)->toBe(100.0);

        $transactionData = [
            'quantity_ordered' => 1,
        ];
        $item            = $product->historicAsset;
        $transaction     = StoreTransaction::make()->action($order, $item, $transactionData);
        $order->refresh();
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(100.0);

        UpdateTransaction::run($transaction, [
            'quantity_ordered' => 2,
        ]);
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(180.0);
    });


    test('CalculateOrderDiscounts: Category Ordered trigger', function () {
        $product = $this->shop->products()->first();

        $categoryDiscount = StoreProductCategoryDiscount::make()->action(
            $product->family,
            [
                'trigger_data_item_quantity' => 1,
                'percentage_off'             => 0.60,
            ]
        );

        expect($categoryDiscount)->toBeInstanceOf(Offer::class);

        $order = Order::first();

        $transaction = DB::table('transactions')->where('order_id', $order->id)->first();
        expect((float)$transaction->net_amount)->toBe(80.0);
    });

    test('suspend all active offers', function () {
        foreach (Offer::where('state', OfferStateEnum::ACTIVE)->get() as $offer) {
            SuspendOffer::run($offer);
        }
        expect(Offer::where('status', true)->count())->toBe(0);

        $order = Order::first();

        $transaction = DB::table('transactions')->where('order_id', $order->id)->first();
        expect((float)$transaction->net_amount)->toBe(200.0);
    });

    test('CalculateOrderDiscounts: Category Ordered trigger item quantity', function () {
        $product = $this->shop->products()->first();

        $categoryDiscount = StoreProductCategoryDiscount::make()->action(
            $product->family,
            [
                'trigger_data_item_quantity' => 5,
                'percentage_off'             => 0.30,
            ]
        );

        expect($categoryDiscount)->toBeInstanceOf(Offer::class)
            ->and($categoryDiscount->status)->toBeTrue()
            ->and($categoryDiscount->trigger_type)->toBe('ProductCategory')
            ->and($categoryDiscount->type)->toBe('Category Quantity Ordered');

        $order = Order::first();

        $transaction = Transaction::where('order_id', $order->id)->first();
        expect((float)$transaction->net_amount)->toBe(200.0);

        UpdateTransaction::run($transaction, [
            'quantity_ordered' => 5,
        ]);
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(350.0);

        SuspendOffer::run($categoryDiscount);

    });

    test('CalculateOrderDiscounts: Vol/GR', function () {
        $product = $this->shop->products()->first();

        $VolGRDiscount = StoreVolumeGRDiscount::make()->action(
            $product->family,
            [
                'trigger_data_item_quantity' => 5,
                'percentage_off'             => 0.30,
                'interval'                   => 30,
            ]
        );

        expect($VolGRDiscount)->toBeInstanceOf(Offer::class)
            ->and($VolGRDiscount->status)->toBeTrue()
            ->and($VolGRDiscount->trigger_type)->toBe('ProductCategory')
            ->and($VolGRDiscount->type)->toBe('Category Quantity Ordered Order Interval');

        $order = Order::first();

        $transaction = Transaction::where('order_id', $order->id)->first();

        expect((float)$transaction->net_amount)->toBe(350.0);

        UpdateTransaction::run($transaction, [
            'quantity_ordered' => 4,
        ]);
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(400.0);

        $todayMinus5Days = now()->subDays(5);

        UpdateCustomerLastInvoicedDate::run($order->customer, $todayMinus5Days);

        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(280.0);

    });


});
