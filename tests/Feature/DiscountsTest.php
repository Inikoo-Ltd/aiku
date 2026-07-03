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
use App\Actions\Discounts\Offer\ActivateScheduledOffers;
use App\Actions\Discounts\Offer\DeleteOffer;
use App\Actions\Discounts\Offer\FinishOffer;
use App\Actions\Discounts\Offer\GetOfferCalendarData;
use App\Actions\Discounts\Offer\Hydrators\OfferTimeSeriesHydrateNumberRecords;
use App\Actions\Discounts\Offer\HydrateOffers;
use App\Actions\Discounts\Offer\ProcessOfferTimeSeriesRecords;
use App\Actions\Discounts\Offer\Search\ReindexOfferSearch;
use App\Actions\Discounts\Offer\SetOfferAsPermanent;
use App\Actions\Discounts\Offer\StoreCustomerOffers;
use App\Actions\Discounts\Offer\StoreDiscountShipping;
use App\Actions\Discounts\Offer\StoreFirstOrderBonus;
use App\Actions\Discounts\Offer\StoreGiftsOffers;
use App\Actions\Discounts\Offer\StoreOffer;
use App\Actions\Discounts\Offer\StoreProductCategoryDiscount;
use App\Actions\Discounts\Offer\StoreShopOffer;
use App\Actions\Discounts\Offer\StoreVoucherOffers;
use App\Actions\Discounts\Offer\SuspendOffer;
use App\Actions\Discounts\Offer\UpdateOffer;
use App\Actions\Discounts\Offer\UpdateOfferAllowanceSignature;
use App\Actions\Discounts\Offer\UpdateOfferStatusFromDates;
use App\Actions\Discounts\Offer\UpdateTriggerModelOffersData;
use App\Actions\Discounts\Offer\VolGr\FinishVolumeGrOfferFromMaster;
use App\Actions\Discounts\Offer\VolGr\StoreGrAmnesty;
use App\Actions\Discounts\Offer\VolGr\StoreVolGrGift;
use App\Actions\Discounts\Offer\VolGr\StoreVolumeGRDiscount;
use App\Actions\Discounts\Offer\VolGr\UpdateVolGrGift;
use App\Actions\Discounts\Offer\VolGr\UpdateVolumeGrOfferFromMaster;
use App\Actions\Discounts\OfferAllowance\Hydrators\OfferAllowanceHydrateInvoices;
use App\Actions\Discounts\OfferAllowance\Hydrators\OfferAllowanceHydrateOrders;
use App\Actions\Discounts\OfferAllowance\StoreOfferAllowance;
use App\Actions\Discounts\OfferAllowance\UpdateOfferAllowance;
use App\Actions\Discounts\OfferCampaign\HydrateOfferCampaigns;
use App\Actions\Discounts\OfferCampaign\ProcessOfferCampaignTimeSeriesRecords;
use App\Actions\Discounts\OfferCampaign\Search\ReindexOfferCampaignSearch;
use App\Actions\Discounts\OfferCampaign\StoreProductOffers;
use App\Actions\Discounts\OfferCampaign\UpdateOfferCampaign;
use App\Actions\Discounts\TransactionHasOfferAllowance\StoreTransactionHasOfferAllowance;
use App\Actions\Discounts\TransactionHasOfferAllowance\UpdateTransactionHasOfferAllowance;
use App\Actions\Masters\MasterProductCategory\StoreMasterFamily;
use App\Actions\Masters\MasterShop\StoreMasterShop;
use App\Actions\Ordering\Order\CalculateOrderDiscounts;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\Ordering\Transaction\UpdateTransaction;
use App\Enums\Analytics\AikuSection\AikuSectionEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Discounts\Offer\OfferDurationEnum;
use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceStateEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceTargetTypeEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceType;
use App\Enums\Discounts\OfferCampaign\OfferCampaignTypeEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Analytics\AikuScopedSection;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferAllowance;
use App\Models\Discounts\OfferCampaign;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

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

    expect($this->group->discountsStats->number_offer_campaigns)->toBe(11)
        ->and($this->group->discountsStats->number_current_offer_campaigns)->toBe(10)
        ->and($this->group->discountsStats->number_offer_campaigns_offers_state_in_process)->toBe(11)
        ->and($this->organisation->discountsStats->number_offer_campaigns)->toBe(11)
        ->and($this->organisation->discountsStats->number_current_offer_campaigns)->toBe(10)
        ->and($this->organisation->discountsStats->number_offer_campaigns_offers_state_in_process)->toBe(11)
        ->and($shop->discountsStats->number_offer_campaigns)->toBe(11)
        ->and($shop->discountsStats->number_current_offer_campaigns)->toBe(10)
        ->and($shop->discountsStats->number_offer_campaigns_offers_state_in_process)->toBe(11);
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

test('set offer as permanent', function (Offer $offer) {
    $offerAllowance = $offer->offerAllowances()->first();
    $offer->update([
        'duration' => OfferDurationEnum::INTERVAL,
        'end_at'   => now()->addDays(10),
        'state'    => OfferStateEnum::IN_PROCESS,
    ]);
    $offerAllowance->update([
        'duration' => OfferDurationEnum::INTERVAL,
        'end_at'   => now()->addDays(10),
        'state'    => OfferAllowanceStateEnum::IN_PROCESS,
    ]);

    $offer = SetOfferAsPermanent::run($offer);

    expect($offer->duration)->toBe(OfferDurationEnum::PERMANENT)
        ->and($offer->end_at)->toBeNull()
        ->and($offer->state)->toBe(OfferStateEnum::ACTIVE)
        ->and($offer->status)->toBeTrue();

    $offerAllowance->refresh();
    expect($offerAllowance->duration)->toBe(OfferDurationEnum::PERMANENT)
        ->and($offerAllowance->end_at)->toBeNull()
        ->and($offerAllowance->state)->toBe(OfferAllowanceStateEnum::ACTIVE)
        ->and($offerAllowance->status)->toBeTrue();
})->depends('create offer');

test('UI Discount Dashboard', function () {
    $response = get(route('grp.org.shops.show.discounts.dashboard', [$this->organisation->slug, $this->shop->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Discounts/DiscountsDashboard')
            ->has('title')
            ->has('pageHead')
            ->has('tabs')
            ->has('breadcrumbs', 3);
    });
});

test('UI Index offer campaigns', function () {
    $response = get(route('grp.org.shops.show.discounts.campaigns.index', [$this->organisation->slug, $this->shop->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Discounts/OfferCampaigns')
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
            ->component('Org/Discounts/OrderRecursionCampaign')
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
    $this->withoutExceptionHandling();


    $response = get(route('grp.org.shops.show.discounts.offers.index', [
        $this->organisation->slug,
        $this->shop->slug
    ]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Discounts/Offers')
            ->has('title')
            ->has('pageHead')
            ->has('data')
            ->has('breadcrumbs', 3);
    });
});

test('UI Offers Insights', function () {
    $response = get(route('grp.org.shops.show.discounts.insights', [$this->organisation->slug, $this->shop->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Discounts/OffersInsights')
            ->has('title')
            ->has('pageHead')
            ->has('filters')
            ->has('offers')
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

test('activate scheduled offers', function () {
    $shop          = $this->shop;
    $offerCampaign = $shop->offerCampaigns()->first();

    $offer = StoreOffer::make()->action($offerCampaign, Offer::factory()->definition());
    $offer->duration = OfferDurationEnum::PERMANENT;
    $offer->start_at = now()->subDay();
    $offer->state    = OfferStateEnum::IN_PROCESS;
    $offer->save();
    expect($offer->state)->toBe(OfferStateEnum::IN_PROCESS);

    $this->artisan('activate:scheduled_offers')->assertExitCode(0);

    $offer->refresh();
    expect($offer->state)->toBe(OfferStateEnum::ACTIVE);

    ActivateScheduledOffers::run();
});

test('finish offer', function () {
    $shop          = $this->shop;
    $offerCampaign = $shop->offerCampaigns()->first();
    $offer         = StoreOffer::make()->action($offerCampaign, Offer::factory()->definition());

    $allowanceData = OfferAllowance::factory()->definition();
    data_set($allowanceData, 'trigger_type', 'Shop');
    data_set($allowanceData, 'trigger_id', $offer->shop->id);
    data_set($allowanceData, 'type', OfferAllowanceType::PERCENTAGE_OFF);
    StoreOfferAllowance::make()->action($offer, $allowanceData);

    $offer->update(['state' => OfferStateEnum::ACTIVE, 'status' => true]);

    $response = post(route('grp.models.offer.finish', $offer->id));
    $response->assertRedirect();

    $offer->refresh();
    expect($offer->state)->toBe(OfferStateEnum::FINISHED)
        ->and($offer->status)->toBeFalse();

    // Running again should be a no-op
    FinishOffer::run($offer);
});

test('get offer calendar data', function () {
    SeedShopOfferCampaigns::run($this->shop);
    $offerCampaign = $this->shop->offerCampaigns()->first();

    $offer = StoreOffer::make()->action($offerCampaign, array_merge(Offer::factory()->definition(), [
        'duration' => OfferDurationEnum::INTERVAL,
        'start_at' => now()->subDay()->toDateTimeString(),
        'end_at'   => now()->addDays(10)->toDateTimeString(),
        'state'    => OfferStateEnum::ACTIVE,
        'status'   => true,
    ]));

    $data = GetOfferCalendarData::run($this->organisation, (int)now()->year);
    expect($data)->toBeArray()
        ->and($data)->toHaveKey('holidayRanges')
        ->and($data)->toHaveKey('pagination');

    $dataSearch = GetOfferCalendarData::run($this->organisation, (int)now()->year, null, (int)now()->month, 10, $this->shop->id, $offer->code);
    expect($dataSearch['pagination']['total'])->toBeGreaterThanOrEqual(0);
});

test('UI show offer calendar', function () {
    SeedShopOfferCampaigns::run($this->shop);

    $response = get(route('grp.org.offer.calendar', [$this->organisation->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Discounts/OfferCalendar')
            ->has('title')
            ->has('calendar');
    });
});

test('reindex offer search and offer campaign search', function () {
    $this->artisan('search:offers')->assertExitCode(0);
    $this->artisan('search:offer_campaigns')->assertExitCode(0);

    ReindexOfferSearch::make()->handle(Offer::first());
    ReindexOfferCampaignSearch::make()->handle(OfferCampaign::first());
});

test('process and redo offer time series', function () {
    $shop          = $this->shop;
    $offerCampaign = $shop->offerCampaigns()->first();
    $offer         = StoreOffer::make()->action($offerCampaign, Offer::factory()->definition());

    ProcessOfferTimeSeriesRecords::run($offer->id, TimeSeriesFrequencyEnum::DAILY, now()->subDays(5)->toDateString(), now()->toDateString());

    $timeSeries = $offer->timeSeries()->where('frequency', TimeSeriesFrequencyEnum::DAILY->value)->first();
    expect($timeSeries)->not->toBeNull();

    OfferTimeSeriesHydrateNumberRecords::run($timeSeries->id);
    $timeSeries->refresh();
    expect($timeSeries->number_records)->toBeGreaterThanOrEqual(0);

    $offer->update(['state' => OfferStateEnum::ACTIVE]);
    $this->artisan('offers:redo_time_series', ['--from' => now()->subDays(5)->toDateString(), '--to' => now()->toDateString()])->assertExitCode(0);
});

test('process and redo offer campaign time series', function () {
    $offerCampaign = $this->shop->offerCampaigns()->first();

    ProcessOfferCampaignTimeSeriesRecords::run($offerCampaign->id, TimeSeriesFrequencyEnum::DAILY, now()->subDays(5)->toDateString(), now()->toDateString());

    $timeSeries = $offerCampaign->timeSeries()->where('frequency', TimeSeriesFrequencyEnum::DAILY->value)->first();
    expect($timeSeries)->not->toBeNull();

    $this->artisan('offer-campaigns:redo_time_series', ['--from' => now()->subDays(5)->toDateString(), '--to' => now()->toDateString()])->assertExitCode(0);
});

test('offer allowance hydrate invoices and orders', function (OfferAllowance $offerAllowance) {
    OfferAllowanceHydrateInvoices::run($offerAllowance);
    OfferAllowanceHydrateOrders::run($offerAllowance);

    $offerAllowance->stats->refresh();
    expect($offerAllowance->stats->number_invoices)->toBeGreaterThanOrEqual(0)
        ->and($offerAllowance->stats->number_orders)->toBeGreaterThanOrEqual(0);
})->depends('create offer allowance');

test('store and update transaction has offer allowance', function () {
    $shop          = $this->shop;
    $customer      = $this->customer;
    $offerCampaign = $shop->offerCampaigns()->first();

    $offer = StoreOffer::make()->action($offerCampaign, Offer::factory()->definition());

    $allowanceData = OfferAllowance::factory()->definition();
    data_set($allowanceData, 'trigger_type', 'Shop');
    data_set($allowanceData, 'trigger_id', $offer->shop->id);
    $offerAllowance = StoreOfferAllowance::make()->action($offer, $allowanceData);

    $order       = StoreOrder::make()->action($customer, []);
    $product     = $shop->products()->first();
    $transaction = StoreTransaction::make()->action($order, $product->historicAsset, ['quantity_ordered' => 1]);

    $transactionHasOfferAllowance = StoreTransactionHasOfferAllowance::make()->action($transaction, $offerAllowance, [
        'discounted_amount' => 10,
    ]);
    $this->assertModelExists($transactionHasOfferAllowance);

    $transactionHasOfferAllowance = UpdateTransactionHasOfferAllowance::make()->action($transactionHasOfferAllowance, [
        'discounted_amount' => 20,
    ]);
    expect((float)$transactionHasOfferAllowance->discounted_amount)->toBe(20.0);

    // Order would otherwise stay in the basket and keep getting re-discounted by
    // every later offer activated on this shop, polluting other tests.
    DB::table('transaction_has_offer_allowances')->where('order_id', $order->id)->delete();
    $transaction->delete();
    $order->delete();
});

test('check voucher code existence', function () {
    $shop          = $this->shop;
    if (!$shop->offerCampaigns()->where('type', OfferCampaignTypeEnum::VOUCHERS)->exists()) {
        SeedShopOfferCampaigns::run($shop);
    }
    $offerCampaign = $shop->offerCampaigns()->where('type', OfferCampaignTypeEnum::VOUCHERS)->first();

    $offer = StoreVoucherOffers::make()->handle($shop, [
        'voucher'            => 'TESTVOUCHER',
        'name'               => 'Test Voucher',
        'offer_amount'       => 0,
        'can_customer_reuse' => false,
        'start_at'           => now()->toDateTimeString(),
        'end_at'             => now()->addDays(10)->toDateTimeString(),
        'percentage_off'     => 10,
        'target_type'        => 'shop',
        'target_id'          => $shop->id,
    ]);

    expect($offer)->toBeInstanceOf(Offer::class)
        ->and($offer->voucher)->toBe('testvoucher');

    $response = $this->getJson(route('grp.org.shops.show.discounts.campaigns.check_voucher', [
        $this->organisation->slug,
        $shop->slug,
        $offerCampaign->slug
    ]).'?code=TESTVOUCHER');

    $response->assertJson(['exists' => true]);
});

test('store customer offers', function () {
    $shop = $this->shop;
    if (!$shop->offerCampaigns()->where('type', OfferCampaignTypeEnum::CUSTOMER_OFFERS)->exists()) {
        SeedShopOfferCampaigns::run($shop);
    }

    $offer = StoreCustomerOffers::make()->handle($shop, [
        'customer_id'       => $this->customer->id,
        'min_order_amount'  => 0,
        'percentage_off'    => 15,
        'duration'          => 'permanent',
        'start_at'          => now()->toDateTimeString(),
        'target_type'       => 'shop',
        'target_id'         => $shop->id,
    ]);

    expect($offer)->toBeInstanceOf(Offer::class)
        ->and($offer->trigger_type)->toBe('Customer');
});

test('store shop offer', function () {
    $shop = $this->shop;
    if (!$shop->offerCampaigns()->where('type', OfferCampaignTypeEnum::SHOP_OFFERS)->exists()) {
        SeedShopOfferCampaigns::run($shop);
    }

    $offer = StoreShopOffer::make()->handle($shop, [
        'type'                       => 'quantity',
        'duration'                   => 'permanent',
        'trigger_data_item_quantity' => 2,
        'start_at'                   => now()->toDateTimeString(),
        'percentage_off'             => 12,
    ]);

    $offer->refresh();
    expect($offer)->toBeInstanceOf(Offer::class)
        ->and($offer->type)->toBe('Shop Ordered');
});

test('store discount shipping', function () {
    $shop = $this->shop;
    if (!$shop->offerCampaigns()->where('type', OfferCampaignTypeEnum::SHIPPING)->exists()) {
        SeedShopOfferCampaigns::run($shop);
    }

    $offer = StoreDiscountShipping::make()->handle($shop, [
        'min_order_amount' => 50,
        'start_at'         => now()->toDateTimeString(),
        'end_at'           => now()->addDays(10)->toDateTimeString(),
    ]);

    $offer->refresh();
    expect($offer)->toBeInstanceOf(Offer::class)
        ->and($offer->type)->toBe('Discount Shipping');
});

test('store gifts offers', function () {
    $shop = $this->shop;
    if (!$shop->offerCampaigns()->where('type', OfferCampaignTypeEnum::GIFT)->exists()) {
        SeedShopOfferCampaigns::run($shop);
    }

    $offer = StoreGiftsOffers::make()->handle($shop, [
        'name'             => 'Free Gift',
        'product_id'       => $this->product->id,
        'duration'         => 'permanent',
        'min_order_amount' => 50,
        'quantity'         => 1,
        'start_at'         => now()->toDateTimeString(),
    ]);

    expect($offer)->toBeInstanceOf(Offer::class)
        ->and($offer->type)->toBe('Gift');

    return $offer;
});

test('store product offers no-op', function () {
    StoreProductOffers::make()->handle([]);
    expect(true)->toBeTrue();
});

test('store and update vol gr gift', function () {
    $shop = $this->shop;
    if (!$shop->offerCampaigns()->where('type', OfferCampaignTypeEnum::VOLUME_DISCOUNT)->exists()) {
        SeedShopOfferCampaigns::run($shop);
    }
    $offerCampaign = $shop->offerCampaigns()->where('type', OfferCampaignTypeEnum::VOLUME_DISCOUNT)->first();

    $offer = StoreVolGrGift::make()->handle($offerCampaign, [
        'amount'   => 100,
        'products' => [
            ['id' => $this->product->id, 'default' => true],
        ],
    ]);

    expect($offer)->toBeInstanceOf(Offer::class)
        ->and($offer->type)->toBe('VolGr Gift');

    $offer = UpdateVolGrGift::make()->handle($offer, [
        'amount'   => 200,
        'products' => [
            ['id' => $this->product->id, 'default' => true],
        ],
    ]);

    expect($offer)->toBeInstanceOf(Offer::class);

    $response = get(route('grp.org.shops.show.discounts.campaigns.create_vol_gr_gift', [
        $this->organisation->slug,
        $shop->slug,
        $offerCampaign->slug,
    ]));
    $response->assertOk();

    $response = get(route('grp.org.shops.show.discounts.campaigns.edit_vol_gr_gift', [
        $this->organisation->slug,
        $shop->slug,
        $offerCampaign->slug,
    ]));
    $response->assertOk();

    $response = get(route('grp.org.shops.show.discounts.campaigns.offer.edit_vol_gr_gift', [
        $this->organisation->slug,
        $shop->slug,
        $offerCampaign->slug,
        $offer->slug,
    ]));
    $response->assertOk();
});

test('store gr amnesty', function () {
    $shop = $this->shop;
    if (!$shop->offerCampaigns()->where('type', OfferCampaignTypeEnum::VOLUME_DISCOUNT)->exists()) {
        SeedShopOfferCampaigns::run($shop);
    }
    $offerCampaign = $shop->offerCampaigns()->where('type', OfferCampaignTypeEnum::VOLUME_DISCOUNT)->first();

    $offer = StoreGrAmnesty::make()->handle($offerCampaign, [
        'start_at' => now()->toDateTimeString(),
        'end_at'   => now()->addDays(7)->toDateTimeString(),
    ]);

    expect($offer)->toBeInstanceOf(Offer::class)
        ->and($offer->type)->toBe('GR Amnesty');

    $response = get(route('grp.org.shops.show.discounts.campaigns.create_gr_amnesty_offer', [
        $this->organisation->slug,
        $shop->slug,
        $offerCampaign->slug,
    ]));
    $response->assertOk();

    $response = get(route('grp.org.shops.show.discounts.campaigns.amnesty.show', [
        $this->organisation->slug,
        $shop->slug,
        $offerCampaign->slug,
        $offer->slug,
    ]));
    $response->assertOk();

    $response = get(route('grp.org.shops.show.discounts.campaigns.amnesty.edit', [
        $this->organisation->slug,
        $shop->slug,
        $offerCampaign->slug,
        $offer->slug,
    ]));
    $response->assertOk();
});

test('update trigger model offers data', function () {
    $shop          = $this->shop;
    $offerCampaign = $shop->offerCampaigns()->first();
    $offer         = StoreOffer::make()->action($offerCampaign, Offer::factory()->definition());

    UpdateTriggerModelOffersData::run($offer);

    $this->artisan('discounts:offer:update-trigger-model-offers-data', ['type' => 'offer', 'model' => $offer->slug])->assertExitCode(0);
    $this->artisan('discounts:offer:update-trigger-model-offers-data', ['type' => 'shop', 'model' => $shop->slug])->assertExitCode(0);
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

    $offerData             = Offer::factory()->definition();
    $offerData['duration'] = OfferDurationEnum::PERMANENT;
    $offerData['start_at'] = now();


    $offer = StoreOffer::make()->action($offerCampaign, $offerData);
    UpdateOfferStatusFromDates::run($offer);


    $allowanceData = OfferAllowance::factory()->definition();
    data_set($allowanceData, 'type', OfferAllowanceType::PERCENTAGE_OFF);
    data_set($allowanceData, 'target_type', OfferAllowanceTargetTypeEnum::ALL_PRODUCTS_IN_ORDER);
    data_set($allowanceData, 'data.percentage_off', 10);


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
        'type'            => ProductCategoryTypeEnum::FAMILY->value,
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
            'type'                       => 'quantity',
            'trigger_data_item_quantity' => 2,
            'percentage_off'             => .25,
            'duration'                   => 'interval',
            'start_at'                   => now()->addDays(7)->toDateTimeString(),
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

test('UI show offer campaign for each campaign type', function () {
    SeedShopOfferCampaigns::run($this->shop);

    foreach ($this->shop->offerCampaigns()->get() as $offerCampaign) {
        $response = get(route('grp.org.shops.show.discounts.campaigns.show', [
            $this->organisation->slug,
            $this->shop->slug,
            $offerCampaign->slug
        ]));

        $response->assertOk();
    }
});

test('UI create offer', function () {
    $offerCampaign = $this->shop->offerCampaigns()->first();

    $response = get(route('grp.org.shops.show.discounts.offers.create', [
        $this->organisation->slug,
        $this->shop->slug,
    ]).'?offerCampaign='.$offerCampaign->slug);

    $response->assertInertia(function (AssertableInertia $page) {
        $page->component('CreateModel')
            ->has('title')
            ->has('formData');
    });
});

test('UI index customers, invoices and orders in offer campaign', function () {
    $offerCampaign = $this->shop->offerCampaigns()->first();

    foreach (
        [
            'grp.org.shops.show.discounts.campaigns.customers',
            'grp.org.shops.show.discounts.campaigns.totals.customers',
            'grp.org.shops.show.discounts.campaigns.invoices',
            'grp.org.shops.show.discounts.campaigns.totals.invoices',
            'grp.org.shops.show.discounts.campaigns.orders',
            'grp.org.shops.show.discounts.campaigns.totals.orders',
        ] as $routeName
    ) {
        $response = get(route($routeName, [
            $this->organisation->slug,
            $this->shop->slug,
            $offerCampaign->slug,
        ]));

        $response->assertOk();
    }
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

        $order = Order::first();

        $transaction = DB::table('transactions')->where('order_id', $order->id)->first();

        expect((float)$transaction->net_amount)->toBe(180.0);


        $product = $this->shop->products()->first();

        $categoryDiscount = StoreProductCategoryDiscount::make()->action(
            $product->family,
            [
                'trigger_data_item_quantity' => 1,
                'percentage_off'             => 0.60,
                'type'                       => 'quantity',
                'duration'                   => 'interval',
                'start_at'                   => now(),
                'end_at'                     => now()->addDays(14)->toDateTimeString(),
            ]
        );

        CalculateOrderDiscounts::run($order);

        expect($categoryDiscount)->toBeInstanceOf(Offer::class);
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
    })->todo();

    test('CalculateOrderDiscounts: Category Ordered trigger item quantity', function () {
        $product = $this->shop->products()->first();

        $categoryDiscount = StoreProductCategoryDiscount::make()->action(
            $product->family,
            [
                'trigger_data_item_quantity' => 5,
                'percentage_off'             => 0.30,
                'type'                       => 'quantity',
                'duration'                   => 'interval',
                'start_at'                   => now(),
                'end_at'                     => now()->addDays(14)->toDateTimeString(),
            ]
        );

        $categoryDiscount->refresh();

        expect($categoryDiscount)->toBeInstanceOf(Offer::class)
            ->and($categoryDiscount->status)->toBeTrue()
            ->and($categoryDiscount->trigger_type)->toBe('ProductCategory')
            ->and($categoryDiscount->type)->toBe('Category Quantity Ordered');

        $order = Order::first();

        $transaction = Transaction::where('order_id', $order->id)->first();
        expect((float)$transaction->net_amount)->toBe(80.0);

        UpdateTransaction::run($transaction, [
            'quantity_ordered' => 5,
        ]);
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(200.0);

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

        expect((float)$transaction->net_amount)->toBe(200.0);

        UpdateTransaction::run($transaction, [
            'quantity_ordered' => 4,
        ]);
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(160.0);

        $todayMinus5Days = now()->subDays(5);

        UpdateCustomerLastInvoicedDate::run($order->customer, $todayMinus5Days);

        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(160.0);
    });
});

test('finish and update volume gr offer from master', function () {
    $shop = $this->shop;
    if (!$shop->offerCampaigns()->where('type', OfferCampaignTypeEnum::VOLUME_DISCOUNT)->exists()) {
        SeedShopOfferCampaigns::run($shop);
    }

    $masterShop = StoreMasterShop::make()->action(group(), [
        'type' => \App\Enums\Catalogue\Shop\ShopTypeEnum::B2B,
        'code' => 'MSH-'.uniqid(),
        'name' => 'Test Master Shop',
    ]);
    $masterShop->update(['gold_reward_eligible' => true]);

    $masterProductCategory = StoreMasterFamily::make()->handle($masterShop, [
        'code' => 'MASTER-GR-'.uniqid(),
        'name' => 'Master GR Family',
    ]);
    $masterProductCategory->update([
        'gr_vol_discount_quantity'   => 4,
        'gr_vol_discount_percentage' => 25,
    ]);

    /** @var ProductCategory $category */
    $category = ProductCategory::factory()->create([
        'shop_id'                    => $shop->id,
        'organisation_id'            => $shop->organisation_id,
        'group_id'                   => $shop->group_id,
        'code'                       => 'MASTER-GR',
        'type'                       => ProductCategoryTypeEnum::FAMILY->value,
        'follow_master_gr'           => true,
        'master_product_category_id' => $masterProductCategory->id,
    ]);

    $result = UpdateVolumeGrOfferFromMaster::run($masterProductCategory);
    expect($result['success'])->toBeTrue()
        ->and($result['updated_offers'])->toBe(1);

    $category->refresh();
    expect($category->has_gr_vol_discount)->toBeTrue();

    // Run again to cover the "offer already exists" branch
    $result = UpdateVolumeGrOfferFromMaster::run($masterProductCategory);
    expect($result['success'])->toBeTrue();

    FinishVolumeGrOfferFromMaster::run($masterProductCategory);

    $category->refresh();
    expect($category->has_gr_vol_discount)->toBeFalse();
});
