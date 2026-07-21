<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 26 Nov 2024 21:37:12 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOffersData;
use App\Actions\Catalogue\Shop\Seeders\SeedShopOfferCampaigns;
use App\Actions\CRM\Customer\UpdateCustomerLastInvoicedDate;
use App\Actions\Discounts\Offer\ActivateOffer;
use App\Actions\Discounts\Offer\ActivateScheduledOffers;
use App\Actions\Discounts\Offer\DeleteOffer;
use App\Actions\Discounts\Offer\FinishOffer;
use App\Actions\Discounts\Offer\GetOfferCalendarData;
use App\Actions\Discounts\Offer\HydrateOffers;
use App\Actions\Discounts\Offer\Hydrators\OfferTimeSeriesHydrateNumberRecords;
use App\Actions\Discounts\Offer\ProcessOfferTimeSeriesRecords;
use App\Actions\Discounts\Offer\Search\ReindexOfferSearch;
use App\Actions\Discounts\Offer\SetOfferAsPermanent;
use App\Actions\Discounts\Offer\StoreBuyXGetCheapestFree;
use App\Actions\Discounts\Offer\StoreCustomerOffers;
use App\Actions\Discounts\Offer\StoreDiscountShipping;
use App\Actions\Discounts\Offer\StoreFirstOrderBonus;
use App\Actions\Discounts\Offer\StoreGiftsOffers;
use App\Actions\Discounts\Offer\StoreOffer;
use App\Actions\Discounts\Offer\StoreProductCategoryDiscount;
use App\Actions\Discounts\Offer\StoreProductDiscount;
use App\Actions\Discounts\Offer\StoreProductStepDiscount;
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
use App\Actions\Billables\ShippingZone\StoreShippingZone;
use App\Actions\Billables\ShippingZoneSchema\StoreShippingZoneSchema;
use App\Actions\Catalogue\Collection\AttachModelToCollection;
use App\Actions\Catalogue\Collection\StoreCollection;
use App\Actions\Catalogue\Product\StoreProduct;
use App\Actions\Catalogue\ProductCategory\StoreProductCategory;
use App\Actions\Masters\MasterProductCategory\StoreMasterFamily;
use App\Actions\Ordering\Order\UpdateState\SubmitOrder;
use App\Actions\Masters\MasterShop\StoreMasterShop;
use App\Actions\Ordering\Order\AddVoucherToOrder;
use App\Actions\Ordering\Order\CalculateOrderDiscounts;
use App\Actions\Ordering\Order\Hydrators\OrderHydrateTransactions;
use App\Actions\Ordering\Order\CalculateOrderShipping;
use App\Actions\Ordering\Order\CalculateOrderTotalAmounts;
use App\Actions\Ordering\Order\RemoveVoucherFromOrder;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Transaction\DeleteTransaction;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\Ordering\Transaction\UpdateTransaction;
use App\Actions\Ordering\Transaction\UpdateTransactionDiscretionaryDiscount;
use App\Actions\SysAdmin\GetSectionRoute;
use App\Enums\Analytics\AikuSection\AikuSectionEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Discounts\Offer\OfferDurationEnum;
use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceStateEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceTargetTypeEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceType;
use App\Enums\Discounts\OfferCampaign\OfferCampaignTypeEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\Ordering\Order\OrderShippingEngineEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Analytics\AikuScopedSection;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferAllowance;
use App\Models\Discounts\OfferCampaign;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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

    $offer           = StoreOffer::make()->action($offerCampaign, Offer::factory()->definition());
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
    $shop = $this->shop;
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
        'allowance_type'     => 'percentage_off',
        'target_type'        => 'shop',
        'target_id'          => $shop->id,
    ]);

    expect($offer)->toBeInstanceOf(Offer::class)
        ->and($offer->voucher)->toBe('testvoucher');

    $response = $this->getJson(
        route('grp.org.shops.show.discounts.campaigns.check_voucher', [
            $this->organisation->slug,
            $shop->slug,
            $offerCampaign->slug
        ]).'?code=TESTVOUCHER'
    );

    $response->assertJson(['exists' => true]);
});

test('store customer offers', function () {
    $shop = $this->shop;
    if (!$shop->offerCampaigns()->where('type', OfferCampaignTypeEnum::CUSTOMER_OFFERS)->exists()) {
        SeedShopOfferCampaigns::run($shop);
    }

    $offer = StoreCustomerOffers::make()->handle($shop, [
        'customer_id'      => $this->customer->id,
        'min_order_amount' => 0,
        'percentage_off'   => 15,
        'duration'         => 'permanent',
        'start_at'         => now()->toDateTimeString(),
        'target_type'      => 'shop',
        'target_id'        => $shop->id,
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

test('check amount and order number with empty trigger data', function () {
    $order = StoreOrder::make()->action($this->customer, []);

    $offerData = (object)['trigger_data' => '{}'];

    list($passAmount, $passOrderNumber, $metadata) = CalculateOrderDiscounts::make()->checkAmountAndOrderNumber($order, $offerData);

    expect($passAmount)->toBeTrue()
        ->and($passOrderNumber)->toBeFalse()
        ->and($metadata)->toBe([]);
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

    $response = get(
        route('grp.org.shops.show.discounts.offers.create', [
            $this->organisation->slug,
            $this->shop->slug,
        ]).'?offerCampaign='.$offerCampaign->slug
    );

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

    test('UpdateTransaction applies current discount factor to net amount before discounts are recalculated', function () {
        $order       = Order::first();
        $transaction = Transaction::where('order_id', $order->id)->first();

        expect((float)$transaction->current_discount_factor)->toBe(0.4);

        CalculateOrderTotalAmounts::mock()->shouldIgnoreMissing();

        UpdateTransaction::run($transaction, [
            'quantity_ordered' => 3,
        ]);

        $transaction->refresh();
        expect((float)$transaction->gross_amount)->toBe(300.0)
            ->and((float)$transaction->net_amount)->toBe(120.0);
    });

    test('the highest active offer percentage wins and is recorded consistently', function () {
        $order = Order::first();
        CalculateOrderDiscounts::run($order);
        $order->refresh();

        $transaction   = Transaction::where('order_id', $order->id)->first();
        $categoryOffer = Offer::where('shop_id', $order->shop_id)->where('type', 'Category Ordered')->orderBy('id')->first();

        expect((float)$transaction->gross_amount)->toBe(300.0)
            ->and((float)$transaction->net_amount)->toBe(120.0)
            ->and((float)$transaction->current_discount_factor)->toEqualWithDelta(0.4, 0.00001)
            ->and(Arr::get($transaction->offers_data, 'o.o'))->toBe($categoryOffer->id)
            ->and(Arr::get($transaction->offers_data, 'o.t'))->toBe('percentage');

        $pivots = DB::table('transaction_has_offer_allowances')->where('order_id', $order->id)->get();
        expect($pivots)->toHaveCount(1)
            ->and($pivots->first()->offer_id)->toBe($categoryOffer->id)
            ->and((float)$pivots->first()->discounted_percentage)->toEqualWithDelta(0.6, 0.00001)
            ->and((float)$pivots->first()->discounted_amount)->toBe(180.0);
    });

    test('suspending the winning offer falls back to next best: Vol/GR interval sub-trigger', function () {
        $order         = Order::first();
        $transaction   = Transaction::where('order_id', $order->id)->first();
        $categoryOffer = Offer::where('shop_id', $order->shop_id)->where('type', 'Category Ordered')->orderBy('id')->first();
        $volGrOffer    = Offer::where('shop_id', $order->shop_id)->where('type', 'Category Quantity Ordered Order Interval')->first();

        DB::table('customers')->where('id', $order->customer_id)->update(['last_invoiced_at' => now()->subDays(5)]);
        Cache::flush();
        SuspendOffer::run($categoryOffer);
        CalculateOrderDiscounts::run($order);

        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(210.0)
            ->and((float)$transaction->current_discount_factor)->toEqualWithDelta(0.7, 0.00001)
            ->and(Arr::get($transaction->offers_data, 'o.o'))->toBe($volGrOffer->id)
            ->and(Arr::get($transaction->offers_data, 'o.st'))->toBe('i');
    });

    test('Vol/GR quantity sub-trigger and first order bonus application', function () {
        $order       = Order::first();
        $transaction = Transaction::where('order_id', $order->id)->first();
        $fobOffer    = Offer::where('shop_id', $order->shop_id)->where('type', 'Amount AND Order Number')->first();
        $volGrOffer  = Offer::where('shop_id', $order->shop_id)->where('type', 'Category Quantity Ordered Order Interval')->first();

        DB::table('customers')->where('id', $order->customer_id)->update(['last_invoiced_at' => now()->subDays(400)]);
        Cache::flush();
        CalculateOrderDiscounts::run($order);
        $order->refresh();
        $transaction->refresh();

        expect((float)$transaction->net_amount)->toBe(270.0)
            ->and((float)$transaction->current_discount_factor)->toEqualWithDelta(0.9, 0.00001)
            ->and(Arr::get($transaction->offers_data, 'o.o'))->toBe($fobOffer->id)
            ->and(Arr::get($transaction->offers_data, 'o.st'))->toBe('fob');

        $fobMeter = collect($order->offer_meters)->firstWhere('offer_id', $fobOffer->id);
        expect($fobMeter)->not->toBeNull()
            ->and($fobMeter['is_gift'])->toBeFalse()
            ->and($fobMeter['metadata']['target'])->toEqual(150);

        UpdateTransaction::run($transaction, ['quantity_ordered' => 5]);
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(350.0)
            ->and((float)$transaction->current_discount_factor)->toEqualWithDelta(0.7, 0.00001)
            ->and(Arr::get($transaction->offers_data, 'o.o'))->toBe($volGrOffer->id)
            ->and(Arr::get($transaction->offers_data, 'o.st'))->toBe('q');
    });

    test('Vol/GR amnesty sub-trigger', function () {
        $order       = Order::first();
        $transaction = Transaction::where('order_id', $order->id)->first();
        $fobOffer    = Offer::where('shop_id', $order->shop_id)->where('type', 'Amount AND Order Number')->first();
        $volGrOffer  = Offer::where('shop_id', $order->shop_id)->where('type', 'Category Quantity Ordered Order Interval')->first();

        UpdateTransaction::run($transaction, ['quantity_ordered' => 3]);
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(270.0)
            ->and(Arr::get($transaction->offers_data, 'o.st'))->toBe('fob');

        $volGrCampaign = OfferCampaign::where('shop_id', $order->shop_id)->where('type', OfferCampaignTypeEnum::VOLUME_DISCOUNT)->first();
        $amnestyOffer  = StoreGrAmnesty::run($volGrCampaign, [
            'start_at' => now()->subDay()->toDateString(),
            'end_at'   => now()->addDay()->toDateString(),
        ]);
        ShopHydrateOffersData::run($order->shop_id);
        CalculateOrderDiscounts::run($order);

        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(210.0)
            ->and(Arr::get($transaction->offers_data, 'o.o'))->toBe($volGrOffer->id)
            ->and(Arr::get($transaction->offers_data, 'o.st'))->toBe('a')
            ->and(Arr::get($transaction->offers_data, 'o.sto'))->toBe($amnestyOffer->id);

        SuspendOffer::run($amnestyOffer);
        SuspendOffer::run($volGrOffer);
        ShopHydrateOffersData::run($order->shop_id);
        Cache::flush();
        CalculateOrderDiscounts::run($order);
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(270.0)
            ->and(Arr::get($transaction->offers_data, 'o.o'))->toBe($fobOffer->id);
    });

    test('CalculateOrderDiscounts: buy X get cheapest free family offer', function () {
        $order       = Order::first();
        $transaction = Transaction::where('order_id', $order->id)->first();

        expect((float)$transaction->net_amount)->toBe(270.0)
            ->and((int)$transaction->quantity_ordered)->toBe(3);

        $offer = StoreBuyXGetCheapestFree::make()->action(
            $this->product->family,
            [
                'trigger_data_item_quantity' => 3,
                'free_quantity'              => 1,
                'duration'                   => 'interval',
                'start_at'                   => now(),
                'end_at'                     => now()->addDays(14)->toDateTimeString(),
            ]
        );
        $offer->refresh();

        expect($offer)->toBeInstanceOf(Offer::class)
            ->and($offer->status)->toBeTrue()
            ->and($offer->type)->toBe('Category For Every Quantity Ordered')
            ->and($offer->offerAllowances->first()->target_type)->toBe(OfferAllowanceTargetTypeEnum::CHEAPEST_PRODUCTS_IN_PRODUCT_CATEGORY)
            ->and($offer->offerAllowances->first()->type)->toBe(OfferAllowanceType::FREE_ITEMS);

        CalculateOrderDiscounts::run($order);
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(200.0)
            ->and(Arr::get($transaction->offers_data, 'o.o'))->toBe($offer->id)
            ->and(Arr::get($transaction->offers_data, 'o.t'))->toBe('free_items')
            ->and(Arr::get($transaction->offers_data, 'o.nf'))->toBe(1)
            ->and((float)Arr::get($transaction->offers_data, 'o.f'))->toBe(100.0);

        $pivot = DB::table('transaction_has_offer_allowances')->where('transaction_id', $transaction->id)->where('offer_id', $offer->id)->first();
        expect($pivot)->not->toBeNull()
            ->and((int)$pivot->number_of_free_items)->toBe(1)
            ->and((float)$pivot->free_items_value)->toBe(100.0);

        UpdateTransaction::run($transaction, ['quantity_ordered' => 2]);
        $transaction->refresh();
        $order->refresh();
        expect((float)$transaction->net_amount)->toBe(180.0)
            ->and(Arr::get($order->offer_meters, "$offer->allowance_signature.metadata.current"))->toBe(2)
            ->and(Arr::get($order->offer_meters, "$offer->allowance_signature.metadata.target"))->toBe(3);

        SuspendOffer::run($offer);
        UpdateTransaction::run($transaction, ['quantity_ordered' => 3]);
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(270.0);
    });

    test('CalculateOrderDiscounts: buy X get cheapest free product offer', function () {
        $order       = Order::first();
        $transaction = Transaction::where('order_id', $order->id)->first();

        expect((float)$transaction->net_amount)->toBe(270.0)
            ->and((int)$transaction->quantity_ordered)->toBe(3);

        $offer = StoreBuyXGetCheapestFree::make()->actionForProduct(
            $this->product,
            [
                'trigger_data_item_quantity' => 3,
                'free_quantity'              => 1,
                'duration'                   => 'interval',
                'start_at'                   => now(),
                'end_at'                     => now()->addDays(14)->toDateTimeString(),
            ]
        );
        $offer->refresh();

        expect($offer)->toBeInstanceOf(Offer::class)
            ->and($offer->status)->toBeTrue()
            ->and($offer->trigger_type)->toBe('Product')
            ->and($offer->type)->toBe('Product For Every Quantity Ordered')
            ->and($offer->offerAllowances->first()->target_type)->toBe(OfferAllowanceTargetTypeEnum::PRODUCT)
            ->and($offer->offerAllowances->first()->type)->toBe(OfferAllowanceType::FREE_ITEMS);

        CalculateOrderDiscounts::run($order);
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(200.0)
            ->and(Arr::get($transaction->offers_data, 'o.o'))->toBe($offer->id)
            ->and(Arr::get($transaction->offers_data, 'o.nf'))->toBe(1)
            ->and((float)Arr::get($transaction->offers_data, 'o.f'))->toBe(100.0);

        SuspendOffer::run($offer);
        CalculateOrderDiscounts::run($order->refresh());
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(270.0);
    });

    test('CalculateOrderDiscounts: product percentage discount quantity and amount triggers', function () {
        $order       = Order::first();
        $transaction = Transaction::where('order_id', $order->id)->first();

        expect((float)$transaction->net_amount)->toBe(270.0)
            ->and((int)$transaction->quantity_ordered)->toBe(3);

        $offer = StoreProductDiscount::make()->action(
            $this->product,
            [
                'type'                       => 'quantity',
                'trigger_data_item_quantity' => 3,
                'percentage_off'             => 0.20,
                'duration'                   => 'interval',
                'start_at'                   => now(),
                'end_at'                     => now()->addDays(14)->toDateTimeString(),
            ]
        );
        $offer->refresh();

        expect($offer)->toBeInstanceOf(Offer::class)
            ->and($offer->status)->toBeTrue()
            ->and($offer->trigger_type)->toBe('Product')
            ->and($offer->type)->toBe('Product Quantity Ordered')
            ->and($offer->offerAllowances->first()->target_type)->toBe(OfferAllowanceTargetTypeEnum::PRODUCT)
            ->and($offer->offerAllowances->first()->type)->toBe(OfferAllowanceType::PERCENTAGE_OFF);

        CalculateOrderDiscounts::run($order);
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(240.0)
            ->and(Arr::get($transaction->offers_data, 'o.o'))->toBe($offer->id);

        UpdateTransaction::run($transaction, ['quantity_ordered' => 2]);
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(180.0);

        SuspendOffer::run($offer);

        $amountOffer = StoreProductDiscount::make()->action(
            $this->product,
            [
                'type'                     => 'amount',
                'trigger_data_item_amount' => 250,
                'percentage_off'           => 0.20,
                'duration'                 => 'interval',
                'start_at'                 => now(),
                'end_at'                   => now()->addDays(14)->toDateTimeString(),
            ]
        );
        expect($amountOffer->refresh()->type)->toBe('Product Amount Ordered');

        CalculateOrderDiscounts::run($order->refresh());
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(180.0);

        UpdateTransaction::run($transaction, ['quantity_ordered' => 3]);
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(240.0)
            ->and(Arr::get($transaction->offers_data, 'o.o'))->toBe($amountOffer->id);

        SuspendOffer::run($amountOffer);
        CalculateOrderDiscounts::run($order->refresh());
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(270.0);
    });

    test('CalculateOrderDiscounts: product step discount', function () {
        $order       = Order::first();
        $transaction = Transaction::where('order_id', $order->id)->first();

        expect((float)$transaction->net_amount)->toBe(270.0)
            ->and((int)$transaction->quantity_ordered)->toBe(3);

        $offer = StoreProductStepDiscount::make()->action(
            $this->product,
            [
                'steps'    => [
                    ['min_quantity' => 5, 'percentage_off' => 0.25],
                    ['min_quantity' => 1, 'percentage_off' => 0.15],
                ],
                'duration' => 'interval',
                'start_at' => now(),
                'end_at'   => now()->addDays(14)->toDateTimeString(),
            ]
        );
        $offer->refresh();

        expect($offer)->toBeInstanceOf(Offer::class)
            ->and($offer->status)->toBeTrue()
            ->and($offer->trigger_type)->toBe('Product')
            ->and($offer->type)->toBe('Product Quantity Ordered')
            ->and($offer->allowance_signature)->toBe('product:'.$this->product->id.':percentage_off:1-0.15,5-0.25')
            ->and(Arr::get($offer->offerAllowances->first()->data, 'steps.0.min_quantity'))->toBe(1);

        CalculateOrderDiscounts::run($order);
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(255.0)
            ->and(Arr::get($transaction->offers_data, 'o.o'))->toBe($offer->id);

        UpdateTransaction::run($transaction, ['quantity_ordered' => 5]);
        $transaction->refresh();
        expect((float)$transaction->gross_amount)->toBe(500.0)
            ->and((float)$transaction->net_amount)->toBe(375.0);

        SuspendOffer::run($offer);
        UpdateTransaction::run($transaction, ['quantity_ordered' => 3]);
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(270.0);
    });

    test('SubmitOrder gift offers: buy X of product get different product free', function () {
        $order       = Order::first();
        $transaction = Transaction::where('order_id', $order->id)->first();

        expect((int)$transaction->quantity_ordered)->toBe(3);

        $giftProductData = array_merge(
            Product::factory()->definition(),
            [
                'code'        => 'GIFT-PROD',
                'price'       => 50,
                'trade_units' => [
                    [
                        'id'       => $this->tradeUnit[0]->id ?? $this->tradeUnit->id,
                        'quantity' => 1
                    ]
                ],
            ]
        );
        $giftProduct     = StoreProduct::make()->action($this->product->family, $giftProductData);

        $offer = StoreBuyXGetCheapestFree::make()->actionForProduct(
            $this->product,
            [
                'trigger_data_item_quantity' => 3,
                'free_quantity'              => 1,
                'free_product_id'            => $giftProduct->id,
                'duration'                   => 'interval',
                'start_at'                   => now(),
                'end_at'                     => now()->addDays(14)->toDateTimeString(),
            ]
        );
        $offer->refresh();

        expect($offer)->toBeInstanceOf(Offer::class)
            ->and($offer->status)->toBeTrue()
            ->and($offer->type)->toBe('Gift')
            ->and($offer->trigger_type)->toBe('Product')
            ->and($offer->trigger_id)->toBe($this->product->id)
            ->and($offer->offerAllowances->first()->type)->toBe(OfferAllowanceType::GIFT);

        SubmitOrder::make()->processGiftOffers($order);

        $giftTransaction = Transaction::where('order_id', $order->id)->where('is_gift', true)->where('model_id', $giftProduct->id)->first();
        expect($giftTransaction)->not->toBeNull()
            ->and((float)$giftTransaction->quantity_bonus)->toBe(1.0)
            ->and(Arr::get($giftTransaction->offers_data, 'o.o'))->toBe($offer->id);

        $giftTransaction->forceDelete();
        SuspendOffer::run($offer);
    });

    test('CalculateOrderDiscounts: mix and match cheapest free across different family products', function () {
        $order       = Order::first();
        $transaction = Transaction::where('order_id', $order->id)->first();

        expect((float)$transaction->net_amount)->toBe(270.0)
            ->and((int)$transaction->quantity_ordered)->toBe(3);

        $cheapProduct = Product::where('shop_id', $this->shop->id)->where('code', 'GIFT-PROD')->first();
        expect($cheapProduct)->not->toBeNull()
            ->and($cheapProduct->family_id)->toBe($this->product->family_id);

        $cheapTransaction = StoreTransaction::make()->action(
            $order,
            $cheapProduct->currentHistoricProduct,
            ['quantity_ordered' => 1]
        );

        $offer = StoreBuyXGetCheapestFree::make()->action(
            $this->product->family,
            [
                'trigger_data_item_quantity' => 2,
                'free_quantity'              => 1,
                'duration'                   => 'interval',
                'start_at'                   => now(),
                'end_at'                     => now()->addDays(14)->toDateTimeString(),
            ]
        );
        expect($offer)->toBeInstanceOf(Offer::class);

        CalculateOrderDiscounts::run($order->refresh());

        $transaction->refresh();
        $cheapTransaction->refresh();

        expect((float)$cheapTransaction->gross_amount)->toBe(50.0)
            ->and((float)$cheapTransaction->net_amount)->toBe(0.0)
            ->and(Arr::get($cheapTransaction->offers_data, 'o.nf'))->toBe(1)
            ->and((float)Arr::get($cheapTransaction->offers_data, 'o.f'))->toBe(50.0)
            ->and((float)$transaction->net_amount)->toBe(200.0)
            ->and(Arr::get($transaction->offers_data, 'o.nf'))->toBe(1)
            ->and((float)Arr::get($transaction->offers_data, 'o.f'))->toBe(100.0);

        SuspendOffer::run($offer);
        DeleteTransaction::run($cheapTransaction->refresh());
        CalculateOrderDiscounts::run($order->refresh());
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(270.0);
    });

    test('sub-department offers: percentage, quantity, amount, voucher and shipping scopes', function () {
        $order       = Order::first();
        $transaction = Transaction::where('order_id', $order->id)->first();
        expect((float)$transaction->net_amount)->toBe(270.0);

        $department = $this->shop->productCategories()->where('type', ProductCategoryTypeEnum::DEPARTMENT)->orderBy('id')->first();

        $subDepartmentData = ProductCategory::factory()->definition();
        data_set($subDepartmentData, 'code', 'SDEP-T');
        data_set($subDepartmentData, 'type', ProductCategoryTypeEnum::SUB_DEPARTMENT->value);
        $subDepartment = StoreProductCategory::make()->action($department, $subDepartmentData);

        $subFamilyData = ProductCategory::factory()->definition();
        data_set($subFamilyData, 'code', 'SDEP-FAM');
        data_set($subFamilyData, 'type', ProductCategoryTypeEnum::FAMILY->value);
        $subFamily = StoreProductCategory::make()->action($subDepartment, $subFamilyData);

        $subProductData = array_merge(
            Product::factory()->definition(),
            [
                'code'        => 'SDEP-PROD',
                'price'       => 60,
                'trade_units' => [
                    [
                        'id'       => $this->tradeUnit[0]->id ?? $this->tradeUnit->id,
                        'quantity' => 1
                    ]
                ],
            ]
        );
        $subProduct = StoreProduct::make()->action($subFamily, $subProductData);
        expect($subProduct->sub_department_id)->toBe($subDepartment->id);

        $subTransaction = StoreTransaction::make()->action($order, $subProduct->currentHistoricProduct, ['quantity_ordered' => 2]);
        $subTransaction->refresh();
        expect($subTransaction->sub_department_id)->toBe($subDepartment->id)
            ->and((float)$subTransaction->gross_amount)->toBe(120.0);

        $subDepartmentOffer = StoreProductCategoryDiscount::make()->action($subDepartment, [
            'type'                       => 'quantity',
            'trigger_data_item_quantity' => 1,
            'percentage_off'             => 0.25,
            'duration'                   => 'permanent',
            'start_at'                   => now(),
        ]);
        expect($subDepartmentOffer->refresh()->type)->toBe('Subdepartment Ordered');

        CalculateOrderDiscounts::run($order->refresh());
        $transaction->refresh();
        $subTransaction->refresh();
        expect((float)$subTransaction->net_amount)->toBe(90.0)
            ->and(Arr::get($subTransaction->offers_data, 'o.o'))->toBe($subDepartmentOffer->id)
            ->and((float)$transaction->net_amount)->toBe(270.0);

        $subDepartmentOffer->update(['type' => 'Subdepartment Quantity Ordered', 'trigger_data' => ['item_quantity' => 5]]);
        CalculateOrderDiscounts::run($order->refresh());
        $subTransaction->refresh();
        expect((float)$subTransaction->net_amount)->toBe(108.0);

        $subDepartmentOffer->update(['type' => 'Subdepartment Amount Ordered', 'trigger_data' => ['item_amount' => 100]]);
        CalculateOrderDiscounts::run($order->refresh());
        $subTransaction->refresh();
        expect((float)$subTransaction->net_amount)->toBe(90.0);

        SuspendOffer::run($subDepartmentOffer);

        $subDepartmentVoucher = StoreVoucherOffers::make()->action($this->shop, [
            'voucher'            => 'SUBDEP30',
            'name'               => 'Sub-department voucher',
            'offer_amount'       => 100,
            'can_customer_reuse' => true,
            'start_at'           => now()->subDay(),
            'end_at'             => now()->addDay(),
            'percentage_off'     => 30,
            'target_type'        => 'sub_department',
            'target_id'          => $subDepartment->id,
            'allowance_type'     => 'percentage_off',
        ]);
        AddVoucherToOrder::run($order, ['voucher' => 'SUBDEP30']);
        $transaction->refresh();
        $subTransaction->refresh();
        expect((float)$subTransaction->net_amount)->toBe(84.0)
            ->and(Arr::get($subTransaction->offers_data, 'o.o'))->toBe($subDepartmentVoucher->id)
            ->and((float)$transaction->net_amount)->toBe(270.0);
        RemoveVoucherFromOrder::run($order);
        SuspendOffer::run($subDepartmentVoucher);

        $shippingOffer = StoreDiscountShipping::make()->handle($this->shop, [
            'name'             => 'Sub-department shipping',
            'min_order_amount' => 100,
            'target_type'      => 'sub_department',
            'target_id'        => $subDepartment->id,
            'start_at'         => now()->toDateTimeString(),
            'end_at'           => now()->addDays(7)->toDateTimeString(),
        ]);
        ShopHydrateOffersData::run($this->shop->id);
        $this->shop->refresh();
        expect(Arr::get(CalculateOrderShipping::make()->matchScopedShippingOffer($order->refresh(), $this->shop->offers_data), 'id'))->toBe($shippingOffer->id);
        SuspendOffer::run($shippingOffer);
        ShopHydrateOffersData::run($this->shop->id);

        SuspendOffer::run($subDepartmentOffer);
        DeleteTransaction::run($subTransaction->refresh());
        CalculateOrderDiscounts::run($order->refresh());
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(270.0);
    });

    test('department and product voucher targets', function () {
        $order       = Order::first();
        $transaction = Transaction::where('order_id', $order->id)->first();
        expect((float)$transaction->net_amount)->toBe(270.0);

        $department = $this->shop->productCategories()->where('type', ProductCategoryTypeEnum::DEPARTMENT)->orderBy('id')->first();

        $departmentVoucher = StoreVoucherOffers::make()->action($this->shop, [
            'voucher'            => 'DEP30',
            'name'               => 'Department voucher',
            'offer_amount'       => 0,
            'can_customer_reuse' => true,
            'start_at'           => now()->subDay(),
            'end_at'             => now()->addDay(),
            'percentage_off'     => 30,
            'target_type'        => 'department',
            'target_id'          => $department->id,
            'allowance_type'     => 'percentage_off',
        ]);
        AddVoucherToOrder::run($order, ['voucher' => 'DEP30']);
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(210.0)
            ->and(Arr::get($transaction->offers_data, 'o.o'))->toBe($departmentVoucher->id);
        RemoveVoucherFromOrder::run($order);
        SuspendOffer::run($departmentVoucher);

        $productVoucher = StoreVoucherOffers::make()->action($this->shop, [
            'voucher'            => 'PROD50',
            'name'               => 'Product voucher',
            'offer_amount'       => 0,
            'can_customer_reuse' => true,
            'start_at'           => now()->subDay(),
            'end_at'             => now()->addDay(),
            'percentage_off'     => 50,
            'target_type'        => 'product',
            'target_id'          => $this->product->id,
            'allowance_type'     => 'percentage_off',
        ]);
        AddVoucherToOrder::run($order, ['voucher' => 'PROD50']);
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(150.0)
            ->and(Arr::get($transaction->offers_data, 'o.o'))->toBe($productVoucher->id);
        RemoveVoucherFromOrder::run($order);
        SuspendOffer::run($productVoucher);

        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(270.0);
    });

    test('multiple competing offers on the same transaction: best discount wins', function () {
        $order       = Order::first();
        $transaction = Transaction::where('order_id', $order->id)->first();
        expect((float)$transaction->net_amount)->toBe(270.0)
            ->and((int)$transaction->quantity_ordered)->toBe(3);

        $familyOffer = StoreProductCategoryDiscount::make()->action($this->product->family, [
            'type'                       => 'quantity',
            'trigger_data_item_quantity' => 1,
            'percentage_off'             => 0.20,
            'duration'                   => 'permanent',
            'start_at'                   => now(),
        ]);

        $stepOffer = StoreProductStepDiscount::make()->action($this->product, [
            'steps'    => [
                ['min_quantity' => 1, 'percentage_off' => 0.15],
                ['min_quantity' => 5, 'percentage_off' => 0.40],
            ],
            'duration' => 'permanent',
            'start_at' => now(),
        ]);

        $freeOffer = StoreBuyXGetCheapestFree::make()->actionForProduct($this->product, [
            'trigger_data_item_quantity' => 3,
            'free_quantity'              => 1,
            'duration'                   => 'permanent',
            'start_at'                   => now(),
        ]);

        CalculateOrderDiscounts::run($order->refresh());
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(200.0)
            ->and(Arr::get($transaction->offers_data, 'o.o'))->toBe($freeOffer->id);

        SuspendOffer::run($freeOffer);
        CalculateOrderDiscounts::run($order->refresh());
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(240.0)
            ->and(Arr::get($transaction->offers_data, 'o.o'))->toBe($familyOffer->id);

        UpdateTransaction::run($transaction, ['quantity_ordered' => 5]);
        $transaction->refresh();
        expect((float)$transaction->gross_amount)->toBe(500.0)
            ->and((float)$transaction->net_amount)->toBe(300.0)
            ->and(Arr::get($transaction->offers_data, 'o.o'))->toBe($stepOffer->id);

        SuspendOffer::run($stepOffer);
        SuspendOffer::run($familyOffer);
        UpdateTransaction::run($transaction, ['quantity_ordered' => 3]);
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(270.0);
    });

    test('customer exclusive offers: amount threshold and any order', function () {
        $order       = Order::first();
        $transaction = Transaction::where('order_id', $order->id)->first();

        $customerOffer = StoreCustomerOffers::run($this->shop, [
            'customer_id'      => $order->customer_id,
            'min_order_amount' => 1000,
            'percentage_off'   => 15,
            'target_type'      => 'shop',
            'target_id'        => $this->shop->id,
            'duration'         => 'permanent',
            'start_at'         => now(),
        ]);
        expect($customerOffer->refresh()->type)->toBe('Customer Amount Ordered');

        CalculateOrderDiscounts::run($order);
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(270.0);

        $customerOffer->update(['trigger_data' => ['min_order_amount' => 250]]);
        CalculateOrderDiscounts::run($order->refresh());
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(255.0)
            ->and((float)$transaction->current_discount_factor)->toEqualWithDelta(0.85, 0.00001)
            ->and(Arr::get($transaction->offers_data, 'o.o'))->toBe($customerOffer->id);

        $customerOffer->update(['type' => 'Customer Any Order', 'trigger_data' => ['min_order_amount' => 0]]);
        CalculateOrderDiscounts::run($order);
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(255.0)
            ->and(Arr::get($transaction->offers_data, 'o.o'))->toBe($customerOffer->id);

        SuspendOffer::run($customerOffer);
    });

    test('voucher offers apply only while attached to the order', function () {
        $order       = Order::first();
        $transaction = Transaction::where('order_id', $order->id)->first();

        $voucherOffer = StoreVoucherOffers::make()->action($this->shop, [
            'voucher'            => 'SAVE20',
            'name'               => 'Save 20',
            'offer_amount'       => 0,
            'can_customer_reuse' => true,
            'start_at'           => now()->subDay(),
            'end_at'             => now()->addDay(),
            'percentage_off'     => 20,
            'target_type'        => 'shop',
            'target_id'          => $this->shop->id,
            'allowance_type'     => 'percentage_off',
        ]);
        expect($voucherOffer)->toBeInstanceOf(Offer::class)
            ->and($voucherOffer->refresh()->type)->toBe('Voucher Any Order');

        AddVoucherToOrder::run($order, ['voucher' => 'SAVE20']);
        $order->refresh();
        $transaction->refresh();
        expect($order->offer_voucher_id)->toBe($voucherOffer->id)
            ->and((float)$transaction->net_amount)->toBe(240.0)
            ->and((float)$transaction->current_discount_factor)->toEqualWithDelta(0.8, 0.00001)
            ->and(Arr::get($transaction->offers_data, 'o.o'))->toBe($voucherOffer->id);

        RemoveVoucherFromOrder::run($order);
        $order->refresh();
        $transaction->refresh();
        expect($order->offer_voucher_id)->toBeNull()
            ->and((float)$transaction->net_amount)->toBe(270.0);

        $voucherOffer->update(['type' => 'Voucher Amount Ordered', 'trigger_data' => ['item_amount' => 1000]]);
        AddVoucherToOrder::run($order, ['voucher' => 'SAVE20']);
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(270.0);

        $voucherOffer->update(['trigger_data' => ['item_amount' => 250]]);
        CalculateOrderDiscounts::run($order->refresh());
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(240.0);

        RemoveVoucherFromOrder::run($order);
        SuspendOffer::run($voucherOffer);
    });

    test('cross family discount: spend on one family discounts another family', function () {
        $order       = Order::first();
        $transaction = Transaction::where('order_id', $order->id)->first();

        expect((float)$transaction->net_amount)->toBe(270.0);

        $department = $this->shop->productCategories()->where('type', ProductCategoryTypeEnum::DEPARTMENT)->first();

        $familyBData = ProductCategory::factory()->definition();
        data_set($familyBData, 'code', 'XFAM-B');
        data_set($familyBData, 'type', ProductCategoryTypeEnum::FAMILY->value);
        $familyB = StoreProductCategory::make()->action($department, $familyBData);

        $productBData = array_merge(
            Product::factory()->definition(),
            [
                'code'        => 'XFAM-PROD',
                'price'       => 40,
                'trade_units' => [
                    [
                        'id'       => $this->tradeUnit[0]->id ?? $this->tradeUnit->id,
                        'quantity' => 1
                    ]
                ],
            ]
        );
        $productB     = StoreProduct::make()->action($familyB, $productBData);
        $transactionB = StoreTransaction::make()->action($order, $productB->currentHistoricProduct, ['quantity_ordered' => 1]);

        $crossOffer = StoreProductCategoryDiscount::make()->action(
            $this->product->family,
            [
                'type'                       => 'quantity',
                'trigger_data_item_quantity' => 1,
                'percentage_off'             => 0.50,
                'target_product_category_id' => $familyB->id,
                'duration'                   => 'interval',
                'start_at'                   => now(),
                'end_at'                     => now()->addDays(14)->toDateTimeString(),
            ]
        );
        $crossOffer->refresh();

        $allowance = $crossOffer->offerAllowances->first();
        expect($crossOffer->trigger_id)->toBe($this->product->family_id)
            ->and($allowance->target_id)->toBe($familyB->id)
            ->and(Arr::get($allowance->data, 'category_id'))->toBe($familyB->id);

        CalculateOrderDiscounts::run($order->refresh());
        $transaction->refresh();
        $transactionB->refresh();

        expect((float)$transactionB->gross_amount)->toBe(40.0)
            ->and((float)$transactionB->net_amount)->toBe(20.0)
            ->and(Arr::get($transactionB->offers_data, 'o.o'))->toBe($crossOffer->id)
            ->and((float)$transaction->net_amount)->toBe(270.0)
            ->and(Arr::get($transaction->offers_data, 'o.o'))->not->toBe($crossOffer->id);

        SuspendOffer::run($crossOffer);
        DeleteTransaction::run($transactionB->refresh());
        CalculateOrderDiscounts::run($order->refresh());
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(270.0);
    });

    test('scoped vouchers: family target with scope minimum spend', function () {
        $order       = Order::first();
        $transaction = Transaction::where('order_id', $order->id)->first();

        expect((float)$transaction->net_amount)->toBe(270.0);

        $voucherOffer = StoreVoucherOffers::make()->action($this->shop, [
            'voucher'            => 'FAM20',
            'name'               => 'Family voucher',
            'offer_amount'       => 250,
            'can_customer_reuse' => true,
            'start_at'           => now()->subDay(),
            'end_at'             => now()->addDay(),
            'percentage_off'     => 20,
            'target_type'        => 'family',
            'target_id'          => $this->product->family_id,
            'allowance_type'     => 'percentage_off',
        ]);
        $voucherOffer->refresh();

        $allowance = $voucherOffer->offerAllowances->first();
        expect($allowance->target_type)->toBe(OfferAllowanceTargetTypeEnum::ALL_PRODUCTS_IN_PRODUCT_CATEGORY)
            ->and(Arr::get($allowance->data, 'category_id'))->toBe($this->product->family_id)
            ->and((float)Arr::get($allowance->data, 'item_amount'))->toBe(250.0);

        AddVoucherToOrder::run($order, ['voucher' => 'FAM20']);
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(240.0)
            ->and(Arr::get($transaction->offers_data, 'o.o'))->toBe($voucherOffer->id);

        $allowanceData                = $allowance->data;
        $allowanceData['item_amount'] = 5000;
        $allowance->update(['data' => $allowanceData]);
        CalculateOrderDiscounts::run($order->refresh());
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(270.0);

        RemoveVoucherFromOrder::run($order);
        SuspendOffer::run($voucherOffer);
    });

    test('scoped vouchers: collection target', function () {
        $order       = Order::first();
        $transaction = Transaction::where('order_id', $order->id)->first();

        expect((float)$transaction->net_amount)->toBe(270.0);

        $collection = StoreCollection::make()->action($this->shop, [
            'code' => 'VCOL',
            'name' => 'Voucher Collection',
        ]);
        AttachModelToCollection::run($collection, $this->product);

        $voucherOffer = StoreVoucherOffers::make()->action($this->shop, [
            'voucher'            => 'COL15',
            'name'               => 'Collection voucher',
            'offer_amount'       => 0,
            'can_customer_reuse' => true,
            'start_at'           => now()->subDay(),
            'end_at'             => now()->addDay(),
            'percentage_off'     => 15,
            'target_type'        => 'collection',
            'target_id'          => $collection->id,
            'allowance_type'     => 'percentage_off',
        ]);
        $voucherOffer->refresh();

        expect($voucherOffer->offerAllowances->first()->target_type)->toBe(OfferAllowanceTargetTypeEnum::ALL_PRODUCTS_IN_COLLECTION)
            ->and(Arr::get($voucherOffer->offerAllowances->first()->data, 'collection_id'))->toBe($collection->id);

        AddVoucherToOrder::run($order, ['voucher' => 'COL15']);
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(255.0)
            ->and(Arr::get($transaction->offers_data, 'o.o'))->toBe($voucherOffer->id);

        RemoveVoucherFromOrder::run($order);
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(270.0);

        SuspendOffer::run($voucherOffer);
    });

    test('scoped discounted shipping offers', function () {
        $order       = Order::first();
        $transaction = Transaction::where('order_id', $order->id)->first();
        expect((float)$transaction->net_amount)->toBe(270.0);

        $offer = StoreDiscountShipping::make()->handle($this->shop, [
            'name'             => 'Family free shipping',
            'min_order_amount' => 250,
            'target_type'      => 'family',
            'target_id'        => $this->product->family_id,
            'start_at'         => now()->toDateTimeString(),
            'end_at'           => now()->addDays(7)->toDateTimeString(),
        ]);
        expect($offer)->toBeInstanceOf(Offer::class)
            ->and(Arr::get($offer->trigger_data, 'target_type'))->toBe('family')
            ->and(Arr::get($offer->trigger_data, 'target_id'))->toBe($this->product->family_id);

        ShopHydrateOffersData::run($this->shop->id);
        $this->shop->refresh();

        $scopedData = Arr::get($this->shop->offers_data, 'discounted_shipping_scoped');
        expect($scopedData)->toHaveKey($offer->id)
            ->and(Arr::get($this->shop->offers_data, 'discounted_shipping.id'))->not->toBe($offer->id);

        $order->refresh();
        $matched = CalculateOrderShipping::make()->matchScopedShippingOffer($order, $this->shop->offers_data);
        expect(Arr::get($matched, 'id'))->toBe($offer->id)
            ->and(Arr::get($matched, 'offer_allowance_id'))->not->toBeNull();

        $offer->update(['trigger_data' => ['min_order_amount' => 5000, 'target_type' => 'family', 'target_id' => $this->product->family_id]]);
        ShopHydrateOffersData::run($this->shop->id);
        $this->shop->refresh();
        expect(CalculateOrderShipping::make()->matchScopedShippingOffer($order, $this->shop->offers_data))->toBeNull();

        $offer->update(['trigger_data' => ['min_order_amount' => 250, 'target_type' => 'product', 'target_id' => $this->product->id]]);
        ShopHydrateOffersData::run($this->shop->id);
        $this->shop->refresh();
        expect(Arr::get(CalculateOrderShipping::make()->matchScopedShippingOffer($order, $this->shop->offers_data), 'id'))->toBe($offer->id);

        $collection = Collection::where('shop_id', $this->shop->id)->where('code', 'VCOL')->first();
        expect($collection)->not->toBeNull();
        $offer->update(['trigger_data' => ['min_order_amount' => 250, 'target_type' => 'collection', 'target_id' => $collection->id]]);
        ShopHydrateOffersData::run($this->shop->id);
        $this->shop->refresh();
        expect(Arr::get(CalculateOrderShipping::make()->matchScopedShippingOffer($order, $this->shop->offers_data), 'id'))->toBe($offer->id);

        SuspendOffer::run($offer);
        ShopHydrateOffersData::run($this->shop->id);
        $this->shop->refresh();
        expect(Arr::get($this->shop->offers_data, 'discounted_shipping_scoped'))->toBe([]);
    });

    test('amount off vouchers: 30% cap validation, order totals and tax base', function () {
        $order       = Order::first();
        $transaction = Transaction::where('order_id', $order->id)->first();
        expect((float)$transaction->net_amount)->toBe(270.0)
            ->and((float)$order->refresh()->amount_off)->toBe(0.0);

        expect(fn () => StoreVoucherOffers::make()->action($this->shop, [
            'voucher'            => 'CASH40',
            'name'               => 'Too big amount off',
            'offer_amount'       => 100,
            'can_customer_reuse' => true,
            'start_at'           => now()->subDay(),
            'end_at'             => now()->addDay(),
            'amount_off'         => 40,
            'target_type'        => 'shop',
            'target_id'          => $this->shop->id,
            'allowance_type'     => 'amount_off',
        ]))->toThrow(ValidationException::class);

        expect(fn () => StoreVoucherOffers::make()->action($this->shop, [
            'voucher'            => 'CASH25',
            'name'               => 'No min purchase',
            'offer_amount'       => 0,
            'can_customer_reuse' => true,
            'start_at'           => now()->subDay(),
            'end_at'             => now()->addDay(),
            'amount_off'         => 25,
            'target_type'        => 'shop',
            'target_id'          => $this->shop->id,
            'allowance_type'     => 'amount_off',
        ]))->toThrow(ValidationException::class);

        $voucherOffer = StoreVoucherOffers::make()->action($this->shop, [
            'voucher'            => 'CASH25',
            'name'               => '25 off',
            'offer_amount'       => 100,
            'can_customer_reuse' => true,
            'start_at'           => now()->subDay(),
            'end_at'             => now()->addDay(),
            'amount_off'         => 25,
            'target_type'        => 'shop',
            'target_id'          => $this->shop->id,
            'allowance_type'     => 'amount_off',
        ]);
        $voucherOffer->refresh();

        expect($voucherOffer->offerAllowances->first()->type)->toBe(OfferAllowanceType::AMOUNT_OFF)
            ->and((float)Arr::get($voucherOffer->offerAllowances->first()->data, 'amount_off'))->toBe(25.0);

        AddVoucherToOrder::run($order, ['voucher' => 'CASH25']);
        $order->refresh();
        $transaction->refresh();

        $taxRate = $order->taxCategory->rate;
        expect((float)$order->amount_off)->toBe(25.0)
            ->and((float)$transaction->net_amount)->toBe(270.0)
            ->and((float)$order->net_amount)->toBe(245.0)
            ->and((float)$order->tax_amount)->toEqualWithDelta(245.0 * $taxRate, 0.01)
            ->and((float)$order->total_amount)->toEqualWithDelta(245.0 * (1 + $taxRate), 0.01);

        RemoveVoucherFromOrder::run($order);
        $order->refresh();
        expect((float)$order->amount_off)->toBe(0.0)
            ->and((float)$order->net_amount)->toBe(270.0);

        SuspendOffer::run($voucherOffer);
    });

    test('CalculateOrderShipping end to end: zone schema, scoped discount and revert', function () {
        $order       = Order::first();
        $transaction = Transaction::where('order_id', $order->id)->first();
        expect((float)$transaction->net_amount)->toBe(270.0);

        $schema = StoreShippingZoneSchema::make()->action($this->shop, ['name' => 'Normal shipping']);
        $zone   = StoreShippingZone::make()->action($schema, [
            'code'        => 'ZONE-ALL',
            'name'        => 'Whole world',
            'status'      => true,
            'price'       => [
                'type'  => 'Step Order Items Net Amount',
                'steps' => [
                    ['from' => 0, 'to' => 500, 'price' => 10],
                    ['from' => 500, 'to' => 'INF', 'price' => 0],
                ],
            ],
            'position'    => 1,
            'is_failover' => false,
        ]);

        $discountSchema = StoreShippingZoneSchema::make()->action($this->shop, ['name' => 'Discounted shipping']);
        $discountZone   = StoreShippingZone::make()->action($discountSchema, [
            'code'        => 'ZONE-FREE',
            'name'        => 'Whole world free',
            'status'      => true,
            'price'       => [
                'type'  => 'Step Order Items Net Amount',
                'steps' => [
                    ['from' => 0, 'to' => 'INF', 'price' => 0],
                ],
            ],
            'position'    => 1,
            'is_failover' => false,
        ]);

        $this->shop->update([
            'shipping_zone_schema_id'          => $schema->id,
            'discount_shipping_zone_schema_id' => $discountSchema->id,
        ]);

        $order->update(['shipping_engine' => OrderShippingEngineEnum::AUTO]);
        OrderHydrateTransactions::run($order);
        $order = $order->refresh();
        expect($order->stats->number_item_transactions)->toBeGreaterThan(0);
        CalculateOrderShipping::run($order);
        $shippingTransaction = $order->transactions()->where('model_type', 'ShippingZone')->first();
        $order->refresh();
        expect($shippingTransaction)->not->toBeNull()
            ->and((float)$shippingTransaction->net_amount)->toBe(10.0)
            ->and($order->shipping_zone_id)->toBe($zone->id)
            ->and($order->shipping_zone_schema_id)->toBe($schema->id)
            ->and((float)$order->shipping_amount)->toBe(10.0);

        $shippingOffer = StoreDiscountShipping::make()->handle($this->shop, [
            'code'             => 'sh-e2e-family',
            'name'             => 'Family shipping discount',
            'min_order_amount' => 250,
            'target_type'      => 'family',
            'target_id'        => $this->product->family_id,
            'start_at'         => now()->toDateTimeString(),
            'end_at'           => now()->addDays(7)->toDateTimeString(),
        ]);
        ShopHydrateOffersData::run($this->shop->id);

        $order = $order->fresh();
        CalculateOrderShipping::run($order);
        $shippingTransaction->refresh();
        $order->refresh();
        expect((float)$shippingTransaction->net_amount)->toBe(0.0)
            ->and($order->shipping_zone_id)->toBe($discountZone->id)
            ->and($order->discounted_shipping_offer_id)->toBe($shippingOffer->id);

        $pivot = DB::table('transaction_has_offer_allowances')
            ->where('transaction_id', $shippingTransaction->id)
            ->where('offer_id', $shippingOffer->id)
            ->first();
        expect($pivot)->not->toBeNull()
            ->and($pivot->offer_allowance_id)->not->toBeNull();

        SuspendOffer::run($shippingOffer);
        ShopHydrateOffersData::run($this->shop->id);

        $order = $order->fresh();
        CalculateOrderShipping::run($order);
        $shippingTransaction->refresh();
        $order->refresh();
        expect((float)$shippingTransaction->net_amount)->toBe(10.0)
            ->and($order->shipping_zone_id)->toBe($zone->id)
            ->and($order->discounted_shipping_offer_id)->toBeNull()
            ->and(
                DB::table('transaction_has_offer_allowances')
                    ->where('order_id', $order->id)
                    ->where('model_type', 'ShippingZone')
                    ->count()
            )->toBe(0);

        DB::table('transactions')->where('id', $shippingTransaction->id)->delete();
        $this->shop->update([
            'shipping_zone_schema_id'          => null,
            'discount_shipping_zone_schema_id' => null,
        ]);
        $order = $order->fresh();
        CalculateOrderTotalAmounts::run(order: $order, calculateShipping: false, calculateDiscounts: false);
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(270.0);
    });

    test('shop wide offers: amount threshold and unconditional', function () {
        $order       = Order::first();
        $transaction = Transaction::where('order_id', $order->id)->first();

        $shopOffer = StoreShopOffer::run($this->shop, [
            'type'                     => 'amount',
            'trigger_data_item_amount' => 250,
            'percentage_off'           => 0.25,
            'duration'                 => 'permanent',
            'start_at'                 => now(),
        ]);
        expect($shopOffer->refresh()->type)->toBe('Shop Amount Ordered');

        CalculateOrderDiscounts::run($order);
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(225.0)
            ->and((float)$transaction->current_discount_factor)->toEqualWithDelta(0.75, 0.00001)
            ->and(Arr::get($transaction->offers_data, 'o.o'))->toBe($shopOffer->id);

        $shopOffer->update(['trigger_data' => ['item_amount' => 1000]]);
        CalculateOrderDiscounts::run($order->refresh());
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(270.0);

        $shopOffer->update(['type' => 'Shop Ordered']);
        CalculateOrderDiscounts::run($order);
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(225.0)
            ->and(Arr::get($transaction->offers_data, 'o.o'))->toBe($shopOffer->id);

        SuspendOffer::run($shopOffer);
    });

    test('department offers: quantity, unconditional and amount thresholds', function () {
        $order       = Order::first();
        $transaction = Transaction::where('order_id', $order->id)->first();
        $department  = $this->shop->productCategories()->where('type', ProductCategoryTypeEnum::DEPARTMENT)->first();

        $departmentOffer = StoreProductCategoryDiscount::make()->action($department, [
            'type'                       => 'quantity',
            'trigger_data_item_quantity' => 4,
            'percentage_off'             => 0.35,
            'duration'                   => 'permanent',
            'start_at'                   => now(),
        ]);
        expect($departmentOffer->type)->toBe('Department Quantity Ordered');

        CalculateOrderDiscounts::run($order);
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(270.0);

        UpdateTransaction::run($transaction, ['quantity_ordered' => 4]);
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(260.0)
            ->and((float)$transaction->current_discount_factor)->toEqualWithDelta(0.65, 0.00001)
            ->and(Arr::get($transaction->offers_data, 'o.o'))->toBe($departmentOffer->id);

        UpdateTransaction::run($transaction, ['quantity_ordered' => 3]);
        $departmentOffer->update(['type' => 'Department Ordered']);
        CalculateOrderDiscounts::run($order->refresh());
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(195.0);

        $departmentOffer->update(['type' => 'Department Amount Ordered', 'trigger_data' => ['item_amount' => 100]]);
        CalculateOrderDiscounts::run($order);
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(195.0)
            ->and(Arr::get($transaction->offers_data, 'o.o'))->toBe($departmentOffer->id);

        SuspendOffer::run($departmentOffer);
    });

    test('category amount ordered threshold', function () {
        $order         = Order::first();
        $transaction   = Transaction::where('order_id', $order->id)->first();
        $categoryOffer = Offer::where('shop_id', $order->shop_id)->where('type', 'Category Ordered')->orderBy('id')->first();

        ActivateOffer::run($categoryOffer);
        $categoryOffer->update(['type' => 'Category Amount Ordered', 'trigger_data' => ['item_amount' => 100]]);
        CalculateOrderDiscounts::run($order);
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(120.0)
            ->and(Arr::get($transaction->offers_data, 'o.o'))->toBe($categoryOffer->id);

        $categoryOffer->update(['trigger_data' => ['item_amount' => 5000]]);
        CalculateOrderDiscounts::run($order->refresh());
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(270.0);

        $categoryOffer->update(['type' => 'Category Ordered', 'trigger_data' => ['item_quantity' => 1]]);
        SuspendOffer::run($categoryOffer);
    });

    test('discretionary discounts interact with regular offers', function () {
        $order                  = Order::first();
        $transaction            = Transaction::where('order_id', $order->id)->first();
        $discretionaryAllowance = OfferAllowance::where('shop_id', $order->shop_id)->where('is_discretionary', true)->first();

        if (!$discretionaryAllowance) {
            $discretionaryCampaign = OfferCampaign::where('shop_id', $order->shop_id)->where('type', OfferCampaignTypeEnum::DISCRETIONARY)->first();
            $discretionaryOffer    = StoreOffer::make()->action(
                $discretionaryCampaign,
                [
                    'state'            => OfferStateEnum::ACTIVE,
                    'status'           => true,
                    'duration'         => OfferDurationEnum::PERMANENT,
                    'code'             => 'di2-'.$this->shop->slug,
                    'name'             => 'Discretionary Discount',
                    'type'             => 'Discretionary',
                    'start_at'         => now(),
                    'is_discretionary' => true,
                ],
                strict: false
            );

            StoreOfferAllowance::make()->action(
                $discretionaryOffer,
                [
                    'code'             => 'di2-'.$this->shop->slug,
                    'state'            => OfferAllowanceStateEnum::ACTIVE,
                    'start_at'         => now(),
                    'trigger_scope'    => 'NA',
                    'is_discretionary' => true,
                ],
                strict: false
            );

            $discretionaryAllowance = OfferAllowance::where('shop_id', $order->shop_id)->where('is_discretionary', true)->first();
        }

        expect($discretionaryAllowance)->not->toBeNull();

        UpdateTransactionDiscretionaryDiscount::run($transaction, [
            'discretionary_offer'       => 0.5,
            'discretionary_offer_label' => 'Manager special',
        ]);
        $order->refresh();
        $transaction->refresh();
        expect(Arr::get($order->discretionary_offers_data, "$transaction->id.percentage"))->toEqual(0.5)
            ->and((float)$transaction->net_amount)->toBe(150.0)
            ->and((float)$transaction->current_discount_factor)->toEqualWithDelta(0.5, 0.00001)
            ->and(Arr::get($transaction->offers_data, 'o.l'))->toBe('Manager special')
            ->and(Arr::get($transaction->offers_data, 'o.oa'))->toBe($discretionaryAllowance->id);

        UpdateTransactionDiscretionaryDiscount::run($transaction, ['discretionary_offer' => 0.1]);
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(270.0)
            ->and(Arr::get($transaction->offers_data, 'o.l'))->toBe('Manager special');

        UpdateTransactionDiscretionaryDiscount::run($transaction, ['discretionary_offer' => 0.05]);
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(270.0)
            ->and(Arr::get($transaction->offers_data, 'o.st'))->toBe('fob');

        UpdateTransactionDiscretionaryDiscount::run($transaction, ['discretionary_offer' => 0]);
        $order->refresh();
        $transaction->refresh();
        expect($order->discretionary_offers_data)->toBe([])
            ->and((float)$transaction->net_amount)->toBe(270.0);
    });

    test('gift offers create meters without discounting', function () {
        $order       = Order::first();
        $transaction = Transaction::where('order_id', $order->id)->first();

        $giftOffer = StoreGiftsOffers::run($this->shop, [
            'name'             => 'Free mug',
            'product_id'       => $this->product->id,
            'duration'         => 'permanent',
            'min_order_amount' => 500,
            'quantity'         => 1,
            'start_at'         => now(),
        ]);

        CalculateOrderDiscounts::run($order);
        $order->refresh();
        $transaction->refresh();

        $giftMeter = collect($order->offer_meters)->firstWhere('offer_id', $giftOffer->id);
        expect($giftMeter)->not->toBeNull()
            ->and($giftMeter['is_gift'])->toBeTrue()
            ->and($giftMeter['metadata']['target'])->toEqual(500)
            ->and((float)$giftMeter['metadata']['current'])->toBe(300.0)
            ->and((float)$transaction->net_amount)->toBe(270.0);

        SuspendOffer::run($giftOffer);
    });

    test('cancelled orders are not recalculated and recalculation heals corrupted amounts', function () {
        $order       = Order::first();
        $transaction = Transaction::where('order_id', $order->id)->first();

        DB::table('transactions')->where('id', $transaction->id)->update(['net_amount' => 999]);
        $order->update(['state' => OrderStateEnum::CANCELLED]);
        CalculateOrderDiscounts::run($order);
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(999.0);

        $order->update(['state' => OrderStateEnum::CREATING]);
        CalculateOrderDiscounts::run($order);
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(270.0)
            ->and(Arr::get($transaction->offers_data, 'o.st'))->toBe('fob');
    });

    test('submitted orders keep their submitted discount when current discount is worse', function () {
        $order       = Order::first();
        $transaction = Transaction::where('order_id', $order->id)->first();
        $fobOffer    = Offer::where('shop_id', $order->shop_id)->where('type', 'Amount AND Order Number')->first();

        SuspendOffer::run($fobOffer);
        CalculateOrderDiscounts::run($order);
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(300.0)
            ->and((float)$transaction->current_discount_factor)->toEqualWithDelta(1.0, 0.00001)
            ->and($transaction->offers_data)->toBe([])
            ->and(DB::table('transaction_has_offer_allowances')->where('order_id', $order->id)->count())->toBe(0);

        $fobAllowance        = DB::table('offer_allowances')->where('offer_id', $fobOffer->id)->first();
        $submittedOffersData = [
            'v' => 1,
            'o' => [
                'oc'  => $fobOffer->offer_campaign_id,
                'o'   => $fobOffer->id,
                'oa'  => $fobAllowance->id,
                't'   => 'percentage',
                'p'   => '20%',
                'l'   => $fobOffer->name,
                'st'  => 'fob',
                'sto' => null,
                'f'   => 0,
                'nf'  => 0,
            ],
        ];
        $transaction->update([
            'has_discount_when_submitted' => true,
            'submitted_discount_factor'   => 0.8,
            'submitted_offers_data'       => $submittedOffersData,
        ]);
        $order->update(['state' => OrderStateEnum::SUBMITTED]);

        CalculateOrderDiscounts::run($order);
        $transaction->refresh();
        expect((float)$transaction->net_amount)->toBe(240.0)
            ->and((float)$transaction->current_discount_factor)->toEqualWithDelta(0.8, 0.00001)
            ->and($transaction->offers_data)->toEqual($submittedOffersData);

        $pivot = DB::table('transaction_has_offer_allowances')->where('transaction_id', $transaction->id)->first();
        expect($pivot)->not->toBeNull()
            ->and((float)$pivot->discounted_percentage)->toEqualWithDelta(0.2, 0.00001)
            ->and((float)$pivot->discounted_amount)->toBe(60.0)
            ->and($pivot->offer_id)->toBe($fobOffer->id);

        $order->update(['state' => OrderStateEnum::CREATING]);
        $transaction->update(['has_discount_when_submitted' => false, 'submitted_offers_data' => []]);
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
