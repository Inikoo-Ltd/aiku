<?php

/*
 * author Arya Permana - Kirin
 * created on 27-02-2025-14h-44m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Billables\Rental\StoreRental;
use App\Actions\Billables\Rental\UpdateRental;
use App\Actions\Billables\Service\StoreService;
use App\Actions\Catalogue\Product\StoreProduct;
use App\Actions\Catalogue\Shop\StoreShop;
use App\Actions\Fulfilment\FulfilmentCustomer\StoreFulfilmentCustomer;
use App\Actions\Fulfilment\FulfilmentCustomer\UpdateFulfilmentCustomer;
use App\Actions\Fulfilment\FulfilmentTransaction\StoreFulfilmentTransaction;
use App\Actions\Fulfilment\FulfilmentTransaction\UpdateFulfilmentTransaction;
use App\Actions\Fulfilment\Pallet\AttachPalletToReturn;
use App\Actions\Fulfilment\Pallet\BookInPallet;
use App\Actions\Fulfilment\Pallet\SetPalletRental;
use App\Actions\Fulfilment\Pallet\StorePalletCreatedInPalletDelivery;
use App\Actions\Fulfilment\PalletDelivery\ReceivePalletDelivery;
use App\Actions\Fulfilment\PalletDelivery\SetPalletDeliveryAsBookedIn;
use App\Actions\Fulfilment\PalletDelivery\StartBookingPalletDelivery;
use App\Actions\Fulfilment\PalletDelivery\StorePalletDelivery;
use App\Actions\Fulfilment\PalletDelivery\SubmitAndConfirmPalletDelivery;
use App\Actions\Fulfilment\PalletReturn\DispatchPalletReturn;
use App\Actions\Fulfilment\PalletReturn\PickedPalletReturn;
use App\Actions\Fulfilment\PalletReturn\PickingPalletReturn;
use App\Actions\Fulfilment\PalletReturn\StorePalletReturn;
use App\Actions\Fulfilment\PalletReturn\SubmitAndConfirmPalletReturn;
use App\Actions\Fulfilment\RecurringBill\StoreRecurringBill;
use App\Actions\Fulfilment\RecurringBillTransaction\UpdateRecurringBillTransaction;
use App\Actions\Fulfilment\RentalAgreement\StoreRentalAgreement;
use App\Actions\Fulfilment\StoredItem\StoreStoredItem;
use App\Actions\Fulfilment\StoredItem\SyncStoredItemToPallet;
use App\Actions\Inventory\Location\StoreLocation;
use App\Actions\SysAdmin\User\StoreUser;
use App\Actions\Traits\WithGetRecurringBillEndDate;
use App\Enums\Billables\Rental\RentalUnitEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\CRM\Customer\CustomerStateEnum;
use App\Enums\CRM\Customer\CustomerStatusEnum;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletTypeEnum;
use App\Enums\Fulfilment\PalletDelivery\PalletDeliveryStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Enums\Fulfilment\RecurringBill\RecurringBillStatusEnum;
use App\Enums\Fulfilment\RentalAgreement\RentalAgreementBillingCycleEnum;
use App\Enums\Fulfilment\RentalAgreement\RentalAgreementStateEnum;
use App\Models\Billables\Rental;
use App\Models\Billables\Service;
use App\Models\Catalogue\Asset;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\FulfilmentTransaction;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Fulfilment\RecurringBill;
use App\Models\Fulfilment\RentalAgreement;
use App\Models\Fulfilment\RentalAgreementStats;
use App\Models\Helpers\Address;
use App\Models\Inventory\Location;
use App\Models\SysAdmin\Permission;
use App\Models\SysAdmin\Role;
use App\Models\SysAdmin\User;

use function Pest\Laravel\actingAs;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $this->organisation            = createOrganisation();
    $this->adminGuest              = createAdminGuest($this->organisation->group);
    $this->warehouse               = createWarehouse();
    $this->group                   = $this->organisation->group;
    $this->getRecurringBillEndDate = new class () {
        use WithGetRecurringBillEndDate;
    };

    $stocks          = createStocks($this->group);
    $orgStocks       = createOrgStocks($this->organisation, $stocks);
    $this->orgStock1 = $orgStocks[0];
    $this->orgStock2 = $orgStocks[1];

    $location = $this->warehouse->locations()->first();
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

    $user = User::first();
    if (!$user) {
        StoreUser::run(
            $this->adminGuest,
            [
                'username' => 'test',
                'password' => '12345678',
                'status'   => true
            ]
        );
    }
    $this->user = $user;

    $this->adminGuest->refresh();
    Config::set(
        'inertia.testing.page_paths',
        [resource_path('js/Pages/Grp')]
    );
    actingAs($this->adminGuest->getUser());
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

    expect($shop)->toBeInstanceOf(Shop::class)
        ->and($shop->fulfilment)->toBeInstanceOf(Fulfilment::class)
        ->and($organisation->catalogueStats->number_shops)->toBe(1)
        ->and($organisation->catalogueStats->number_shops_type_b2b)->toBe(0)
        ->and($organisation->catalogueStats->number_shops_type_fulfilment)->toBe(1)
        ->and($shopRoles->count())->toBe(0)
        ->and($shopPermissions->count())->toBe(0)
        ->and($fulfilmentRoles->count())->toBe(2)
        ->and($fulfilmentPermissions->count())->toBe(4)
        ->and($warehouseRoles->count())->toBe(8)
        ->and($warehousePermissions->count())->toBe(20);

    $user = $this->adminGuest->getUser();
    $user->refresh();

    expect($user->hasAllRoles(["fulfilment-shop-supervisor-{$shop->fulfilment->id}"]))->toBeTrue()
        ->and($user->hasAllRoles(["shop-admin-$shop->id"]))->toBeFalse()
        ->and($shop->fulfilment->number_warehouses)->toBe(1);


    return $shop->fulfilment;
});

test('create product in fulfilment shop', function (Fulfilment $fulfilment) {
    $orgStocks = [
        $this->orgStock1->id => [
            'quantity' => 1,
        ]
    ];

    $productData = array_merge(
        Product::factory()->definition(),
        [
            'org_stocks' => $orgStocks,
            'price'      => 100,
            'unit'       => 'unit'
        ]
    );

    $product = StoreProduct::make()->action($fulfilment->shop, $productData);

    $product->refresh();

    expect($product)->toBeInstanceOf(Product::class);

    return $product;
})->depends('create fulfilment shop');

test('create services in fulfilment shop', function (Fulfilment $fulfilment) {
    $service1 = StoreService::make()->action(
        $fulfilment->shop,
        [
            'price'                    => 100,
            'unit'                     => 'job',
            'code'                     => 'Ser-01',
            'name'                     => 'Service 1',
            'is_auto_assign'           => true,
            'auto_assign_trigger'      => 'PalletDelivery',
            'auto_assign_subject'      => 'Pallet',
            'auto_assign_subject_type' => 'box'
        ]
    );
    $service2 = StoreService::make()->action(
        $fulfilment->shop,
        [
            'price'                    => 111,
            'unit'                     => 'job',
            'code'                     => 'Ser-02',
            'name'                     => 'Service 2',
            'is_auto_assign'           => true,
            'auto_assign_trigger'      => 'PalletDelivery',
            'auto_assign_subject'      => 'Pallet',
            'auto_assign_subject_type' => 'pallet'
        ]
    );
    StoreService::make()->action(
        $fulfilment->shop,
        [
            'price'          => 200,
            'unit'           => 'job',
            'code'           => 'Ser-03',
            'name'           => 'Service 3',
            'is_auto_assign' => false,
        ]
    );
    StoreService::make()->action(
        $fulfilment->shop,
        [
            'price'          => 300,
            'unit'           => 'job',
            'code'           => 'Ser-04',
            'name'           => 'Service 4',
            'is_auto_assign' => false,
        ]
    );
    StoreService::make()->action(
        $fulfilment->shop,
        [
            'price'          => 400,
            'unit'           => 'job',
            'code'           => 'Ser-05',
            'name'           => 'Service 5',
            'is_auto_assign' => false,
        ]
    );
    StoreService::make()->action(
        $fulfilment->shop,
        [
            'price'                    => 111,
            'unit'                     => 'job',
            'code'                     => 'Ser-06',
            'name'                     => 'Service 6',
            'is_auto_assign'           => true,
            'auto_assign_trigger'      => 'PalletReturn',
            'auto_assign_subject'      => 'Pallet',
            'auto_assign_subject_type' => 'pallet'
        ]
    );


    expect($service1)->toBeInstanceOf(Service::class)
        ->and($service1->asset)->toBeInstanceOf(Asset::class)
        ->and($service2->organisation->catalogueStats->number_assets_type_service)->toBe(6)
        ->and($service2->organisation->catalogueStats->number_assets)->toBe(7)
        ->and($service2->shop->stats->number_assets)->toBe(7)
        ->and($service2->asset->stats->number_historic_assets)->toBe(1);

    return $service1;
})->depends('create fulfilment shop');

test('create rental product to fulfilment shop', function (Fulfilment $fulfilment) {
    $rental = StoreRental::make()->action(
        $fulfilment->shop,
        [
            'price' => 100,
            'unit'  => RentalUnitEnum::WEEK->value,
            'code'  => 'R00001',
            'name'  => 'Rental Asset A',

        ]
    );

    $rental->refresh();


    expect($rental)->toBeInstanceOf(Rental::class)
        ->and($rental->asset)->toBeInstanceOf(Asset::class)
        ->and($rental->organisation->catalogueStats->number_assets)->toBe(8)
        ->and($rental->organisation->catalogueStats->number_assets_type_rental)->toBe(1)
        ->and($rental->shop->stats->number_assets)->toBe(8)
        ->and($rental->asset->stats->number_historic_assets)->toBe(1);

    return $rental;
})->depends('create fulfilment shop');

test('create auto assign rental product to fulfilment shop', function (Fulfilment $fulfilment) {
    $palletRental   = StoreRental::make()->action(
        $fulfilment->shop,
        [
            'price'                  => 100,
            'unit'                   => RentalUnitEnum::WEEK->value,
            'code'                   => 'R00002',
            'name'                   => 'Rental Asset B',
            'auto_assign_asset'      => 'Pallet',
            'auto_assign_asset_type' => PalletTypeEnum::PALLET->value
        ]
    );
    $oversizeRental = StoreRental::make()->action(
        $fulfilment->shop,
        [
            'price'                  => 100,
            'unit'                   => RentalUnitEnum::WEEK->value,
            'code'                   => 'R00003',
            'name'                   => 'Rental Asset C',
            'auto_assign_asset'      => 'Pallet',
            'auto_assign_asset_type' => PalletTypeEnum::OVERSIZE->value
        ]
    );
    $boxRental      = StoreRental::make()->action(
        $fulfilment->shop,
        [
            'price'                  => 100,
            'unit'                   => RentalUnitEnum::WEEK->value,
            'code'                   => 'R00004',
            'name'                   => 'Rental Asset D',
            'auto_assign_asset'      => 'Pallet',
            'auto_assign_asset_type' => PalletTypeEnum::BOX->value
        ]
    );

    $palletRental->refresh();
    $oversizeRental->refresh();
    $boxRental->refresh();


    expect($palletRental)->toBeInstanceOf(Rental::class)
        ->and($palletRental->asset)->toBeInstanceOf(Asset::class)
        ->and($palletRental->organisation->catalogueStats->number_assets)->toBe(11)
        ->and($palletRental->organisation->catalogueStats->number_assets_type_rental)->toBe(4)
        ->and($palletRental->shop->stats->number_assets)->toBe(11)
        ->and($palletRental->asset->stats->number_historic_assets)->toBe(1);

    return $palletRental;
})->depends('create fulfilment shop');


test('update rental', function (Rental $rental) {
    $rentalData = [
        'name'        => 'Updated Rental Name',
        'description' => 'Updated Rental Description',
        'rrp'         => 99.99
    ];
    $rental     = UpdateRental::make()->action(rental: $rental, modelData: $rentalData);


    $rental->refresh();


    expect($rental->asset->name)->toBe('Updated Rental Name')
        ->and($rental->asset->stats->number_historic_assets)->toBe(2)
        ->and($rental->asset->stats->number_historic_assets)->toBe(2);

    return $rental;
})->depends('create rental product to fulfilment shop');

test('create 4th fulfilment customer', function (Fulfilment $fulfilment) {
    $fulfilmentCustomer = StoreFulfilmentCustomer::make()->action(
        $fulfilment,
        [
            'state'           => CustomerStateEnum::ACTIVE,
            'status'          => CustomerStatusEnum::APPROVED,
            'contact_name'    => 'jacqueline',
            'company_name'    => 'ghost.o',
            'interest'        => ['pallets_storage', 'items_storage', 'dropshipping'],
            'contact_address' => Address::factory()->definition(),
        ]
    );

    UpdateFulfilmentCustomer::make()->action(
        $fulfilmentCustomer,
        [
            'pallets_storage' => true,
            'items_storage'   => true,
        ]
    );

    $fulfilment->refresh();

    expect($fulfilmentCustomer)->toBeInstanceOf(FulfilmentCustomer::class)
        ->and($fulfilmentCustomer->customer)->toBeInstanceOf(Customer::class)
        ->and($fulfilmentCustomer->customer->status)->toBe(CustomerStatusEnum::APPROVED)
        ->and($fulfilmentCustomer->customer->state)->toBe(CustomerStateEnum::ACTIVE)
        ->and($fulfilmentCustomer->customer->is_fulfilment)->toBeTrue()
        ->and($fulfilmentCustomer->pallets_storage)->toBeTrue()
        ->and($fulfilmentCustomer->items_storage)->toBeTrue()
        ->and($fulfilmentCustomer->dropshipping)->toBeTrue()
        ->and($fulfilmentCustomer->number_pallets)->toBe(0)
        ->and($fulfilmentCustomer->number_stored_items)->toBe(0)
        ->and($fulfilment->stats->number_customers_interest_items_storage)->toBe(1)
        ->and($fulfilment->stats->number_customers_interest_pallets_storage)->toBe(1)
        ->and($fulfilment->stats->number_customers_interest_dropshipping)->toBe(1);

    return $fulfilmentCustomer;
})->depends('create fulfilment shop');

test('create rental agreement for 4th customer', function (FulfilmentCustomer $fulfilmentCustomer) {
    $rentalAgreement = StoreRentalAgreement::make()->action(
        $fulfilmentCustomer,
        [
            'state'         => RentalAgreementStateEnum::ACTIVE,
            'billing_cycle' => RentalAgreementBillingCycleEnum::MONTHLY,
            'pallets_limit' => null,
            'username'      => 'test',
            'email'         => 'test@testmail.com',
            'clauses'       => [
                'rentals' => [
                    [
                        'asset_id'       => $fulfilmentCustomer->fulfilment->rentals->first()->asset_id,
                        'percentage_off' => 10,
                    ],
                    [
                        'asset_id'       => $fulfilmentCustomer->fulfilment->rentals->last()->asset_id,
                        'percentage_off' => 20,
                    ],
                ]
            ]
        ]
    );
    $rentalAgreement->refresh();
    expect($rentalAgreement)->toBeInstanceOf(RentalAgreement::class)
        ->and($fulfilmentCustomer->rentalAgreement)->toBeInstanceOf(RentalAgreement::class)
        ->and($rentalAgreement->state)->toBe(RentalAgreementStateEnum::ACTIVE)
        ->and($rentalAgreement->stats)->toBeInstanceOf(RentalAgreementStats::class)
        ->and($rentalAgreement->stats->number_rental_agreement_clauses)->toBe(2)
        ->and($rentalAgreement->stats->number_rental_agreement_clauses_type_rental)->toBe(2)
        ->and($rentalAgreement->clauses->first()->asset)->toBeInstanceOf(Asset::class)
        ->and($rentalAgreement->clauses->last()->asset)->toBeInstanceOf(Asset::class)
        ->and($rentalAgreement->stats->number_rental_agreement_snapshots)->toBe(1);

    return $rentalAgreement;
})->depends('create 4th fulfilment customer');


test('setup recurring bill', function (RentalAgreement $rentalAgreement) {
    $recurringBill = StoreRecurringBill::make()->action(
        $rentalAgreement,
        [
            'start_date' => now()
        ]
    );
    $recurringBill->refresh();
    expect($recurringBill)->toBeInstanceOf(RecurringBill::class)
        ->and($recurringBill->status)->toBe(RecurringBillStatusEnum::CURRENT)
        ->and($recurringBill->fulfilmentCustomer->current_recurring_bill_id)->toBe($recurringBill->id);

    return $recurringBill->fulfilmentCustomer;
})->depends('create rental agreement for 4th customer');

test('setup delivery', function (FulfilmentCustomer $fulfilmentCustomer) {
    $recurringBill = $fulfilmentCustomer->currentRecurringBill;

    // Setup Delivery
    $palletDelivery = StorePalletDelivery::make()->action(
        $fulfilmentCustomer,
        []
    );
    expect($palletDelivery)->toBeInstanceOf(PalletDelivery::class);

    // Add Pallets and Stored Items and Transactions
    $pallet      = StorePalletCreatedInPalletDelivery::make()->action(
        $palletDelivery,
        [
            'type' => PalletTypeEnum::PALLET,
        ]
    );
    $pallet2     = StorePalletCreatedInPalletDelivery::make()->action(
        $palletDelivery,
        [
            'type' => PalletTypeEnum::PALLET,
        ]
    );
    $storedItem  = StoreStoredItem::make()->action(
        $fulfilmentCustomer,
        [
            'reference' => 'test',
            'name'      => 'test1'
        ]
    );
    $storedItem2 = StoreStoredItem::make()->action(
        $fulfilmentCustomer,
        [
            'reference' => 'test2',
            'name'      => 'test2'
        ]
    );
    SyncStoredItemToPallet::make()->action(
        $pallet,
        [
            'stored_item_ids' => [
                $storedItem->id => [
                    'quantity' => 50
                ]
            ]
        ]
    );
    SyncStoredItemToPallet::make()->action(
        $pallet2,
        [
            'stored_item_ids' => [
                $storedItem2->id => [
                    'quantity' => 100
                ]
            ]
        ]
    );
    $service = Service::where('is_auto_assign', false)->first();
    StoreFulfilmentTransaction::make()->action(
        $palletDelivery,
        [
            'quantity'          => 10,
            'historic_asset_id' => $service->current_historic_asset_id
        ]
    );

    $product = Product::first();
    StoreFulfilmentTransaction::make()->action(
        $palletDelivery,
        [
            'quantity'          => 10,
            'historic_asset_id' => $product->current_historic_asset_id
        ]
    );

    $palletDelivery->refresh();

    expect($palletDelivery->stats->number_pallets)->toBe(2)
        ->and($palletDelivery->stats->number_pallets_type_pallet)->toBe(2)
        ->and($palletDelivery->stats->number_services)->toBe(2)
        ->and($palletDelivery->stats->number_physical_goods)->toBe(1)
        ->and($recurringBill->stats->number_transactions)->toBe(0);

    return $palletDelivery;
})->depends('setup recurring bill');

test('Delivery state change and recurring bill transaction monitor', function (PalletDelivery $palletDelivery) {
    // State Changes
    $palletDelivery = SubmitAndConfirmPalletDelivery::make()->action($palletDelivery);
    $palletDelivery->refresh();
    $fulfilmentCustomer = $palletDelivery->fulfilmentCustomer;
    $recurringBill      = $fulfilmentCustomer->currentRecurringBill;

    expect($palletDelivery->stats->number_pallets)->toBe(2)
        ->and($palletDelivery->stats->number_pallets_type_pallet)->toBe(2)
        ->and($palletDelivery->stats->number_services)->toBe(2)
        ->and($palletDelivery->stats->number_physical_goods)->toBe(1)
        ->and($recurringBill->stats->number_transactions)->toBe(0);

    $palletDelivery = ReceivePalletDelivery::make()->action($palletDelivery);
    $palletDelivery->refresh();
    $recurringBill      = $palletDelivery->recurringBill;
    $fulfilmentCustomer = $palletDelivery->fulfilmentCustomer;

    expect($palletDelivery->state)->toBe(PalletDeliveryStateEnum::RECEIVED)
        ->and($palletDelivery->recurring_bill_id)->toBe($fulfilmentCustomer->current_recurring_bill_id)
        ->and($recurringBill->stats->number_transactions)->toBe(5);

    // Transactions added in Received and Pallet Rentals Added in Booked in
    $palletDelivery = StartBookingPalletDelivery::make()->action($palletDelivery);
    $palletDelivery->refresh();

    $location = $this->warehouse->locations()->first();
    $rental   = $palletDelivery->fulfilment->rentals->last();
    foreach ($palletDelivery->pallets as $pallet) {
        BookInPallet::make()->action($pallet, ['location_id' => $location->id]);
        SetPalletRental::make()->action($pallet, ['rental_id' => $rental->id]);
    }

    $palletDelivery = SetPalletDeliveryAsBookedIn::make()->action($palletDelivery);
    $palletDelivery->refresh();
    $fulfilmentCustomer = $palletDelivery->fulfilmentCustomer;
    $recurringBill      = $palletDelivery->recurringBill;

    expect($palletDelivery->state)->toBe(PalletDeliveryStateEnum::BOOKED_IN)
        ->and($fulfilmentCustomer->number_pallets_state_storing)->toBe(2)
        ->and($recurringBill->stats->number_transactions)->toBe(5);

    return $palletDelivery;
})->depends('setup delivery');

test('add service after delivery booked in', function (PalletDelivery $palletDelivery) {
    $service = Service::where('is_auto_assign', false)->skip(1)->first();

    $fulfilmentTransaction = StoreFulfilmentTransaction::make()->action($palletDelivery, [
        'quantity'          => 10,
        'historic_asset_id' => $service->current_historic_asset_id
    ]);

    $palletDelivery->refresh();
    $recurringBill = $palletDelivery->recurringBill;
    expect($palletDelivery->stats->number_services)->toBe(3)
        ->and($recurringBill->stats->number_transactions)->toBe(6);

    return $fulfilmentTransaction;
})->depends('Delivery state change and recurring bill transaction monitor');

test('update quantity from pallet delivery and Recurring Bill', function (FulfilmentTransaction $fulfilmentTransaction) {
    $fulfilmentTransaction = UpdateFulfilmentTransaction::make()->action($fulfilmentTransaction, [
        'quantity' => 20
    ]);

    $recurringBillTransaction = $fulfilmentTransaction->recurringBillTransaction;
    expect(intval($fulfilmentTransaction->quantity))->toBe(20)
        ->and(intval($recurringBillTransaction->quantity))->toBe(20);

    $recurringBillTransaction = UpdateRecurringBillTransaction::make()->action($recurringBillTransaction, [
        'quantity' => 15
    ]);

    $fulfilmentTransaction = $recurringBillTransaction->fulfilmentTransaction;
    expect(intval($recurringBillTransaction->quantity))->toBe(15)
        ->and(intval($fulfilmentTransaction->quantity))->toBe(15);

    return $fulfilmentTransaction->fulfilmentCustomer;
})->depends('add service after delivery booked in');

test('setup pallet return (whole pallet)', function (FulfilmentCustomer $fulfilmentCustomer) {
    $palletReturn = StorePalletReturn::make()->action($fulfilmentCustomer, [
        'type' => PalletReturnTypeEnum::PALLET
    ]);

    $palletReturn->refresh();
    $fulfilmentCustomer->refresh();
    $recurringBill = $fulfilmentCustomer->currentRecurringBill;

    expect($recurringBill->stats->number_transactions)->toBe(6)
        ->and($fulfilmentCustomer->number_pallet_returns)->toBe(1);
    $pallet = $fulfilmentCustomer->pallets()->where('state', PalletStateEnum::STORING)->first();

    $palletReturn = AttachPalletToReturn::make()->action(
        $palletReturn,
        $pallet
    );

    $pallet->refresh();
    $palletReturn->refresh();
    $recurringBill->refresh();
    expect($recurringBill->stats->number_transactions)->toBe(7)
        ->and($pallet->state)->toBe(PalletStateEnum::REQUEST_RETURN_IN_PROCESS)
        ->and($palletReturn->stats->number_pallets)->toBe(1)
        ->and($palletReturn->stats->number_services)->toBe(1);


    $palletReturn = SubmitAndConfirmPalletReturn::make()->action($palletReturn);
    $pallet->refresh();
    $palletReturn->refresh();

    expect($pallet->state)->toBe(PalletStateEnum::REQUEST_RETURN_CONFIRMED)
        ->and($palletReturn->state)->toBe(PalletReturnStateEnum::CONFIRMED);

    $palletReturn = PickingPalletReturn::make()->action($palletReturn);
    $pallet->refresh();
    $palletReturn->refresh();

    expect($pallet->state)->toBe(PalletStateEnum::PICKING)
        ->and($palletReturn->state)->toBe(PalletReturnStateEnum::PICKING);

    $palletReturn = PickedPalletReturn::make()->action($palletReturn);
    $pallet->refresh();
    $palletReturn->refresh();

    expect($pallet->state)->toBe(PalletStateEnum::PICKED)
        ->and($palletReturn->state)->toBe(PalletReturnStateEnum::PICKED);

    $palletReturn = DispatchPalletReturn::make()->action($palletReturn);
    $pallet->refresh();
    $palletReturn->refresh();

    expect($pallet->state)->toBe(PalletStateEnum::DISPATCHED)
        ->and($palletReturn->state)->toBe(PalletReturnStateEnum::DISPATCHED);

    return $palletReturn;
})->depends('update quantity from pallet delivery and Recurring Bill');

test('add service after return dispatched', function (PalletReturn $palletReturn) {
    $palletReturn->refresh();
    $recurringBill = $palletReturn->recurringBill;
    expect($palletReturn->stats->number_services)->toBe(1)
        ->and($recurringBill->stats->number_transactions)->toBe(7);

    $service = Service::where('is_auto_assign', false)->skip(1)->first();

    $fulfilmentTransaction = StoreFulfilmentTransaction::make()->action($palletReturn, [
        'quantity'          => 10,
        'historic_asset_id' => $service->current_historic_asset_id
    ]);

    $palletReturn->refresh();
    $recurringBill = $palletReturn->recurringBill;
    expect($palletReturn->stats->number_services)->toBe(2)
        ->and($recurringBill->stats->number_transactions)->toBe(8);

    return $fulfilmentTransaction;
})->depends('setup pallet return (whole pallet)');

test('update quantity from pallet return and Recurring Bill', function (FulfilmentTransaction $fulfilmentTransaction) {
    $fulfilmentTransaction = UpdateFulfilmentTransaction::make()->action($fulfilmentTransaction, [
        'quantity' => 20
    ]);

    $recurringBillTransaction = $fulfilmentTransaction->recurringBillTransaction;
    expect(intval($fulfilmentTransaction->quantity))->toBe(20)
        ->and(intval($recurringBillTransaction->quantity))->toBe(20);

    $recurringBillTransaction = UpdateRecurringBillTransaction::make()->action($recurringBillTransaction, [
        'quantity' => 15
    ]);

    $fulfilmentTransaction = $recurringBillTransaction->fulfilmentTransaction;
    expect(intval($recurringBillTransaction->quantity))->toBe(15)
        ->and(intval($fulfilmentTransaction->quantity))->toBe(15);

    return $fulfilmentTransaction->fulfilmentCustomer;
})->depends('add service after return dispatched');
