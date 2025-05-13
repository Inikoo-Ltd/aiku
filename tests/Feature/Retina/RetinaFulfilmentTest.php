<?php

/*
 * author Arya Permana - Kirin
 * created on 17-01-2025-10h-33m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Billables\Rental\StoreRental;
use App\Actions\Billables\Service\StoreService;
use App\Actions\Catalogue\Shop\UpdateShop;
use App\Actions\CRM\Customer\AttachCustomerToPlatform;
use App\Actions\Fulfilment\Pallet\BookInPallet;
use App\Actions\Fulfilment\PalletDelivery\ConfirmPalletDelivery;
use App\Actions\Fulfilment\PalletDelivery\ReceivePalletDelivery;
use App\Actions\Fulfilment\PalletDelivery\SetPalletDeliveryAsBookedIn;
use App\Actions\Fulfilment\PalletDelivery\StartBookingPalletDelivery;
use App\Actions\Fulfilment\RentalAgreement\StoreRentalAgreement;
use App\Actions\Inventory\Location\StoreLocation;
use App\Actions\Retina\CRM\DeleteRetinaCustomerDeliveryAddress;
use App\Actions\Retina\CRM\StoreRetinaCustomerClient;
use App\Actions\Retina\CRM\UpdateRetinaCustomerDeliveryAddress;
use App\Actions\Retina\Fulfilment\FulfilmentTransaction\DeleteRetinaFulfilmentTransaction;
use App\Actions\Retina\Fulfilment\FulfilmentTransaction\StoreRetinaFulfilmentTransaction;
use App\Actions\Retina\Fulfilment\FulfilmentTransaction\UpdateRetinaFulfilmentTransaction;
use App\Actions\Retina\Fulfilment\Pallet\DeleteRetinaPallet;
use App\Actions\Retina\Fulfilment\Pallet\ImportRetinaPallet;
use App\Actions\Retina\Fulfilment\Pallet\StoreRetinaMultiplePalletsFromDelivery;
use App\Actions\Retina\Fulfilment\Pallet\StoreRetinaPalletFromDelivery;
use App\Actions\Retina\Fulfilment\Pallet\UpdateRetinaPallet;
use App\Actions\Retina\Fulfilment\PalletDelivery\Pdf\PdfRetinaPalletDelivery;
use App\Actions\Retina\Fulfilment\PalletDelivery\StoreRetinaPalletDelivery;
use App\Actions\Retina\Fulfilment\PalletDelivery\SubmitRetinaPalletDelivery;
use App\Actions\Retina\Fulfilment\PalletDelivery\UpdateRetinaPalletDelivery;
use App\Actions\Retina\Fulfilment\PalletReturn\AttachRetinaPalletToReturn;
use App\Actions\Retina\Fulfilment\PalletReturn\CancelRetinaPalletReturn;
use App\Actions\Retina\Fulfilment\PalletReturn\DetachRetinaPalletFromReturn;
use App\Actions\Retina\Fulfilment\PalletReturn\ImportRetinaPalletReturnItem;
use App\Actions\Retina\Fulfilment\PalletReturn\StoreRetinaPalletReturn;
use App\Actions\Retina\Fulfilment\PalletReturn\StoreRetinaStoredItemsToReturn;
use App\Actions\Retina\Fulfilment\PalletReturn\SubmitRetinaPalletReturn;
use App\Actions\Retina\Fulfilment\PalletReturn\UpdateRetinaPalletReturn;
use App\Actions\Retina\Fulfilment\StoredItem\StoreRetinaStoredItem;
use App\Actions\Retina\Fulfilment\StoredItem\SyncRetinaStoredItemToPallet;
use App\Actions\Retina\SysAdmin\AddRetinaDeliveryAddressToFulfilmentCustomer;
use App\Actions\Retina\SysAdmin\DeleteRetinaWebUser;
use App\Actions\Retina\SysAdmin\StoreRetinaWebUser;
use App\Actions\Retina\SysAdmin\UpdateRetinaCustomer;
use App\Actions\Retina\SysAdmin\UpdateRetinaWebUser;
use App\Actions\Retina\UI\Profile\UpdateRetinaProfile;
use App\Actions\Web\Website\LaunchWebsite;
use App\Actions\Web\Website\UI\DetectWebsiteFromDomain;
use App\Enums\Billables\Rental\RentalStateEnum;
use App\Enums\Billables\Rental\RentalUnitEnum;
use App\Enums\Billables\Service\ServiceStateEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletTypeEnum;
use App\Enums\Fulfilment\PalletDelivery\PalletDeliveryStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Enums\Fulfilment\RentalAgreement\RentalAgreementBillingCycleEnum;
use App\Enums\Fulfilment\RentalAgreement\RentalAgreementStateEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Enums\Web\Website\WebsiteStateEnum;
use App\Models\Billables\Rental;
use App\Models\Billables\Service;
use App\Models\CRM\Customer;
use App\Models\CRM\WebUser;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\Platform;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\FulfilmentTransaction;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Fulfilment\RentalAgreement;
use App\Models\Fulfilment\StoredItem;
use App\Models\Helpers\Address;
use App\Models\Helpers\Upload;
use App\Models\Inventory\Location;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

beforeAll(function () {
    loadDB();
});
beforeEach(function () {
    $this->organisation = createOrganisation();
    $this->warehouse    = createWarehouse();
    $this->fulfilment   = createFulfilment($this->organisation);
    $this->website      = createWebsite($this->fulfilment->shop);
    if ($this->website->state != WebsiteStateEnum::LIVE) {
        LaunchWebsite::make()->action($this->website);
    }
    $this->shop               = $this->fulfilment->shop;
    $this->shop               = UpdateShop::make()->action($this->shop, ['state' => ShopStateEnum::OPEN]);
    $this->customer           = createCustomer($this->shop);
    $this->fulfilmentCustomer = $this->customer->fulfilmentCustomer;

    list(
        $this->tradeUnit,
        $this->product
    ) = createProduct($this->shop);

    $rental = Rental::first();
    if (!$rental) {
        data_set($storeData, 'code', 'TEST');
        data_set($storeData, 'state', RentalStateEnum::ACTIVE);
        data_set($storeData, 'name', 'test');
        data_set($storeData, 'price', 100);
        data_set($storeData, 'unit', RentalUnitEnum::DAY->value);

        $rental = StoreRental::make()->action(
            $this->shop,
            $storeData
        );
    }

    $this->rental = $rental;

    $service = Service::first();
    if (!$service) {
        data_set($storeData, 'code', 'TEST');
        data_set($storeData, 'state', ServiceStateEnum::ACTIVE);
        data_set($storeData, 'name', 'test');
        data_set($storeData, 'price', 100);
        data_set($storeData, 'unit', RentalUnitEnum::DAY->value);

        $service = StoreService::make()->action(
            $this->shop,
            $storeData
        );
    }

    $this->service = $service;


    $rentalAgreement = RentalAgreement::where('fulfilment_customer_id', $this->customer->fulfilmentCustomer->id)->first();
    if (!$rentalAgreement) {
        data_set($storeData, 'billing_cycle', RentalAgreementBillingCycleEnum::MONTHLY);
        data_set($storeData, 'state', RentalAgreementStateEnum::ACTIVE);
        data_set($storeData, 'username', 'test');
        data_set($storeData, 'email', 'test@aiku.io');


        $rentalAgreement = StoreRentalAgreement::make()->action(
            $this->customer->fulfilmentCustomer,
            $storeData,
        );
    }
    $this->rentalAgreement = $rentalAgreement;

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
    $this->location = $location;

    $this->webUser = createWebUser($this->customer);

    $palletRental         = StoreRental::make()->action(
        $this->fulfilment->shop,
        [
            'price'                  => 100,
            'unit'                   => RentalUnitEnum::WEEK->value,
            'code'                   => 'R00002',
            'name'                   => 'Rental Asset B',
            'auto_assign_asset'      => 'Pallet',
            'auto_assign_asset_type' => PalletTypeEnum::PALLET->value
        ]
    );
    $this->palletRental   = $palletRental;
    $oversizeRental       = StoreRental::make()->action(
        $this->fulfilment->shop,
        [
            'price'                  => 100,
            'unit'                   => RentalUnitEnum::WEEK->value,
            'code'                   => 'R00003',
            'name'                   => 'Rental Asset C',
            'auto_assign_asset'      => 'Pallet',
            'auto_assign_asset_type' => PalletTypeEnum::OVERSIZE->value
        ]
    );
    $this->oversizeRental = $oversizeRental;
    $boxRental            = StoreRental::make()->action(
        $this->fulfilment->shop,
        [
            'price'                  => 100,
            'unit'                   => RentalUnitEnum::WEEK->value,
            'code'                   => 'R00004',
            'name'                   => 'Rental Asset D',
            'auto_assign_asset'      => 'Pallet',
            'auto_assign_asset_type' => PalletTypeEnum::BOX->value
        ]
    );
    $this->boxRental      = $boxRental;

    Config::set(
        'inertia.testing.page_paths',
        [resource_path('js/Pages/Retina')]
    );

    DetectWebsiteFromDomain::shouldRun()->with('localhost')->andReturn($this->website);
    actingAs($this->webUser);
});

test('Update Retina Profile', function () {
    $webUser = UpdateRetinaProfile::make()->action($this->webUser, [
        'username' => 'joko',
        'about'    => 'decent human being'
    ]);

    expect($webUser)->toBeInstanceOf(WebUser::class)
        ->and($webUser->username)->toBe('joko')
        ->and($webUser->about)->toBe('decent human being');
});

test('Create Retina Pallet Delivery', function () {
    $this->withoutExceptionHandling();
    $fulfilmentCustomer = $this->fulfilmentCustomer;
    $palletDelivery     = StoreRetinaPalletDelivery::make()->action($fulfilmentCustomer, []);

    $fulfilmentCustomer->refresh();

    expect($palletDelivery)->toBeInstanceOf(PalletDelivery::class)
        ->and($fulfilmentCustomer->number_pallet_deliveries)->toBe(1);

    return $palletDelivery;
});

test('Add Retina Pallet to PalletDelivery', function (PalletDelivery $palletDelivery) {
    $pallet = StoreRetinaPalletFromDelivery::make()->action($palletDelivery, []);

    $pallet->refresh();

    expect($pallet)->toBeInstanceOf(Pallet::class)
        ->and($pallet->fulfilmentCustomer->number_pallets)->toBe(1);

    return $pallet;
})->depends('Create Retina Pallet Delivery');

test('Create Stored Item', function () {
    $storedItem = StoreRetinaStoredItem::make()->action(
        $this->fulfilmentCustomer,
        [
            'reference' => 'item1'
        ]
    );

    $storedItem2 = StoreRetinaStoredItem::make()->action(
        $this->fulfilmentCustomer,
        [
            'reference' => 'item2'
        ]
    );

    $storedItem3 = StoreRetinaStoredItem::make()->action(
        $this->fulfilmentCustomer,
        [
            'reference' => 'item3'
        ]
    );

    $storedItem->refresh();
    $storedItem2->refresh();
    $storedItem3->refresh();

    expect($storedItem)->toBeInstanceOf(StoredItem::class)
        ->and($storedItem2)->toBeInstanceOf(StoredItem::class)
        ->and($storedItem3)->toBeInstanceOf(StoredItem::class)
        ->and($storedItem->fulfilmentCustomer->number_stored_items)->toBe(3);

    return $storedItem;
});

test('Sync Stored Item to Pallet', function (Pallet $pallet) {
    SyncRetinaStoredItemToPallet::make()->action(
        $pallet,
        [
            'stored_item_ids' => [
                1 => [
                    'quantity' => 100
                ]
            ]
        ]
    );

    $pallet->refresh();

    expect($pallet)->toBeInstanceOf(Pallet::class)
        ->and($pallet->number_stored_items)->toBe(1);


    return $pallet;
})->depends('Add Retina Pallet to PalletDelivery');

test('Add Retina Multiple Pallets to Pallet Delivery', function (PalletDelivery $palletDelivery) {
    StoreRetinaMultiplePalletsFromDelivery::make()->action($palletDelivery, [
        'number_pallets' => 9,
        'type'           => PalletTypeEnum::PALLET
    ]);

    $palletDelivery->refresh();

    expect($palletDelivery)->toBeInstanceOf(PalletDelivery::class)
        ->and($palletDelivery->fulfilmentCustomer->number_pallets)->toBe(10);

    return $palletDelivery;
})->depends('Create Retina Pallet Delivery');

test('Delete Retina Pallet', function (PalletDelivery $palletDelivery) {
    $pallet = $palletDelivery->pallets()->skip(1)->first();
    DeleteRetinaPallet::make()->action($pallet);

    $palletDelivery->refresh();

    expect($palletDelivery)->toBeInstanceOf(PalletDelivery::class)
        ->and($palletDelivery->fulfilmentCustomer->number_pallets)->toBe(9);

    return $palletDelivery;
})->depends('Create Retina Pallet Delivery');

test('Sync Stored Item to Pallet (again)', function (PalletDelivery $palletDelivery) {
    $pallet  = $palletDelivery->pallets()->skip(1)->first();
    $pallet2 = $palletDelivery->pallets()->skip(2)->first();
    SyncRetinaStoredItemToPallet::make()->action(
        $pallet,
        [
            'stored_item_ids' => [
                2 => [
                    'quantity' => 200
                ]
            ]
        ]
    );
    SyncRetinaStoredItemToPallet::make()->action(
        $pallet2,
        [
            'stored_item_ids' => [
                3 => [
                    'quantity' => 300
                ]
            ]
        ]
    );

    $pallet->refresh();
    $pallet2->refresh();

    expect($pallet)->toBeInstanceOf(Pallet::class)
        ->and($pallet->number_stored_items)->toBe(1)
        ->and($pallet2)->toBeInstanceOf(Pallet::class)
        ->and($pallet2->number_stored_items)->toBe(1);

    return $pallet;
})->depends('Add Retina Multiple Pallets to Pallet Delivery');

test('Update Retina Pallet Delivery', function (PalletDelivery $palletDelivery) {
    $palletDelivery = UpdateRetinaPalletDelivery::make()->action($palletDelivery, [
        'customer_notes' => 'This and That',
    ]);

    $palletDelivery->refresh();

    expect($palletDelivery)->toBeInstanceOf(PalletDelivery::class)
        ->and($palletDelivery->customer_notes)->toBe('This and That');

    return $palletDelivery;
})->depends('Create Retina Pallet Delivery');

test('Store Retina Fulfilment Transaction in Pallet Delivery', function (PalletDelivery $palletDelivery) {
    $fulfilmentTransaction = StoreRetinaFulfilmentTransaction::make()->action($palletDelivery, [
        'historic_asset_id' => $this->product->current_historic_asset_id,
        'quantity'          => 5
    ]);

    $palletDelivery->refresh();
    $fulfilmentTransaction->refresh();
    expect($palletDelivery)->toBeInstanceOf(PalletDelivery::class)
        ->and($palletDelivery->stats->number_physical_goods)->toBe(1);

    return $fulfilmentTransaction;
})->depends('Create Retina Pallet Delivery');

test('Update Retina Fulfilment Transaction in Pallet Delivery', function (FulfilmentTransaction $fulfilmentTransaction) {
    $fulfilmentTransaction = UpdateRetinaFulfilmentTransaction::make()->action($fulfilmentTransaction, [
        'quantity' => 7
    ]);

    $fulfilmentTransaction->refresh();

    expect($fulfilmentTransaction)->toBeInstanceOf(FulfilmentTransaction::class)
        ->and(intval($fulfilmentTransaction->quantity))->toBe(7);

    return $fulfilmentTransaction;
})->depends('Store Retina Fulfilment Transaction in Pallet Delivery');

test('Delete Retina Fulfilment Transaction in Pallet Delivery', function (FulfilmentTransaction $fulfilmentTransaction) {
    /** @var PalletDelivery $parent */
    $parent = $fulfilmentTransaction->parent;
    DeleteRetinaFulfilmentTransaction::make()->action($fulfilmentTransaction);

    $parent->refresh();
    expect($parent)->toBeInstanceOf(PalletDelivery::class)
        ->and($parent->stats->number_physical_goods)->toBe(0);

    return $parent;
})->depends('Update Retina Fulfilment Transaction in Pallet Delivery');

test('Import Pallet (xlsx) for Pallet Delivery', function (PalletDelivery $palletDelivery) {
    Storage::fake('local');

    $tmpPath = 'tmp/uploads/';

    $filePath = base_path('tests/fixtures/PalletsOnlyTest.xlsx');
    $file     = new UploadedFile($filePath, 'PalletsOnlyTest.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    Storage::fake('local')->put($tmpPath, $file);

    expect($palletDelivery->stats->number_pallets)->toBe(9);

    $upload = ImportRetinaPallet::run($palletDelivery, $file, [
        'with_stored_item' => false
    ]);
    $palletDelivery->refresh();
    expect($upload)->toBeInstanceOf(Upload::class)
        ->and($upload->number_rows)->toBe(3)
        ->and($upload->number_success)->toBe(3)
        ->and($upload->number_fails)->toBe(0)
        ->and($palletDelivery->stats->number_pallets)->toBe(12)
        ->and($palletDelivery->stats->number_pallets_type_pallet)->toBe(10)
        ->and($palletDelivery->stats->number_pallets_type_box)->toBe(1)
        ->and($palletDelivery->stats->number_pallets_type_oversize)->toBe(1);

    return $palletDelivery;
})->depends('Create Retina Pallet Delivery');

test('Import Pallet (xlsx) for Pallet Delivery Duplicate', function (PalletDelivery $palletDelivery) {
    Storage::fake('local');

    $tmpPath = 'tmp/uploads/';

    $filePath = base_path('tests/fixtures/PalletsOnlyTest.xlsx');
    $file     = new UploadedFile($filePath, 'PalletsOnlyTest.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    Storage::fake('local')->put($tmpPath, $file);

    expect($palletDelivery->stats->number_pallets)->toBe(12)
    ->and($palletDelivery->stats->number_pallets_type_pallet)->toBe(10)
    ->and($palletDelivery->stats->number_pallets_type_box)->toBe(1)
    ->and($palletDelivery->stats->number_pallets_type_oversize)->toBe(1);

    $upload = ImportRetinaPallet::run($palletDelivery, $file, [
        'with_stored_item' => false
    ]);
    $palletDelivery->refresh();
    expect($upload)->toBeInstanceOf(Upload::class)
        ->and($upload->number_rows)->toBe(3)
        ->and($upload->number_success)->toBe(0)
        ->and($upload->number_fails)->toBe(3)
        ->and($palletDelivery->stats->number_pallets)->toBe(12)
        ->and($palletDelivery->stats->number_pallets_type_pallet)->toBe(10)
        ->and($palletDelivery->stats->number_pallets_type_box)->toBe(1)
        ->and($palletDelivery->stats->number_pallets_type_oversize)->toBe(1);

    return $palletDelivery;
})->depends('Import Pallet (xlsx) for Pallet Delivery');

test('Import Pallet (xlsx) for Pallet Delivery Invalid Types', function (PalletDelivery $palletDelivery) {
    Storage::fake('local');

    $tmpPath = 'tmp/uploads/';

    $filePath = base_path('tests/fixtures/PalletsOnlyTestInvalidType.xlsx');
    $file     = new UploadedFile($filePath, 'PalletsOnlyTestInvalidType.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    Storage::fake('local')->put($tmpPath, $file);

    expect($palletDelivery->stats->number_pallets)->toBe(12)
    ->and($palletDelivery->stats->number_pallets_type_pallet)->toBe(10)
    ->and($palletDelivery->stats->number_pallets_type_box)->toBe(1)
    ->and($palletDelivery->stats->number_pallets_type_oversize)->toBe(1);

    $upload = ImportRetinaPallet::run($palletDelivery, $file, [
        'with_stored_item' => false
    ]);
    $palletDelivery->refresh();
    expect($upload)->toBeInstanceOf(Upload::class)
        ->and($upload->number_rows)->toBe(3)
        ->and($upload->number_success)->toBe(3)
        ->and($upload->number_fails)->toBe(0)
        ->and($palletDelivery->stats->number_pallets)->toBe(15)
        ->and($palletDelivery->stats->number_pallets_type_pallet)->toBe(13)
        ->and($palletDelivery->stats->number_pallets_type_box)->toBe(1)
        ->and($palletDelivery->stats->number_pallets_type_oversize)->toBe(1);

    return $palletDelivery;
})->depends('Import Pallet (xlsx) for Pallet Delivery Duplicate');

test('Submit Retina Pallet Delivery', function (PalletDelivery $palletDelivery) {
    $palletDelivery     = SubmitRetinaPalletDelivery::make()->action($palletDelivery, []);
    $fulfilmentCustomer = $palletDelivery->fulfilmentCustomer;
    $palletDelivery->refresh();
    $fulfilmentCustomer->refresh();

    expect($palletDelivery)->toBeInstanceOf(PalletDelivery::class)
        ->and($palletDelivery->state)->toBe(PalletDeliveryStateEnum::SUBMITTED)
        ->and($fulfilmentCustomer->number_pallet_deliveries_state_submitted)->toBe(1);

    return $palletDelivery;
})->depends('Create Retina Pallet Delivery');

test('Generate Pallet Delivery PDF', function (PalletDelivery $palletDelivery) {
    $pdf = PdfRetinaPalletDelivery::run($palletDelivery);
    expect($pdf->output())->toBeString();

    return $palletDelivery;
})->depends('Submit Retina Pallet Delivery');

test('Process Pallet Delivery (from aiku)', function (PalletDelivery $palletDelivery) {
    $palletDelivery = ConfirmPalletDelivery::make()->action($palletDelivery);

    expect($palletDelivery)->toBeInstanceOf(PalletDelivery::class)
        ->and($palletDelivery->state)->toBe(PalletDeliveryStateEnum::CONFIRMED);


    $palletDelivery = ReceivePalletDelivery::make()->action($palletDelivery);

    expect($palletDelivery)->toBeInstanceOf(PalletDelivery::class)
        ->and($palletDelivery->state)->toBe(PalletDeliveryStateEnum::RECEIVED);


    $palletDelivery = StartBookingPalletDelivery::make()->action($palletDelivery);

    expect($palletDelivery)->toBeInstanceOf(PalletDelivery::class)
        ->and($palletDelivery->state)->toBe(PalletDeliveryStateEnum::BOOKING_IN);



    foreach ($palletDelivery->pallets as $pallet) {
        BookInPallet::make()->action($pallet, ['location_id' => $this->location->id]);
    }




    $palletDelivery = SetPalletDeliveryAsBookedIn::make()->action($palletDelivery);
    $pallet = $palletDelivery->pallets->first();

    expect($pallet->location)->toBeInstanceOf(Location::class)
        ->and($pallet->location->id)->toBe($this->location->id)
        ->and($pallet->state)->toBe(PalletStateEnum::STORING);




    return $palletDelivery;
})->depends('Submit Retina Pallet Delivery');

test('Update Pallet', function (PalletDelivery $palletDelivery) {
    $pallet = $palletDelivery->pallets->first();
    $pallet = UpdateRetinaPallet::make()->action(
        $pallet,
        [
            'reference'          => '000001-diam-p0001',
            'customer_reference' => 'hello-01',
        ]
    );

    $pallet->refresh();

    expect($pallet)->toBeInstanceOf(Pallet::class)
        ->and($pallet->reference)->toBe('000001-diam-p0001')
        ->and($pallet->customer_reference)->toBe('hello-01');

    return $pallet;
})->depends('Process Pallet Delivery (from aiku)');

test('Create Retina Pallet Return', function () {
    $fulfilmentCustomer = $this->fulfilmentCustomer;
    $palletReturn       = StoreRetinaPalletReturn::make()->action(
        $fulfilmentCustomer,
        [
            'warehouse_id' => $this->warehouse->id,
        ]
    );

    $fulfilmentCustomer->refresh();

    expect($palletReturn)->toBeInstanceOf(PalletReturn::class)
        ->and($palletReturn->state)->toBe(PalletReturnStateEnum::IN_PROCESS)
        ->and($fulfilmentCustomer->number_pallet_returns)->toBe(1);

    return $palletReturn;
});

test('Update Retina Pallet Return', function (PalletReturn $palletReturn) {
    $palletReturn = UpdateRetinaPalletReturn::make()->action(
        $palletReturn,
        [
            'public_notes' => 'this notes',
        ]
    );

    $palletReturn->refresh();

    expect($palletReturn)->toBeInstanceOf(PalletReturn::class)
        ->and($palletReturn->public_notes)->toBe('this notes');

    return $palletReturn;
})->depends('Create Retina Pallet Return');

test('import pallets in return (xlsx)  whole pallets ', function (PalletReturn $palletReturn) {
    Storage::fake('local');

    $tmpPath = 'tmp/uploads/';
    //
    $filePath = base_path('tests/fixtures/returnRetinaPalletItems.xlsx');
    $file     = new UploadedFile($filePath, 'returnRetinaPalletItems.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    Storage::fake('local')->put($tmpPath, $file);
    $palletReturn->refresh();
    expect($palletReturn->pallets()->count())->toBe(0)
        ->and($palletReturn->stats->number_pallets)->toBe(0);
    $upload = ImportRetinaPalletReturnItem::run($palletReturn, $file);
    $palletReturn->refresh();

    expect($upload)->toBeInstanceOf(Upload::class)
        ->and($upload->model)->toBe('PalletReturnItem')
        ->and($upload->original_filename)->toBe('returnRetinaPalletItems.xlsx')
        ->and($upload->number_rows)->toBe(1)
        ->and($upload->number_success)->toBe(1)
        ->and($upload->number_fails)->toBe(0)
        ->and($palletReturn->pallets()->count())->toBe(1)
        ->and($palletReturn->stats->number_pallets)->toBe(1);

    return $palletReturn;
})->depends('Create Retina Pallet Return');

test('import pallets in return (xlsx) duplicate', function (PalletReturn $palletReturn) {
    Storage::fake('local');

    $tmpPath = 'tmp/uploads/';
    //
    $filePath = base_path('tests/fixtures/returnRetinaPalletItems.xlsx');
    $file     = new UploadedFile($filePath, 'returnRetinaPalletItems.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    Storage::fake('local')->put($tmpPath, $file);
    $palletReturn->refresh();
    expect($palletReturn->pallets()->count())->toBe(1)
        ->and($palletReturn->stats->number_pallets)->toBe(1);
    $upload = ImportRetinaPalletReturnItem::run($palletReturn, $file);
    $palletReturn->refresh();

    expect($upload)->toBeInstanceOf(Upload::class)
        ->and($upload->model)->toBe('PalletReturnItem')
        ->and($upload->original_filename)->toBe('returnRetinaPalletItems.xlsx')
        ->and($upload->number_rows)->toBe(1)
        ->and($upload->number_success)->toBe(0)
        ->and($upload->number_fails)->toBe(1)
        ->and($palletReturn->pallets()->count())->toBe(1)
        ->and($palletReturn->stats->number_pallets)->toBe(1);

    return $palletReturn;
})->depends('Create Retina Pallet Return');

test('import pallets in return (xlsx) invalid reference', function (PalletReturn $palletReturn) {
    Storage::fake('local');

    $tmpPath = 'tmp/uploads/';
    //
    $filePath = base_path('tests/fixtures/PalletReturnInvalid.xlsx');
    $file     = new UploadedFile($filePath, 'PalletReturnInvalid.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    Storage::fake('local')->put($tmpPath, $file);
    $palletReturn->refresh();
    expect($palletReturn->pallets()->count())->toBe(1)
        ->and($palletReturn->stats->number_pallets)->toBe(1);
    $upload = ImportRetinaPalletReturnItem::run($palletReturn, $file);
    $palletReturn->refresh();

    expect($upload)->toBeInstanceOf(Upload::class)
        ->and($upload->model)->toBe('PalletReturnItem')
        ->and($upload->original_filename)->toBe('PalletReturnInvalid.xlsx')
        ->and($upload->number_rows)->toBe(1)
        ->and($upload->number_success)->toBe(0)
        ->and($upload->number_fails)->toBe(1)
        ->and($palletReturn->pallets()->count())->toBe(1)
        ->and($palletReturn->stats->number_pallets)->toBe(1);

    return $palletReturn;
})->depends('Create Retina Pallet Return');

test('Attach Pallet to Retina Pallet Return', function (PalletReturn $palletReturn) {
    $pallet1 = Pallet::Find(3);
    $pallet2 = Pallet::Find(4);
    $palletReturn = AttachRetinaPalletToReturn::make()->action(
        $palletReturn, $pallet1
    );

    $palletReturn->refresh();

    $palletReturn = AttachRetinaPalletToReturn::make()->action(
        $palletReturn, $pallet2
    );
    $palletReturn->refresh();

    expect($palletReturn)->toBeInstanceOf(PalletReturn::class)
        ->and($palletReturn->stats->number_pallets)->toBe(3);

    return $palletReturn;
})->depends('import pallets in return (xlsx)  whole pallets ');


test('Detach Pallet to Retina Pallet Return', function (PalletReturn $palletReturn) {
    $pallet = $palletReturn->pallets()->first();
    DetachRetinaPalletFromReturn::make()->action(
        $palletReturn,
        $pallet,
        []
    );

    $palletReturn->refresh();

    expect($palletReturn)->toBeInstanceOf(PalletReturn::class)
        ->and($palletReturn->stats->number_pallets)->toBe(2);

    return $palletReturn;
})->depends('Attach Pallet to Retina Pallet Return');

test('Add Transaction to Retina Pallet Return', function (PalletReturn $palletReturn) {
    $fulfilmentTransaction = StoreRetinaFulfilmentTransaction::make()->action(
        $palletReturn,
        [
            'quantity'          => 10,
            'historic_asset_id' => $this->product->current_historic_asset_id
        ]
    );

    $fulfilmentTransaction->refresh();
    $palletReturn->refresh();
    expect($fulfilmentTransaction)->toBeInstanceOf(FulfilmentTransaction::class)
        ->and(intval($fulfilmentTransaction->quantity))->toBe(10)
        ->and($palletReturn->stats->number_physical_goods)->toBe(1);

    return $fulfilmentTransaction;
})->depends('Attach Pallet to Retina Pallet Return');

test('Submit Retina Pallet Return', function (PalletReturn $palletReturn) {
    $palletReturn = SubmitRetinaPalletReturn::make()->action(
        $palletReturn,
        []
    );

    $palletReturn->refresh();

    expect($palletReturn)->toBeInstanceOf(PalletReturn::class)
        ->and($palletReturn->state)->toBe(PalletReturnStateEnum::SUBMITTED);

    return $palletReturn;
})->depends('Detach Pallet to Retina Pallet Return');

test('Cancel Retina Pallet Return', function (PalletReturn $palletReturn) {
    $palletReturn = CancelRetinaPalletReturn::make()->action(
        $palletReturn,
        []
    );

    $palletReturn->refresh();

    expect($palletReturn)->toBeInstanceOf(PalletReturn::class)
        ->and($palletReturn->state)->toBe(PalletReturnStateEnum::CANCEL);

    return $palletReturn;
})->depends('Submit Retina Pallet Return');

test('Create Retina Pallet Return (with stored item)', function () {
    $fulfilmentCustomer = $this->fulfilmentCustomer;

    $palletReturn = StoreRetinaPalletReturn::make()->action(
        $fulfilmentCustomer,
        [
            'warehouse_id' => $this->warehouse->id,
        ],
        withStoredItems: true
    );

    $fulfilmentCustomer->refresh();

    expect($palletReturn)->toBeInstanceOf(PalletReturn::class)
        ->and($palletReturn->state)->toBe(PalletReturnStateEnum::IN_PROCESS)
        ->and($palletReturn->type)->toBe(PalletReturnTypeEnum::STORED_ITEM);

    return $palletReturn;
})->depends('Create Retina Pallet Return');

test('Attach Stored Item to Retina Pallet Return (with stored item)', function (PalletReturn $palletReturn) {
    $palletReturn = StoreRetinaStoredItemsToReturn::make()->action(
        $palletReturn,
        [
            'stored_items' => [
                2 => [
                    'quantity' => 200
                ]
            ]
        ]
    );

    $palletReturn->refresh();

    expect($palletReturn)->toBeInstanceOf(PalletReturn::class)
        ->and($palletReturn->stats->number_stored_items)->toBe(1);

    return $palletReturn;
})->depends('Create Retina Pallet Return (with stored item)');

test('Update Retina Customer', function () {
    $customer = UpdateRetinaCustomer::make()->action(
        $this->fulfilmentCustomer->customer,
        [
            'contact_name' => 'John'
        ]
    );

    $customer->refresh();

    expect($customer)->toBeInstanceOf(Customer::class)
        ->and($customer->contact_name)->toBe('John');

    return $customer;
});

test('Store retina delivery address to fulfilment customer', function () {
    $fulfilmentCustomer = AddRetinaDeliveryAddressToFulfilmentCustomer::make()->action(
        $this->fulfilmentCustomer,
        [
            'delivery_address' => Address::factory()->definition()
        ]
    );

    $fulfilmentCustomer->refresh();

    expect($fulfilmentCustomer)->toBeInstanceOf(FulfilmentCustomer::class)
        ->and($fulfilmentCustomer->customer->addresses()->count())->toBe(2)
        ->and($fulfilmentCustomer->customer->deliveryAddress)->not->toBeNull();

    return $fulfilmentCustomer;
});

test('Update retina customer delivery address', function () {
    $customer = UpdateRetinaCustomerDeliveryAddress::make()->action(
        $this->fulfilmentCustomer->customer,
        [
            'delivery_address_id' => 6
        ]
    );

    $customer->refresh();

    expect($customer)->toBeInstanceOf(Customer::class)
        ->and($customer->delivery_address_id)->toBe(6);

    return $customer;
});

test('Delete retina customer delivery address', function () {
    UpdateRetinaCustomerDeliveryAddress::make()->action(
        $this->fulfilmentCustomer->customer,
        [
            'delivery_address_id' => 5
        ]
    );

    $customer = DeleteRetinaCustomerDeliveryAddress::make()->action(
        $this->fulfilmentCustomer->customer,
        $this->fulfilmentCustomer->customer->addresses()->skip(1)->first()
    );

    $customer->refresh();

    expect($customer)->toBeInstanceOf(Customer::class)
        ->and($customer->addresses()->count())->toBe(1);

    return $customer;
});

test('Store retina customer client', function () {
    $customer = $this->fulfilmentCustomer->customer;
    $platform = $customer->getMainPlatform();
    if (!$platform) {
        $platform       = Platform::where('type', PlatformTypeEnum::MANUAL)->first();
        AttachCustomerToPlatform::make()->action($customer, $platform, []);
    }

    $customerClient = StoreRetinaCustomerClient::make()->action(
        $this->fulfilmentCustomer->customer,
        [
            'reference'    => 'ref1',
            'contact_name' => 'Acme',
            'company_name' => 'Acme.inc',
            'email'        => 'acme@acme.com',
            'phone'        => '123456789',
            'address'      => Address::factory()->definition(),
            'status'       => true,
            'platform_id' => $platform->id,
        ]
    );

    $customerClient->refresh();

    expect($customerClient)->toBeInstanceOf(CustomerClient::class)
        ->and($customerClient->reference)->toBe('ref1')
        ->and($customerClient->contact_name)->toBe('Acme')
        ->and($customerClient->company_name)->toBe('Acme.inc');

    return $customerClient;
});

test('Store retina web user', function () {
    $webUser = StoreRetinaWebUser::make()->action(
        $this->fulfilmentCustomer->customer,
        [
            'contact_name' => 'Sunshine',
            'username'     => 'camel',
            'email'        => 'acme@acme.com',
            'password'     => 'joko'
        ]
    );

    $webUser->refresh();

    expect($webUser)->toBeInstanceOf(WebUser::class)
        ->and($webUser->contact_name)->toBe('Sunshine')
        ->and($webUser->username)->toBe('camel')
        ->and($webUser->email)->toBe('acme@acme.com');

    return $webUser;
});

test('update retina web user', function (WebUser $webUser) {
    $webUser = UpdateRetinaWebUser::make()->action(
        $webUser,
        [
            'username' => 'amelia',
        ]
    );

    $webUser->refresh();

    expect($webUser)->toBeInstanceOf(WebUser::class)
        ->and($webUser->username)->toBe('amelia');

    return $webUser;
})->depends('Store retina web user');

test('delete retina web user', function (WebUser $webUser) {
    expect($webUser->customer->stats->number_web_users)->toBe(2);
    DeleteRetinaWebUser::make()->action(
        $webUser
    );
    $webUser->customer->refresh();
    expect($webUser->customer->stats->number_web_users)->toBe(1);
})->depends('update retina web user');
