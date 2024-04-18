<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 02 Jan 2024 22:04:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\Fulfilment\Pallet\BookInPallet;
use App\Actions\Fulfilment\Pallet\SetPalletAsNotReceived;
use App\Actions\Fulfilment\Pallet\StoreMultiplePalletsFromDelivery;
use App\Actions\Fulfilment\Pallet\StorePallet;
use App\Actions\Fulfilment\Pallet\StorePalletFromDelivery;
use App\Actions\Fulfilment\PalletDelivery\BookedInPalletDelivery;
use App\Actions\Fulfilment\PalletDelivery\ConfirmPalletDelivery;
use App\Actions\Fulfilment\PalletDelivery\ReceivedPalletDelivery;
use App\Actions\Fulfilment\PalletDelivery\SendPalletDeliveryNotification;
use App\Actions\Fulfilment\PalletDelivery\StorePalletDelivery;
use App\Actions\Inventory\Location\StoreLocation;
use App\Actions\Market\RentalAgreement\StoreRentalAgreement;
use App\Actions\Market\Shop\StoreShop;
use App\Actions\Web\Website\StoreWebsite;
use App\Enums\CRM\Customer\CustomerStatusEnum;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Enums\Fulfilment\Pallet\PalletTypeEnum;
use App\Enums\Fulfilment\PalletDelivery\PalletDeliveryStateEnum;
use App\Enums\Market\RentalAgreement\RentalAgreementStateEnum;
use App\Enums\Market\Shop\ShopTypeEnum;
use App\Enums\UI\Fulfilment\FulfilmentsTabsEnum;
use App\Enums\Web\Website\WebsiteStateEnum;
use App\Models\CRM\Customer;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Inventory\Location;
use App\Models\Market\RentalAgreement;
use App\Models\Market\Shop;
use App\Models\SysAdmin\Permission;
use App\Models\SysAdmin\Role;
use App\Models\Web\Website;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeAll(function () {
    loadDB('test_base_database.dump');
});


beforeEach(function () {
    $this->organisation = createOrganisation();
    $this->adminGuest   = createAdminGuest($this->organisation->group);
    $this->warehouse    = createWarehouse();
    $location           = $this->warehouse->locations()->first();
    if (!$location) {
        StoreLocation::run(
            $this->warehouse,
            Location::factory()->definition()
        );
        StoreLocation::run(
            $this->warehouse,
            Location::factory()->definition()
        );
    }


    Config::set(
        'inertia.testing.page_paths',
        [resource_path('js/Pages/Grp')]
    );
    actingAs($this->adminGuest->user);
});

test('create fulfilment shop', function () {
    $organisation = $this->organisation;
    $storeData    = Shop::factory()->definition();
    data_set($storeData, 'type', ShopTypeEnum::FULFILMENT->value);
    data_set($storeData, 'warehouses', [$this->warehouse->id]);
    $shop = StoreShop::make()->action($this->organisation, $storeData);
    $organisation->refresh();

    $shopRoles             = Role::where('scope_type', 'Shop')->where('scope_id', $shop->id)->get();
    $shopPermissions       = Permission::where('scope_type', 'Shop')->where('scope_id', $shop->id)->get();
    $fulfilmentRoles       = Role::where('scope_type', 'Fulfilment')->where('scope_id', $shop->fulfilment->id)->get();
    $fulfilmentPermissions = Permission::where('scope_type', 'Fulfilment')->where('scope_id', $shop->fulfilment->id)->get();
    $warehouseRoles        = Role::where('scope_type', 'Warehouse')->where('scope_id', $this->warehouse->id)->get();
    $warehousePermissions  = Permission::where('scope_type', 'Warehouse')->where('scope_id', $this->warehouse->id)->get();


    //  dd($fulfilmentPermissions->pluck('name'));

    expect($shop)->toBeInstanceOf(Shop::class)
        ->and($shop->fulfilment)->toBeInstanceOf(Fulfilment::class)
        ->and($organisation->marketStats->number_shops)->toBe(1)
        ->and($organisation->marketStats->number_shops_type_b2b)->toBe(0)
        ->and($organisation->marketStats->number_shops_type_fulfilment)->toBe(1)
        ->and($shopRoles->count())->toBe(0)
        ->and($shopPermissions->count())->toBe(0)
        ->and($fulfilmentRoles->count())->toBe(2)
        ->and($fulfilmentPermissions->count())->toBe(4)
        ->and($warehouseRoles->count())->toBe(4)
        ->and($warehousePermissions->count())->toBe(10);

    $user = $this->adminGuest->user;
    $user->refresh();

    expect($user->getAllPermissions()->count())->toBe(24)
        ->and($user->hasAllRoles(["fulfilment-shop-supervisor-{$shop->fulfilment->id}"]))->toBe(true)
        ->and($user->hasAllRoles(["shop-admin-$shop->id"]))->toBe(false)
        ->and($shop->fulfilment->number_warehouses)->toBe(1);


    return $shop->fulfilment;
});

test('create fulfilment website', function (Fulfilment $fulfilment) {
    $website = StoreWebsite::make()->action(
        $fulfilment->shop,
        Website::factory()->definition(),
    );

    expect($website)->toBeInstanceOf(Website::class)
        ->and($website->state)->toBe(WebsiteStateEnum::IN_PROCESS);

    return $website;
})->depends('create fulfilment shop');

test('create fulfilment customer', function (Fulfilment $fulfilment) {
    $customer = StoreCustomer::make()->action(
        $fulfilment->shop,
        Customer::factory()->definition(),
    );

    expect($customer)->toBeInstanceOf(Customer::class)
        ->and($customer->reference)->toBe('000001')
        ->and($customer->status)->toBe(CustomerStatusEnum::APPROVED)
        ->and($customer->fulfilmentCustomer)->toBeInstanceOf(FulfilmentCustomer::class)
        ->and($customer->fulfilmentCustomer->number_pallets)->toBe(0)
        ->and($customer->fulfilmentCustomer->number_stored_items)->toBe(0);

    return $customer->fulfilmentCustomer;
})->depends('create fulfilment shop');

test('create rental agreement', function (FulfilmentCustomer $fulfilmentCustomer) {


    $rentalAgreement = StoreRentalAgreement::make()->action(
        $fulfilmentCustomer,
        []
    );

    expect($rentalAgreement)->toBeInstanceOf(RentalAgreement::class);

    return $rentalAgreement;
})->depends('create fulfilment customer');

test('update rental agreement', function (RentalAgreement $rentalAgreement) {
    $rentalAgreement->update([
        'billing_cycle' => 30,
        'pallets_limit' => 10,
        'state'         => RentalAgreementStateEnum::ACTIVE
    ]);

    expect($rentalAgreement->billing_cycle)->toBe(30)
        ->and($rentalAgreement->pallets_limit)->toBe(10);

    return $rentalAgreement;
})->depends('create rental agreement');

test('create pallet delivery', function ($fulfilmentCustomer) {

    SendPalletDeliveryNotification::shouldRun()
        ->andReturn();

    $palletDelivery = StorePalletDelivery::make()->action(
        $fulfilmentCustomer,
        [
            'warehouse_id' => $this->warehouse->id,
        ]
    );
    $fulfilmentCustomer->refresh();
    expect($palletDelivery)->toBeInstanceOf(PalletDelivery::class)
        ->and($palletDelivery->state)->toBe(PalletDeliveryStateEnum::IN_PROCESS)
        ->and($palletDelivery->number_pallets)->toBe(0)
        ->and($fulfilmentCustomer->number_pallet_deliveries)->toBe(1)
    ->and($fulfilmentCustomer->number_pallets)->toBe(0);

    return $palletDelivery;

})->depends('create fulfilment customer');

test('add pallet to pallet delivery', function (PalletDelivery $palletDelivery) {

    $pallet = StorePalletFromDelivery::make()->action(
        $palletDelivery,
        [
            'customer_reference' => 'C00001',
            'type'               => PalletTypeEnum::OVERSIZE->value,
            'notes'              => 'note A',
      ]
    );

    $palletDelivery->refresh();
    expect($pallet)->toBeInstanceOf(Pallet::class)
        ->and($pallet->state)->toBe(PalletStateEnum::IN_PROCESS)
        ->and($pallet->status)->toBe(PalletStatusEnum::RECEIVING)
        ->and($pallet->type)->toBe(PalletTypeEnum::OVERSIZE)
        ->and($pallet->notes)->toBe('note A')
        ->and($pallet->source_id)->toBeNull()
        ->and($pallet->customer_reference)->toBeString()
        ->and($pallet->received_at)->toBeNull()
        ->and($pallet->fulfilmentCustomer)->toBeInstanceOf(FulfilmentCustomer::class)
        ->and($pallet->fulfilmentCustomer->number_pallets)->toBe(1)
        ->and($pallet->fulfilmentCustomer->number_stored_items)->toBe(0)
        ->and($palletDelivery->number_pallets)->toBe(1)
        ->and($palletDelivery->number_pallet_stored_items)->toBe(0)
        ->and($palletDelivery->number_stored_items)->toBe(0);

    return $pallet;
})->depends('create pallet delivery');

test('add multiple pallets to pallet delivery', function (PalletDelivery $palletDelivery) {

    StoreMultiplePalletsFromDelivery::make()->action(
        $palletDelivery,
        [
           'warehouse_id'   => $this->warehouse->id,
           'number_pallets' => 2,
           'type'           => PalletTypeEnum::PALLET->value,
        ]
    );

    $palletDelivery->refresh();

    expect($palletDelivery->number_pallets)->toBe(3)
        ->and($palletDelivery->number_pallet_stored_items)->toBe(0)
        ->and($palletDelivery->number_stored_items)->toBe(0);

    return $palletDelivery;
})->depends('create pallet delivery');

test('confirm pallet delivery', function (PalletDelivery $palletDelivery) {

    SendPalletDeliveryNotification::shouldRun()->andReturn();

    $palletDelivery=ConfirmPalletDelivery::make()->action($palletDelivery);

    $pallet= $palletDelivery->pallets->first();

    expect($palletDelivery->state)->toBe(PalletDeliveryStateEnum::CONFIRMED)
        ->and($palletDelivery->confirmed_at)->toBeInstanceOf(Carbon::class)
        ->and($palletDelivery->number_pallets)->toBe(3)
        ->and($palletDelivery->number_pallet_stored_items)->toBe(0)
        ->and($palletDelivery->number_stored_items)->toBe(0)
        ->and($pallet->reference)->toEndWith('-p0001')
        ->and($pallet->state)->toBe(PalletStateEnum::CONFIRMED)
        ->and($pallet->status)->toBe(PalletStatusEnum::RECEIVING);

    return $palletDelivery;
})->depends('add multiple pallets to pallet delivery');

test('receive pallet delivery', function (PalletDelivery $palletDelivery) {

    SendPalletDeliveryNotification::shouldRun()->andReturn();

    $palletDelivery=ReceivedPalletDelivery::make()->action($palletDelivery);

    $palletDelivery->refresh();

    expect($palletDelivery->state)->toBe(PalletDeliveryStateEnum::RECEIVED)
        ->and($palletDelivery->received_at)->toBeInstanceOf(Carbon::class)
        ->and($palletDelivery->number_pallets)->toBe(3)
        ->and($palletDelivery->number_pallet_stored_items)->toBe(0)
        ->and($palletDelivery->number_stored_items)->toBe(0);

    return $palletDelivery;
})->depends('confirm pallet delivery');

test('set location of first pallet in the pallet delivery', function (PalletDelivery $palletDelivery) {

    $pallet = $palletDelivery->pallets->first();
    /** @var Location $location */
    $location = $this->warehouse->locations()->first();

    BookInPallet::make()->action($pallet, ['location_id' => $location->id]);
    $pallet->refresh();
    expect($pallet->location)->toBeInstanceOf(Location::class)
        ->and($pallet->location->id)->toBe($location->id)
        ->and($pallet->state)->toBe(PalletStateEnum::BOOKED_IN)
        ->and($pallet->status)->toBe(PalletStatusEnum::RECEIVING);

    return $palletDelivery;
})->depends('receive pallet delivery');

test('set second pallet in the pallet delivery as not delivered', function (PalletDelivery $palletDelivery) {

    $pallet = $palletDelivery->pallets->skip(1)->first();

    SetPalletAsNotReceived::make()->action($pallet);
    $pallet->refresh();
    expect($pallet->state)->toBe(PalletStateEnum::NOT_RECEIVED)
        ->and($pallet->status)->toBe(PalletStatusEnum::NOT_RECEIVED);

    return $palletDelivery;
})->depends('set location of first pallet in the pallet delivery');

test('set location of third pallet in the pallet delivery', function (PalletDelivery $palletDelivery) {

    $pallet = $palletDelivery->pallets->last();
    /** @var Location $location */
    $location = $this->warehouse->locations->last();
    expect($location->stats->number_pallets)->toBe(0);

    BookInPallet::make()->action($pallet, ['location_id' => $location->id]);
    $pallet->refresh();
    $location->refresh();
    expect($pallet->location)->toBeInstanceOf(Location::class)
        ->and($pallet->location->id)->toBe($location->id)
        ->and($pallet->state)->toBe(PalletStateEnum::BOOKED_IN)
        ->and($pallet->status)->toBe(PalletStatusEnum::RECEIVING)
        ->and($location->stats->number_pallets)->toBe(1);

    return $palletDelivery;
})->depends('set second pallet in the pallet delivery as not delivered');


test('set pallet delivery as booked in', function (PalletDelivery $palletDelivery) {

    SendPalletDeliveryNotification::shouldRun()->andReturn();

    $fulfilmentCustomer=$palletDelivery->fulfilmentCustomer;
    expect($fulfilmentCustomer->currentRecurringBill)->toBeNull();

    $palletDelivery=BookedInPalletDelivery::make()->action($palletDelivery);
    $palletDelivery->refresh();

    expect($palletDelivery->state)->toBe(PalletDeliveryStateEnum::BOOKED_IN)
        ->and($palletDelivery->booked_in_at)->toBeInstanceOf(Carbon::class)
        ->and($palletDelivery->number_pallets)->toBe(3)
        ->and($palletDelivery->number_pallet_stored_items)->toBe(0)
        ->and($palletDelivery->number_stored_items)->toBe(0);

    return $palletDelivery;
})->depends('receive pallet delivery');



test('UI list of fulfilment shops', function () {
    $response = get(route('grp.org.fulfilments.index', $this->organisation->slug));
    expect(FulfilmentsTabsEnum::FULFILMENT_SHOPS->value)->toBe('fulfilments');
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/Fulfilments')
            ->has('title')->has('tabs')->has(FulfilmentsTabsEnum::FULFILMENT_SHOPS->value.'.data')
            ->has('breadcrumbs', 2);
    });
});

test('UI list of websites in fulfilment', function (Fulfilment $fulfilment) {
    $response = get(
        route(
            'grp.org.fulfilments.show.web.websites.index',
            [
                $this->organisation->slug,
                $fulfilment->slug
            ]
        )
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Web/Websites')
            ->has('title')
            ->has('breadcrumbs', 3);
    });
})->depends('create fulfilment shop');

test('UI create fulfilment', function () {
    $response = get(route('grp.org.fulfilments.create', $this->organisation->slug));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->has('title')->has('formData')->has('pageHead')->has('breadcrumbs', 3);
    });
});


test('UI show fulfilment shop', function (Fulfilment $fulfilment) {
    $response = get(
        route(
            'grp.org.fulfilments.show.operations.dashboard',
            [
                $this->organisation->slug,
                $fulfilment->slug
            ]
        )
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/Fulfilment')
            ->has('title')->has('tabs')
            ->has('breadcrumbs', 2);
    });
})->depends('create fulfilment shop');


test('UI show fulfilment shop customers list', function (Fulfilment $fulfilment) {
    $response = get(
        route(
            'grp.org.fulfilments.show.crm.customers.index',
            [
                $this->organisation->slug,
                $fulfilment->slug
            ]
        )
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/Customers')
            ->has('title')
            ->has('breadcrumbs', 3);
    });
})->depends('create fulfilment shop');

test('UI show fulfilment pallet list', function (Fulfilment $fulfilment) {
    $response = get(
        route(
            'grp.org.fulfilments.show.pallets.index',
            [
                $this->organisation->slug,
                $fulfilment->slug
            ]
        )
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/Pallets')
            ->has('title')
            ->has('breadcrumbs', 3);
    });
})->depends('create fulfilment shop')->todo();


test('create pallet no delivery', function (Fulfilment $fulfilment) {

    $customer = StoreCustomer::make()->action(
        $fulfilment->shop,
        Customer::factory()->definition(),
    );


    $pallet = StorePallet::make()->action(
        $customer->fulfilmentCustomer,
        array_merge([
            'warehouse_id' => $this->warehouse->id,
        ], Pallet::factory()->definition())
    );


    expect($pallet)->toBeInstanceOf(Pallet::class)
        ->and($pallet->state)->toBe(PalletStateEnum::IN_PROCESS)
        ->and($pallet->status)->toBe(PalletStatusEnum::RECEIVING)
        ->and($pallet->type)->toBe(PalletTypeEnum::PALLET)
        ->and($pallet->notes)->toBe('')
        ->and($pallet->source_id)->toBeNull()
        ->and($pallet->customer_reference)->toBeString()
        ->and($pallet->received_at)->toBeNull()
        ->and($pallet->fulfilmentCustomer)->toBeInstanceOf(FulfilmentCustomer::class)
        ->and($pallet->fulfilmentCustomer->number_pallets)->toBe(1)
        ->and($pallet->fulfilmentCustomer->number_stored_items)->toBe(0);

    return $pallet;
})->depends('create fulfilment shop');
