<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 26 Nov 2024 20:48:28 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Accounting\Invoice\PayInvoice;
use App\Actions\Accounting\Invoice\RefundToCredit;
use App\Actions\Accounting\Invoice\StoreRefund;
use App\Actions\Accounting\Invoice\UI\FinaliseRefund;
use App\Actions\Accounting\InvoiceTransaction\StoreRefundInvoiceTransaction;
use App\Actions\Accounting\StandaloneFulfilmentInvoice\CompleteStandaloneFulfilmentInvoice;
use App\Actions\Accounting\StandaloneFulfilmentInvoice\StoreStandaloneFulfilmentInvoice;
use App\Actions\Accounting\StandaloneFulfilmentInvoiceTransaction\DeleteStandaloneFulfilmentInvoiceTransaction;
use App\Actions\Accounting\StandaloneFulfilmentInvoiceTransaction\StoreStandaloneFulfilmentInvoiceTransaction;
use App\Actions\Accounting\StandaloneFulfilmentInvoiceTransaction\UpdateStandaloneFulfilmentInvoiceTransaction;
use App\Actions\Billables\Rental\StoreRental;
use App\Actions\Billables\Rental\UpdateRental;
use App\Actions\Billables\Service\StoreService;
use App\Actions\Catalogue\Shop\StoreShop;
use App\Actions\Comms\OrgPostRoom\StoreOrgPostRoom;
use App\Actions\Comms\Outbox\StoreOutbox;
use App\Actions\CRM\Customer\ApproveCustomer;
use App\Actions\CRM\Customer\RejectCustomer;
use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\Fulfilment\Fulfilment\UpdateFulfilment;
use App\Actions\Fulfilment\FulfilmentCustomer\FetchNewWebhookFulfilmentCustomer;
use App\Actions\Fulfilment\FulfilmentCustomer\Search\ReindexFulfilmentCustomerSearch;
use App\Actions\Fulfilment\FulfilmentCustomer\StoreFulfilmentCustomer;
use App\Actions\Fulfilment\FulfilmentCustomer\UpdateFulfilmentCustomer;
use App\Actions\Fulfilment\FulfilmentTransaction\DeleteFulfilmentTransaction;
use App\Actions\Fulfilment\Pallet\AttachPalletsToReturn;
use App\Actions\Fulfilment\Pallet\BookInPallet;
use App\Actions\Fulfilment\Pallet\DeletePallet;
use App\Actions\Fulfilment\Pallet\DeletePalletInDelivery;
use App\Actions\Fulfilment\Pallet\ImportPalletReturnItem;
use App\Actions\Fulfilment\Pallet\ReturnPalletToCustomer;
use App\Actions\Fulfilment\Pallet\SetPalletAsDamaged;
use App\Actions\Fulfilment\Pallet\SetPalletAsLost;
use App\Actions\Fulfilment\Pallet\SetPalletAsNotReceived;
use App\Actions\Fulfilment\Pallet\SetPalletRental;
use App\Actions\Fulfilment\Pallet\StoreMultiplePalletsFromDelivery;
use App\Actions\Fulfilment\Pallet\StorePallet;
use App\Actions\Fulfilment\Pallet\StorePalletCreatedInPalletDelivery;
use App\Actions\Fulfilment\Pallet\UndoBookedInPallet;
use App\Actions\Fulfilment\Pallet\UpdatePallet;
use App\Actions\Fulfilment\PalletDelivery\ConfirmPalletDelivery;
use App\Actions\Fulfilment\PalletDelivery\ImportPalletsInPalletDelivery;
use App\Actions\Fulfilment\PalletDelivery\ImportPalletsInPalletDeliveryWithStoredItems;
use App\Actions\Fulfilment\PalletDelivery\Notifications\SendPalletDeliveryNotification;
use App\Actions\Fulfilment\PalletDelivery\Pdf\PdfPalletDelivery;
use App\Actions\Fulfilment\PalletDelivery\ReceivePalletDelivery;
use App\Actions\Fulfilment\PalletDelivery\SetPalletDeliveryAsBookedIn;
use App\Actions\Fulfilment\PalletDelivery\StartBookingPalletDelivery;
use App\Actions\Fulfilment\PalletDelivery\StorePalletDelivery;
use App\Actions\Fulfilment\PalletDelivery\SubmitAndConfirmPalletDelivery;
use App\Actions\Fulfilment\PalletDelivery\UpdatePalletDelivery;
use App\Actions\Fulfilment\PalletReturn\CancelPalletReturn;
use App\Actions\Fulfilment\PalletReturn\ConfirmPalletReturn;
use App\Actions\Fulfilment\PalletReturn\DispatchPalletReturn;
use App\Actions\Fulfilment\PalletReturn\Notifications\SendPalletReturnNotification;
use App\Actions\Fulfilment\PalletReturn\PickedPalletReturn;
use App\Actions\Fulfilment\PalletReturn\PickingPalletReturn;
use App\Actions\Fulfilment\PalletReturn\StorePalletReturn;
use App\Actions\Fulfilment\PalletReturn\SubmitAndConfirmPalletReturn;
use App\Actions\Fulfilment\PalletReturn\UpdatePalletReturn;
use App\Actions\Fulfilment\PalletReturnItem\PickPalletReturnItemInPalletReturnWithStoredItem;
use App\Actions\Fulfilment\RecurringBill\ConsolidateRecurringBill;
use App\Actions\Fulfilment\RecurringBill\StoreRecurringBill;
use App\Actions\Fulfilment\RentalAgreement\StoreRentalAgreement;
use App\Actions\Fulfilment\RentalAgreement\UpdateRentalAgreement;
use App\Actions\Fulfilment\Space\StoreSpace;
use App\Actions\Fulfilment\Space\UpdateSpace;
use App\Actions\Fulfilment\StoredItem\AttachStoredItemToReturn;
use App\Actions\Fulfilment\StoredItem\DeleteStoredItem;
use App\Actions\Fulfilment\StoredItem\StoreStoredItem;
use App\Actions\Fulfilment\StoredItem\SyncStoredItemToPallet;
use App\Actions\Fulfilment\StoredItemAudit\CompleteStoredItemAudit;
use App\Actions\Fulfilment\StoredItemAudit\StoreStoredItemAudit;
use App\Actions\Fulfilment\StoredItemAudit\StoreStoredItemAuditFromPallet;
use App\Actions\Fulfilment\StoredItemAudit\UpdateStoredItemAudit;
use App\Actions\Fulfilment\StoredItemAuditDelta\DeleteStoredItemAuditDelta;
use App\Actions\Fulfilment\StoredItemAuditDelta\StoreStoredItemAuditDelta;
use App\Actions\Fulfilment\StoredItemAuditDelta\UpdateStoredItemAuditDelta;
use App\Actions\Inventory\Location\StoreLocation;
use App\Actions\SysAdmin\User\StoreUser;
use App\Actions\Traits\WithGetRecurringBillEndDate;
use App\Actions\Web\Website\StoreWebsite;
use App\Enums\Accounting\Invoice\InvoicePayStatusEnum;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Enums\Billables\Rental\RentalTypeEnum;
use App\Enums\Billables\Rental\RentalUnitEnum;
use App\Enums\Billables\Service\ServiceStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\Outbox\OutboxStateEnum;
use App\Enums\Comms\Outbox\OutboxTypeEnum;
use App\Enums\Comms\PostRoom\PostRoomCodeEnum;
use App\Enums\CRM\Customer\CustomerRejectReasonEnum;
use App\Enums\CRM\Customer\CustomerStateEnum;
use App\Enums\CRM\Customer\CustomerStatusEnum;
use App\Enums\Fulfilment\FulfilmentTransaction\FulfilmentTransactionTypeEnum;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Enums\Fulfilment\Pallet\PalletTypeEnum;
use App\Enums\Fulfilment\PalletDelivery\PalletDeliveryStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnItemStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Enums\Fulfilment\RecurringBill\RecurringBillStatusEnum;
use App\Enums\Fulfilment\RentalAgreement\RentalAgreementBillingCycleEnum;
use App\Enums\Fulfilment\RentalAgreement\RentalAgreementStateEnum;
use App\Enums\Fulfilment\StoredItemAudit\StoredItemAuditStateEnum;
use App\Enums\Fulfilment\StoredItemAuditDelta\StoredItemAuditDeltaStateEnum;
use App\Enums\Web\Website\WebsiteStateEnum;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceTransaction;
use App\Models\Accounting\Payment;
use App\Models\Billables\Rental;
use App\Models\Billables\Service;
use App\Models\Catalogue\Asset;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Outbox;
use App\Models\Comms\PostRoom;
use App\Models\CRM\Customer;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\FulfilmentTransaction;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Fulfilment\PalletReturnItem;
use App\Models\Fulfilment\PalletStoredItem;
use App\Models\Fulfilment\RecurringBill;
use App\Models\Fulfilment\RentalAgreement;
use App\Models\Fulfilment\RentalAgreementStats;
use App\Models\Fulfilment\Space;
use App\Models\Fulfilment\StoredItem;
use App\Models\Fulfilment\StoredItemAudit;
use App\Models\Fulfilment\StoredItemAuditDelta;
use App\Models\Helpers\Address;
use App\Models\Helpers\Upload;
use App\Models\Inventory\Location;
use App\Models\SysAdmin\Permission;
use App\Models\SysAdmin\Role;
use App\Models\SysAdmin\User;
use App\Models\Web\Website;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $this->organisation            = createOrganisation();
    $this->adminGuest              = createAdminGuest($this->organisation->group);
    $this->warehouse               = createWarehouse();
    $this->getRecurringBillEndDate = new class () {
        use WithGetRecurringBillEndDate;
    };

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

    expect($user->getAllPermissions()->count())->toBe(26)
        ->and($user->hasAllRoles(["fulfilment-shop-supervisor-{$shop->fulfilment->id}"]))->toBeTrue()
        ->and($user->hasAllRoles(["shop-admin-$shop->id"]))->toBeFalse()
        ->and($shop->fulfilment->number_warehouses)->toBe(1);


    return $shop->fulfilment;
});

test('create marketing outbox', function (Fulfilment $fulfilment) {
    $postRoom = PostRoom::where('code', PostRoomCodeEnum::MARKETING_NOTIFICATION)->first();

    $orgPostRoom = StoreOrgPostRoom::make()->action($postRoom, $this->organisation, []);

    $shop = $fulfilment->shop;

    $marketingOutbox = StoreOutbox::make()->action($orgPostRoom, $shop, [
        'code' => OutboxCodeEnum::SEND_INVOICE_TO_CUSTOMER,
        'type' => OutboxTypeEnum::CUSTOMER_NOTIFICATION,
        'state' => OutboxStateEnum::ACTIVE,
        'name' => 'invoice outbox'
    ]);

    expect($marketingOutbox)->toBeInstanceOf(Outbox::class);

    return $marketingOutbox;
})->depends('create fulfilment shop');

// case reject customer
test('create fulfilment second customer (pending approval)', function (Fulfilment $fulfilment) {
    $fulfilmentCustomer = StoreFulfilmentCustomer::make()->action(
        $fulfilment,
        [
            'state'           => CustomerStateEnum::IN_PROCESS,
            'status'          => CustomerStatusEnum::PENDING_APPROVAL,
            'contact_name'    => 'Contact B',
            'company_name'    => 'Company B',
            'interest'        => ['pallets_storage', 'items_storage', 'dropshipping'],
            'contact_address' => Address::factory()->definition(),
        ]
    );

    expect($fulfilmentCustomer)->toBeInstanceOf(FulfilmentCustomer::class)
        ->and($fulfilmentCustomer->customer)->toBeInstanceOf(Customer::class)
        ->and($fulfilmentCustomer->customer->status)->toBe(CustomerStatusEnum::PENDING_APPROVAL)
        ->and($fulfilmentCustomer->customer->state)->toBe(CustomerStateEnum::IN_PROCESS)
        ->and($fulfilmentCustomer->customer->is_fulfilment)->toBeTrue()
        ->and($fulfilment->shop->crmStats->number_customers_status_pending_approval)->toBe(1);

    return $fulfilmentCustomer;
})->depends('create fulfilment shop');

test('reject fulfilment customer', function (FulfilmentCustomer $fulfilmentCustomer) {
    $customer = RejectCustomer::make()->action($fulfilmentCustomer->customer, [
        'rejected_reason' => CustomerRejectReasonEnum::SPAM->value,
    ]);

    expect($customer)->toBeInstanceOf(Customer::class)
        ->and($customer->status)->toBe(CustomerStatusEnum::REJECTED)
        ->and($customer->is_fulfilment)->toBeTrue()
        ->and($customer->shop->crmStats->number_customers_status_rejected)->toBe(1);

    $fulfilmentCustomer->forceDelete();
})->depends('create fulfilment second customer (pending approval)');


test('update fulfilment settings (weekly cut off day)', function (Fulfilment $fulfilment) {
    $fulfilment = UpdateFulfilment::make()->action(
        $fulfilment,
        [
            'weekly_cut_off_day' => "Tuesday"
        ]
    );

    expect($fulfilment->settings['rental_agreement_cut_off']['weekly']['day'])->toBe('Tuesday');

    return $fulfilment;
})->depends('create fulfilment shop');

test('update fulfilment settings (monthly cut off day)', function (Fulfilment $fulfilment) {
    $fulfilment = UpdateFulfilment::make()->action(
        $fulfilment,
        [
            'monthly_cut_off' => [
                'date'       => 9,
                'isWeekdays' => false
            ]
        ]
    );

    expect($fulfilment->settings['rental_agreement_cut_off']['monthly']['day'])->toBe(9)
        ->and($fulfilment->settings['rental_agreement_cut_off']['monthly']['is_weekdays'])->toBeFalse();

    return $fulfilment;
})->depends('create fulfilment shop');

test('get end date recurring bill (monthly)', function () {
    $current = Carbon::now(); // store current time once

    $endDate = $this->getRecurringBillEndDate->getEndDate(
        $current,
        [
            'type' => 'monthly',
            'day'  => 9,
        ]
    );

    expect($endDate)->toBeInstanceOf(Carbon::class);

    $expected = $current->copy()->day(9);
    if ($current->gte($expected)) {
        $expected = $expected->addMonth();
    }

    expect($endDate->toDateString())->toEqual($expected->toDateString());

    return $endDate;
});

test('get end date recurring bill (weekly)', function () {
    $startDate = Carbon::create(2025, 10, 20); // 20 is monday
    $endDate   = $this->getRecurringBillEndDate->getEndDate(
        $startDate,
        [
            'type' => 'weekly',
            'day'  => 'Monday',
        ]
    );

    expect($endDate)->toBeInstanceOf(Carbon::class)
        ->toEqual(Carbon::create(2025, 10, 27));

    $startDate = Carbon::create(2025, 11, 21); // 21 is friday
    $endDate   = $this->getRecurringBillEndDate->getEndDate(
        $startDate,
        [
            'type' => 'weekly',
            'day'  => 'Tuesday',
        ]
    );

    expect($endDate)->toBeInstanceOf(Carbon::class)
        ->toEqual(Carbon::create(2025, 11, 25));

    return $endDate;
});

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
            'auto_assign_subject_type' => 'box',
            'auto_assign_status'       => true,
            'state'                    => ServiceStateEnum::ACTIVE
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
            'auto_assign_subject_type' => 'pallet',
            'auto_assign_status'       => true,
            'state'                    => ServiceStateEnum::ACTIVE
        ]
    );
    StoreService::make()->action(
        $fulfilment->shop,
        [
            'price'                    => 111,
            'unit'                     => 'job',
            'code'                     => 'Ser-03',
            'name'                     => 'Service 3',
            'is_auto_assign'           => true,
            'auto_assign_trigger'      => 'PalletReturn',
            'auto_assign_subject'      => 'Pallet',
            'auto_assign_subject_type' => 'pallet',
            'auto_assign_status'       => true,
            'state'                    => ServiceStateEnum::ACTIVE
        ]
    );


    expect($service1)->toBeInstanceOf(Service::class)
        ->and($service1->asset)->toBeInstanceOf(Asset::class)
        ->and($service2->organisation->catalogueStats->number_assets_type_service)->toBe(3)
        ->and($service2->organisation->catalogueStats->number_assets)->toBe(3)
        ->and($service2->shop->stats->number_services)->toBe(3)
        ->and($service2->shop->stats->number_services_state_active)->toBe(3)
        ->and($service2->shop->stats->number_assets)->toBe(3)
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
        ->and($rental->organisation->catalogueStats->number_assets)->toBe(4)
        ->and($rental->organisation->catalogueStats->number_assets_type_rental)->toBe(1)
        ->and($rental->shop->stats->number_assets)->toBe(4)
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
        ->and($palletRental->organisation->catalogueStats->number_assets)->toBe(7)
        ->and($palletRental->organisation->catalogueStats->number_assets_type_rental)->toBe(4)
        ->and($palletRental->shop->stats->number_assets)->toBe(7)
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


test('create second rental product to fulfilment shop', function (Fulfilment $fulfilment) {
    $rental = StoreRental::make()->action(
        $fulfilment->shop,
        [
            'price' => 200,
            'unit'  => RentalUnitEnum::WEEK->value,
            'code'  => 'R00002',
            'name'  => 'Rental Asset B',
        ]
    );

    expect($rental)->toBeInstanceOf(Rental::class)
        ->and($rental->asset)->toBeInstanceOf(Asset::class);

    return $rental;
})->depends('create fulfilment shop');

test('assign auto assign asset to rental', function (Rental $rental) {
    $rental->update([
        'auto_assign_asset'      => 'Pallet',
        'auto_assign_asset_type' => PalletTypeEnum::PALLET->value,
    ]);

    expect($rental->auto_assign_asset)->toBe('Pallet')
        ->and($rental->auto_assign_asset_type)->toBe(PalletTypeEnum::PALLET->value);

    return $rental;
})->depends('create rental product to fulfilment shop');

test('create fulfilment website', function (Fulfilment $fulfilment) {
    $website = StoreWebsite::make()->action(
        $fulfilment->shop,
        Website::factory()->definition(),
    );

    expect($website)->toBeInstanceOf(Website::class)
        ->and($website->state)->toBe(WebsiteStateEnum::IN_PROCESS);

    return $website;
})->depends('create fulfilment shop');


test('create fulfilment customer from customer', function (Fulfilment $fulfilment) {
    $customerData = Customer::factory()->definition();

    $customer = StoreCustomer::make()->action(
        $fulfilment->shop,
        $customerData
    );

    UpdateFulfilmentCustomer::make()->action(
        $customer->fulfilmentCustomer,
        [
            'pallets_storage' => true,
            'items_storage'   => true,
        ]
    );

    $fulfilment->refresh();

    expect($customer)->toBeInstanceOf(Customer::class)
        ->and($customer->fulfilmentCustomer)->toBeInstanceOf(FulfilmentCustomer::class)
        ->and($customer->status)->toBe(CustomerStatusEnum::PENDING_APPROVAL)
        ->and($customer->is_fulfilment)->toBeTrue()
        ->and($customer->fulfilmentCustomer->pallets_storage)->toBeTrue()
        ->and($customer->fulfilmentCustomer->items_storage)->toBeTrue()
        ->and($customer->fulfilmentCustomer->dropshipping)->toBeFalse()
        ->and($customer->fulfilmentCustomer->number_pallets)->toBe(0)
        ->and($customer->fulfilmentCustomer->number_stored_items)->toBe(0)
        ->and($fulfilment->stats->number_customers_interest_items_storage)->toBe(1)
        ->and($fulfilment->stats->number_customers_interest_pallets_storage)->toBe(1)
        ->and($fulfilment->shop->crmStats->number_customers_status_pending_approval)->tobe(1)
        ->and($fulfilment->stats->number_customers_interest_dropshipping)->toBe(0);

    return $customer->fulfilmentCustomer;
})->depends('create fulfilment shop');

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
        ->and($fulfilment->shop->crmStats->number_customers)->toBe(3)
        ->and($fulfilment->stats->number_customers_interest_items_storage)->toBe(2)
        ->and($fulfilment->stats->number_customers_interest_pallets_storage)->toBe(2)
        ->and($fulfilment->stats->number_customers_interest_dropshipping)->toBe(1);

    return $fulfilmentCustomer;
})->depends('create fulfilment shop');

test('create 5th fulfilment customer', function (Fulfilment $fulfilment) {
    $fulfilmentCustomer = StoreFulfilmentCustomer::make()->action(
        $fulfilment,
        [
            'state'           => CustomerStateEnum::ACTIVE,
            'status'          => CustomerStatusEnum::APPROVED,
            'contact_name'    => 'John',
            'company_name'    => 'john.o',
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
        ->and($fulfilment->shop->crmStats->number_customers)->toBe(4)
        ->and($fulfilment->stats->number_customers_interest_items_storage)->toBe(3)
        ->and($fulfilment->stats->number_customers_interest_pallets_storage)->toBe(3)
        ->and($fulfilment->stats->number_customers_interest_dropshipping)->toBe(2);

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

test('update rental agreement', function (RentalAgreement $rentalAgreement) {
    UpdateRentalAgreement::make()->action(
        $rentalAgreement,
        [
            'billing_cycle' => RentalAgreementBillingCycleEnum::WEEKLY,
            'pallets_limit' => 10,
            'state'         => RentalAgreementStateEnum::ACTIVE,
            'update_all'    => false,
        ]
    );
    $rentalAgreement->refresh();
    expect($rentalAgreement->billing_cycle)->toBe(RentalAgreementBillingCycleEnum::WEEKLY)
        ->and($rentalAgreement->pallets_limit)->toBe(10)
        ->and($rentalAgreement->state)->toBe(RentalAgreementStateEnum::ACTIVE)
        ->and($rentalAgreement->stats->number_rental_agreement_snapshots)->toBe(2);


    return $rentalAgreement;
})->depends('create rental agreement for 4th customer');

test('update rental agreement cause', function (RentalAgreement $rentalAgreement) {
    $rentalAgreement = UpdateRentalAgreement::make()->action(
        $rentalAgreement,
        [
            'update_all' => false,
            'clauses'    => [
                'rentals' => [
                    [
                        'asset_id'       => $rentalAgreement->fulfilmentCustomer->fulfilment->rentals->first()->asset_id,
                        'percentage_off' => 30,
                    ],
                    [
                        'asset_id'       => $rentalAgreement->fulfilmentCustomer->fulfilment->rentals->last()->asset_id,
                        'percentage_off' => 50,
                    ],
                ]
            ]
        ]
    );
    $rentalAgreement->refresh();
    expect($rentalAgreement->stats->number_rental_agreement_clauses)->toBe(2)
        ->and($rentalAgreement->stats->number_rental_agreement_clauses_type_rental)->toBe(2)
        ->and($rentalAgreement->clauses->first()->percentage_off)->toEqualWithDelta(30, .001)
        ->and($rentalAgreement->clauses->last()->percentage_off)->toEqualWithDelta(50, .001)
        ->and($rentalAgreement->stats->number_rental_agreement_snapshots)->toBe(3);


    return $rentalAgreement;
})->depends('create rental agreement for 4th customer');

test('create second rental agreement for 5th customer', function (FulfilmentCustomer $fulfilmentCustomer) {
    $rentalAgreement = StoreRentalAgreement::make()->action(
        $fulfilmentCustomer,
        [
            'state'         => RentalAgreementStateEnum::DRAFT,
            'billing_cycle' => RentalAgreementBillingCycleEnum::MONTHLY,
            'pallets_limit' => null,
            'username'      => 'test-a',
            'email'         => 'test-bis@testmail.com',
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
        ->and($rentalAgreement->state)->toBe(RentalAgreementStateEnum::DRAFT)
        ->and($rentalAgreement->stats)->toBeInstanceOf(RentalAgreementStats::class)
        ->and($rentalAgreement->stats->number_rental_agreement_clauses)->toBe(2)
        ->and($rentalAgreement->stats->number_rental_agreement_clauses_type_rental)->toBe(2)
        ->and($rentalAgreement->clauses->first()->asset)->toBeInstanceOf(Asset::class)
        ->and($rentalAgreement->clauses->last()->asset)->toBeInstanceOf(Asset::class)
        ->and($rentalAgreement->stats->number_rental_agreement_snapshots)->toBe(1);

    return $rentalAgreement;
})->depends('create 5th fulfilment customer');

test('update second rental agreement cause', function (RentalAgreement $rentalAgreement) {
    $rentalAgreement = UpdateRentalAgreement::make()->action(
        $rentalAgreement,
        [
            'update_all' => false,
            'clauses'    => [
                'rentals' => [
                    [
                        'asset_id'       => $rentalAgreement->fulfilmentCustomer->fulfilment->rentals->first()->asset_id,
                        'percentage_off' => 30,
                    ],
                    [
                        'asset_id'       => $rentalAgreement->fulfilmentCustomer->fulfilment->rentals->last()->asset_id,
                        'percentage_off' => 50,
                    ],
                ]
            ]
        ]
    );
    $rentalAgreement->refresh();
    expect($rentalAgreement->stats->number_rental_agreement_clauses)->toBe(2)
        ->and($rentalAgreement->stats->number_rental_agreement_clauses_type_rental)->toBe(2)
        ->and($rentalAgreement->clauses->first()->percentage_off)->toEqualWithDelta(30, .001)
        ->and($rentalAgreement->clauses->last()->percentage_off)->toEqualWithDelta(50, .001)
        ->and($rentalAgreement->stats->number_rental_agreement_snapshots)->toBe(2);


    return $rentalAgreement;
})->depends('create second rental agreement for 5th customer');

test('create space', function (FulfilmentCustomer $fulfilmentCustomer) {
    $fulfilment = $fulfilmentCustomer->fulfilment;
    $rental     = StoreRental::make()->action(
        $fulfilment->shop,
        [
            'price' => 100,
            'unit'  => RentalUnitEnum::WEEK->value,
            'code'  => 'R00020',
            'name'  => 'Rental Asset Z',
            'type'  => RentalTypeEnum::SPACE
        ]
    );
    $space      = StoreSpace::make()->action(
        $fulfilmentCustomer,
        [
            'reference'       => 'Ref1',
            'exclude_weekend' => true,
            'start_at'        => now(),
            'rental_id'       => $rental->id
        ]
    );

    expect($space)->toBeInstanceOf(Space::class);

    return $space;
})->depends('create 5th fulfilment customer');

test('update space', function (Space $space) {
    $space = UpdateSpace::make()->action(
        $space,
        [
            'exclude_weekend' => false,
        ]
    );

    expect($space)->toBeInstanceOf(Space::class)
        ->and($space->exclude_weekend)->toBeFalse();
})->depends('create space');

test('Fetch new webhook fulfilment customer', function (FulfilmentCustomer $fulfilmentCustomer) {
    $webhook = FetchNewWebhookFulfilmentCustomer::make()->action(
        $this->organisation,
        $fulfilmentCustomer->fulfilment,
        $fulfilmentCustomer,
        []
    );

    expect($webhook)->toHaveKey('webhook_access_key')
        ->and($webhook['webhook_access_key'])->toBeString()
        ->and(strlen($webhook['webhook_access_key']))->toBe(64);
    /** @var FulfilmentCustomer $updatedFulfilmentCustomer */
    $updatedFulfilmentCustomer = FulfilmentCustomer::find($fulfilmentCustomer->id);
    expect($updatedFulfilmentCustomer->webhook_access_key)->toBe($webhook['webhook_access_key']);

    return $webhook;
})->depends('create 4th fulfilment customer');


test('create pallet delivery', function ($fulfilmentCustomer) {
    expect($fulfilmentCustomer->currentRecurringBill)->toBeNull();


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
        ->and($palletDelivery->stats->number_pallets)->toBe(0)
        ->and($fulfilmentCustomer->number_pallet_deliveries)->toBe(1)
        ->and($fulfilmentCustomer->number_pallets)->toBe(0)
        ->and($fulfilmentCustomer->currentRecurringBill)->toBeNull();

    return $palletDelivery;
})->depends('create 4th fulfilment customer');

test('create second pallet delivery', function ($fulfilmentCustomer) {
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
        ->and($palletDelivery->stats->number_pallets)->toBe(0)
        ->and($fulfilmentCustomer->number_pallet_deliveries)->toBe(1)
        ->and($fulfilmentCustomer->number_pallets)->toBe(0);

    return $palletDelivery;
})->depends('create 5th fulfilment customer');

test('update pallet delivery notes', function (PalletDelivery $palletDelivery) {
    UpdatePalletDelivery::make()->action(
        $palletDelivery,
        [
            'customer_notes' => 'Note A',
            'public_notes'   => 'Note B',
            'internal_notes' => 'Note C',

        ]
    );

    expect($palletDelivery->customer_notes)->toBe('Note A')
        ->and($palletDelivery->public_notes)->toBe('Note B')
        ->and($palletDelivery->internal_notes)->toBe('Note C')
        ->and($palletDelivery->transactions()->count())->toBe(0);


    UpdatePalletDelivery::make()->action(
        $palletDelivery,
        [
            'customer_notes' => '',

        ]
    );
    expect($palletDelivery->customer_notes)->toBe('');

    return $palletDelivery;
})->depends('create pallet delivery');

test('add pallet and delete it', function (PalletDelivery $palletDelivery) {

    $palletDelivery->refresh();
    $pallet = StorePalletCreatedInPalletDelivery::make()->action(
        $palletDelivery,
        [
            'customer_reference' => 'C00001_will_be_deleted',
            'type'               => PalletTypeEnum::BOX->value,
            'notes'              => 'note A',
        ]
    );
    $palletDelivery->refresh();
    expect($pallet)->toBeInstanceOf(Pallet::class)
        ->and($palletDelivery->stats->number_services)->toBe(1)
        ->and($pallet->state)->toBe(PalletStateEnum::IN_PROCESS)
        ->and($pallet->status)->toBe(PalletStatusEnum::IN_PROCESS)
        ->and($pallet->type)->toBe(PalletTypeEnum::BOX)
        ->and($pallet->notes)->toBe('note A')
        ->and($pallet->source_id)->toBeNull()
        ->and($pallet->customer_reference)->toBeString()
        ->and($pallet->received_at)->toBeNull()
        ->and($pallet->fulfilmentCustomer)->toBeInstanceOf(FulfilmentCustomer::class)
        ->and($pallet->fulfilmentCustomer->number_pallets)->toBe(1)
        ->and($pallet->fulfilmentCustomer->number_stored_items)->toBe(0)
        ->and($palletDelivery->stats->number_pallets)->toBe(1)
        ->and($palletDelivery->stats->number_pallets_type_box)->toBe(1)
        ->and($palletDelivery->transactions()->count())->toBe(1);
    $palletDelivery->refresh();


    DeletePallet::make()->action($pallet, []);
    expect($palletDelivery->transactions()->count())->toBe(0);


})->depends('create pallet delivery');

test('add pallet to pallet delivery', function (PalletDelivery $palletDelivery) {
    $pallet = StorePalletCreatedInPalletDelivery::make()->action(
        $palletDelivery,
        [
            'customer_reference' => 'C00001',
            'type'               => PalletTypeEnum::BOX->value,
            'notes'              => 'note A',
        ]
    );

    $palletDelivery->refresh();

    expect($pallet)->toBeInstanceOf(Pallet::class)
        ->and($palletDelivery->stats->number_services)->toBe(1)
        ->and($pallet->state)->toBe(PalletStateEnum::IN_PROCESS)
        ->and($pallet->status)->toBe(PalletStatusEnum::IN_PROCESS)
        ->and($pallet->type)->toBe(PalletTypeEnum::BOX)
        ->and($pallet->notes)->toBe('note A')
        ->and($pallet->source_id)->toBeNull()
        ->and($pallet->customer_reference)->toBeString()
        ->and($pallet->received_at)->toBeNull()
        ->and($pallet->fulfilmentCustomer)->toBeInstanceOf(FulfilmentCustomer::class)
        ->and($pallet->fulfilmentCustomer->number_pallets)->toBe(1)
        ->and($pallet->fulfilmentCustomer->number_stored_items)->toBe(0)
        ->and($palletDelivery->stats->number_pallets)->toBe(1)
        ->and($palletDelivery->stats->number_pallets_type_box)->toBe(1)
        ->and($palletDelivery->transactions()->count())->toBe(1);


    return $pallet;
})->depends('create pallet delivery');

test('add pallet to second pallet delivery', function (PalletDelivery $palletDelivery) {
    $pallet = StorePalletCreatedInPalletDelivery::make()->action(
        $palletDelivery,
        [
            'customer_reference' => 'C00002',
            'type'               => PalletTypeEnum::BOX->value,
            'notes'              => 'note A',
        ]
    );

    $palletDelivery->refresh();
    expect($pallet)->toBeInstanceOf(Pallet::class)
        ->and($palletDelivery->stats->number_services)->toBe(1)
        ->and($pallet->state)->toBe(PalletStateEnum::IN_PROCESS)
        ->and($pallet->status)->toBe(PalletStatusEnum::IN_PROCESS)
        ->and($pallet->type)->toBe(PalletTypeEnum::BOX)
        ->and($pallet->notes)->toBe('note A')
        ->and($pallet->source_id)->toBeNull()
        ->and($pallet->customer_reference)->toBeString()
        ->and($pallet->received_at)->toBeNull()
        ->and($pallet->fulfilmentCustomer)->toBeInstanceOf(FulfilmentCustomer::class)
        ->and($pallet->fulfilmentCustomer->number_pallets)->toBe(1)
        ->and($pallet->fulfilmentCustomer->number_stored_items)->toBe(0)
        ->and($palletDelivery->stats->number_pallets)->toBe(1)
        ->and($palletDelivery->stats->number_pallets_type_box)->toBe(1);


    return $palletDelivery;
})->depends('create second pallet delivery');





test('add multiple pallets to pallet delivery', function (PalletDelivery $palletDelivery) {
    StoreMultiplePalletsFromDelivery::make()->action(
        $palletDelivery,
        [
            'warehouse_id'   => $this->warehouse->id,
            'number_pallets' => 3,
            'type'           => PalletTypeEnum::PALLET->value,
        ]
    );

    $palletDelivery->refresh();

    expect($palletDelivery->stats->number_pallets)->toBe(4)
        ->and($palletDelivery->stats->number_pallets_type_pallet)->toBe(3)
        ->and($palletDelivery->stats->number_pallets_type_box)->toBe(1)
        ->and($palletDelivery->stats->number_pallets_with_stored_items)->toBe(0)
        ->and($palletDelivery->stats->number_stored_items)->toBe(0);

    return $palletDelivery;
})->depends('create pallet delivery');

test('remove a pallet from pallet delivery', function (PalletDelivery $palletDelivery) {
    DeletePalletInDelivery::make()->action(
        $palletDelivery->pallets->last()
    );

    $palletDelivery->refresh();

    expect($palletDelivery->stats->number_pallets)->toBe(3)
        ->and($palletDelivery->stats->number_pallets_type_pallet)->toBe(2)
        ->and($palletDelivery->stats->number_pallets_type_box)->toBe(1)
        ->and($palletDelivery->stats->number_services)->toBe(2)
        ->and($palletDelivery->stats->number_pallets_with_stored_items)->toBe(0)
        ->and($palletDelivery->stats->number_stored_items)->toBe(0);


    return $palletDelivery;
})->depends('add multiple pallets to pallet delivery');

test('remove a service from pallet delivery', function (PalletDelivery $palletDelivery) {
    /** @var FulfilmentTransaction $serviceTransaction */
    $serviceTransaction = $palletDelivery->transactions()->where('type', FulfilmentTransactionTypeEnum::SERVICE)->first();

    DeleteFulfilmentTransaction::make()->action(
        $serviceTransaction
    );


    $palletDelivery->refresh();
    $fulfilmentCustomer = $palletDelivery->fulfilmentCustomer;

    expect($fulfilmentCustomer->currentRecurringBill)->toBeNull()
        ->and($palletDelivery->stats->number_pallets)->toBe(3)
        ->and($palletDelivery->stats->number_pallets_type_pallet)->toBe(2)
        ->and($palletDelivery->stats->number_pallets_type_box)->toBe(1)
        ->and($palletDelivery->stats->number_services)->toBe(1)
        ->and($palletDelivery->stats->number_pallets_with_stored_items)->toBe(0)
        ->and($palletDelivery->stats->number_stored_items)->toBe(0);


    return $palletDelivery;
})->depends('remove a pallet from pallet delivery');

test('confirm pallet delivery', function (PalletDelivery $palletDelivery) {
    SendPalletDeliveryNotification::shouldRun()->andReturn();

    $palletDelivery = ConfirmPalletDelivery::make()->action($palletDelivery);

    $pallet = $palletDelivery->pallets->first();

    $fulfilmentCustomer = $palletDelivery->fulfilmentCustomer;

    expect($fulfilmentCustomer->currentRecurringBill)->toBeNull()
        ->and($palletDelivery->state)->toBe(PalletDeliveryStateEnum::CONFIRMED)
        ->and($palletDelivery->confirmed_at)->toBeInstanceOf(Carbon::class)
        ->and($palletDelivery->stats->number_pallets)->toBe(3)
        ->and($palletDelivery->stats->number_pallets_with_stored_items)->toBe(0)
        ->and($palletDelivery->stats->number_stored_items)->toBe(0)
        ->and($pallet->state)->toBe(PalletStateEnum::CONFIRMED)
        ->and($pallet->status)->toBe(PalletStatusEnum::RECEIVING);

    return $palletDelivery;
})->depends('add multiple pallets to pallet delivery');

test('confirm second pallet delivery', function (PalletDelivery $palletDelivery) {
    SendPalletDeliveryNotification::shouldRun()->andReturn();

    $palletDelivery = ConfirmPalletDelivery::make()->action($palletDelivery);

    $pallet = $palletDelivery->pallets->first();

    expect($palletDelivery->state)->toBe(PalletDeliveryStateEnum::CONFIRMED)
        ->and($palletDelivery->confirmed_at)->toBeInstanceOf(Carbon::class)
        ->and($palletDelivery->stats->number_pallets)->toBe(1)
        ->and($palletDelivery->stats->number_pallets_with_stored_items)->toBe(0)
        ->and($palletDelivery->stats->number_stored_items)->toBe(0)
        ->and($pallet->state)->toBe(PalletStateEnum::CONFIRMED)
        ->and($pallet->status)->toBe(PalletStatusEnum::RECEIVING);

    return $palletDelivery;
})->depends('add pallet to second pallet delivery');

test('receive pallet delivery', function (PalletDelivery $palletDelivery) {
    SendPalletDeliveryNotification::shouldRun()->andReturn();

    $palletDelivery = ReceivePalletDelivery::make()->action($palletDelivery);
    $palletDelivery->refresh();

    $palletNotInRentalCount = $palletDelivery->pallets()->whereNull('rental_id')->count();


    $fulfilmentCustomer = $palletDelivery->fulfilmentCustomer;
    expect($fulfilmentCustomer->currentRecurringBill)->toBeInstanceOf(RecurringBill::class)
        ->and($fulfilmentCustomer->currentRecurringBill->stats->number_transactions_type_pallets)->toBe(3)
        ->and($palletDelivery->state)->toBe(PalletDeliveryStateEnum::RECEIVED)
        ->and($palletDelivery->received_at)->toBeInstanceOf(Carbon::class)
        ->and($palletDelivery->stats->number_pallets)->toBe(3)
        ->and($palletNotInRentalCount)->toBe(0);

    return $palletDelivery;
})->depends('confirm pallet delivery');

test('receive second pallet delivery', function (PalletDelivery $palletDelivery) {
    SendPalletDeliveryNotification::shouldRun()->andReturn();

    $palletDelivery = ReceivePalletDelivery::make()->action($palletDelivery);

    $palletDelivery->refresh();

    $palletNotInRentalCount = $palletDelivery->pallets()->whereNull('rental_id')->count();


    expect($palletDelivery->state)->toBe(PalletDeliveryStateEnum::RECEIVED)
        ->and($palletDelivery->received_at)->toBeInstanceOf(Carbon::class)
        ->and($palletDelivery->stats->number_pallets)->toBe(1)
        ->and($palletNotInRentalCount)->toBe(0);

    return $palletDelivery;
})->depends('confirm second pallet delivery');

test('start booking-in pallet delivery', function (PalletDelivery $palletDelivery) {
    $palletDelivery = StartBookingPalletDelivery::make()->action($palletDelivery);

    $palletDelivery->refresh();
    $fulfilmentCustomer = $palletDelivery->fulfilmentCustomer;
    expect($fulfilmentCustomer->currentRecurringBill->stats->number_transactions_type_pallets)->toBe(3)
        ->and($palletDelivery->state)->toBe(PalletDeliveryStateEnum::BOOKING_IN)
        ->and($palletDelivery->booking_in_at)->toBeInstanceOf(Carbon::class);


    return $palletDelivery;
})->depends('receive pallet delivery');

test('start booking-in second pallet delivery', function (PalletDelivery $palletDelivery) {
    $palletDelivery = StartBookingPalletDelivery::make()->action($palletDelivery);

    $palletDelivery->refresh();


    expect($palletDelivery->state)->toBe(PalletDeliveryStateEnum::BOOKING_IN)
        ->and($palletDelivery->booking_in_at)->toBeInstanceOf(Carbon::class);

    return $palletDelivery;
})->depends('receive second pallet delivery');

test('set location of first pallet in the pallet delivery', function (PalletDelivery $palletDelivery) {
    $pallet = $palletDelivery->pallets->first();
    /** @var Location $location */
    $location = $this->warehouse->locations()->first();

    BookInPallet::make()->action($pallet, ['location_id' => $location->id]);
    $pallet->refresh();

    $fulfilmentCustomer = $palletDelivery->fulfilmentCustomer;

    expect($fulfilmentCustomer->currentRecurringBill->stats->number_transactions_type_pallets)->toBe(3)
        ->and($pallet->location)->toBeInstanceOf(Location::class)
        ->and($pallet->location->id)->toBe($location->id)
        ->and($pallet->received_at)->toBeInstanceOf(Carbon::class)
        ->and($pallet->booked_in_at)->toBeInstanceOf(Carbon::class)
        ->and($pallet->set_as_not_received_at)->toBeNull()
        ->and($pallet->state)->toBe(PalletStateEnum::BOOKED_IN)
        ->and($pallet->status)->toBe(PalletStatusEnum::RECEIVING);


    return $palletDelivery;
})->depends('start booking-in pallet delivery');

test('set location of only pallet in the second pallet delivery', function (PalletDelivery $palletDelivery) {
    $pallet = $palletDelivery->pallets->first();
    /** @var Location $location */
    $location = $this->warehouse->locations()->first();

    BookInPallet::make()->action($pallet, ['location_id' => $location->id]);
    $pallet->refresh();
    expect($pallet->location)->toBeInstanceOf(Location::class)
        ->and($pallet->location->id)->toBe($location->id)
        ->and($pallet->received_at)->toBeInstanceOf(Carbon::class)
        ->and($pallet->booked_in_at)->toBeInstanceOf(Carbon::class)
        ->and($pallet->set_as_not_received_at)->toBeNull()
        ->and($pallet->state)->toBe(PalletStateEnum::BOOKED_IN)
        ->and($pallet->status)->toBe(PalletStatusEnum::RECEIVING);

    return $palletDelivery;
})->depends('start booking-in second pallet delivery');

test('book in 1st pallet', function (PalletDelivery $palletDelivery) {
    $pallet = $palletDelivery->pallets->first();
    /** @var Location $location */
    $location = $this->warehouse->locations->skip(1)->first();

    BookInPallet::make()->action($pallet, ['location_id' => $location->id]);
    $pallet->refresh();
    expect($pallet->location)->toBeInstanceOf(Location::class)
        ->and($pallet->location->id)->toBe($location->id);

    return $palletDelivery;
})->depends('set location of first pallet in the pallet delivery');

test('undo booked in', function (PalletDelivery $palletDelivery) {
    $pallet = $palletDelivery->pallets->first();

    UndoBookedInPallet::make()->action($pallet);
    $pallet->refresh();

    expect($pallet)->toBeInstanceOf(Pallet::class)
        ->and($pallet->state)->toBe(PalletStateEnum::RECEIVED);

    return $pallet;
})->depends('book in 1st pallet');

test('book in 1st pallet again', function (Pallet $pallet) {
    /** @var Location $location */
    $location = $this->warehouse->locations->skip(1)->first();
    expect($location->stats->number_pallets)->toBe(0);
    BookInPallet::make()->action($pallet, ['location_id' => $location->id]);
    $pallet->refresh();
    expect($pallet->location)->toBeInstanceOf(Location::class)
        ->and($pallet->location->id)->toBe($location->id);
})->depends('undo booked in');

test('set rental to first pallet in the pallet delivery', function (PalletDelivery $palletDelivery) {
    $pallet = $palletDelivery->pallets->first();
    $rental = $palletDelivery->fulfilment->rentals->last();
    expect($rental)->toBeInstanceOf(Rental::class);

    SetPalletRental::make()->action($pallet, ['rental_id' => $rental->id]);
    $pallet->refresh();
    $palletNotInRentalCount = $palletDelivery->pallets()->whereNull('rental_id')->count();

    $fulfilmentCustomer = $palletDelivery->fulfilmentCustomer;

    expect($fulfilmentCustomer->currentRecurringBill->stats->number_transactions_type_pallets)->toBe(3)
        ->and($pallet->rental)->toBeInstanceOf(Rental::class)
        ->and($palletNotInRentalCount)->toBe(0)
        ->and($palletDelivery->stats->number_pallets)->toBe(3);


    return $palletDelivery;
})->depends('set location of first pallet in the pallet delivery');

test('set rental to only pallet in the second pallet delivery', function (PalletDelivery $palletDelivery) {
    $pallet = $palletDelivery->pallets->first();
    $rental = $palletDelivery->fulfilment->rentals->last();
    expect($rental)->toBeInstanceOf(Rental::class);

    SetPalletRental::make()->action($pallet, ['rental_id' => $rental->id]);
    $pallet->refresh();
    $palletNotInRentalCount = $palletDelivery->pallets()->whereNull('rental_id')->count();

    expect($pallet->rental)->toBeInstanceOf(Rental::class)
        ->and($palletNotInRentalCount)->toBe(0);


    return $palletDelivery;
})->depends('set location of only pallet in the second pallet delivery');

test('can create pallet delivery pdf', function (PalletDelivery $palletDelivery) {
    $pdf = PdfPalletDelivery::run($palletDelivery);
    expect($pdf->output())->toBeString();

    return $palletDelivery;
})->depends('set rental to first pallet in the pallet delivery');

test('set second pallet in the pallet delivery as not delivered 4th customer', function (PalletDelivery $palletDelivery) {
    $pallet = $palletDelivery->pallets->skip(1)->first();

    SetPalletAsNotReceived::make()->action($pallet);
    $pallet->refresh();
    $fulfilmentCustomer = $palletDelivery->fulfilmentCustomer;
    $recurringBill      = $fulfilmentCustomer->currentRecurringBill;
    $recurringBill->refresh();


    expect($recurringBill->stats->number_transactions)->toBe(4)
        ->and($recurringBill->stats->number_transactions_type_pallets)->toBe(2)
        ->and($recurringBill->stats->number_transactions_type_services)->toBe(2)
        ->and($recurringBill->stats->number_transactions_type_products)->toBe(0)
        ->and($recurringBill->stats->number_transactions_type_stored_items)->toBe(0)
        ->and($pallet->state)->toBe(PalletStateEnum::NOT_RECEIVED)
        ->and($pallet->set_as_not_received_at)->toBeInstanceOf(Carbon::class)
        ->and($pallet->booked_in_at)->toBeNull()
        ->and($pallet->status)->toBe(PalletStatusEnum::NOT_RECEIVED);


    return $palletDelivery;
})->depends('set location of first pallet in the pallet delivery');

test('create pallet delivery that was not delivered by marking items 4th customer', function (PalletDelivery $palletDelivery) {
    SendPalletDeliveryNotification::shouldRun()->andReturn();

    $fulfilmentCustomer = $palletDelivery->fulfilmentCustomer;


    $palletDelivery = StorePalletDelivery::make()->action($fulfilmentCustomer, ['warehouse_id' => $this->warehouse->id]);
    $pallet         = StorePalletCreatedInPalletDelivery::make()->action($palletDelivery, []);
    $palletDelivery = ConfirmPalletDelivery::make()->action($palletDelivery);
    $palletDelivery = ReceivePalletDelivery::make()->action($palletDelivery);

    $recurringBill = $fulfilmentCustomer->currentRecurringBill;
    $recurringBill->refresh();

    expect($palletDelivery->stats->number_pallets)->toBe(1)
        ->and($palletDelivery->state)->toBe(PalletDeliveryStateEnum::RECEIVED)
        ->and($recurringBill->stats->number_transactions)->toBe(6)
        ->and($recurringBill->stats->number_transactions_type_pallets)->toBe(3)
        ->and($recurringBill->stats->number_transactions_type_services)->toBe(3)
        ->and($recurringBill->stats->number_transactions_type_products)->toBe(0)
        ->and($recurringBill->stats->number_transactions_type_stored_items)->toBe(0);


    SetPalletAsNotReceived::make()->action($pallet, true);
    $palletDelivery->refresh();
    $recurringBill->refresh();

    expect($recurringBill->stats->number_transactions)->toBe(4)
        ->and($recurringBill->stats->number_transactions_type_pallets)->toBe(2)
        ->and($recurringBill->stats->number_transactions_type_services)->toBe(2)
        ->and($recurringBill->stats->number_transactions_type_products)->toBe(0)
        ->and($recurringBill->stats->number_transactions_type_stored_items)->toBe(0)
        ->and($palletDelivery->state)->toBe(PalletDeliveryStateEnum::NOT_RECEIVED);


    return $palletDelivery;
})->depends('set second pallet in the pallet delivery as not delivered 4th customer');


test('set location of third pallet in the pallet delivery 4th customer', function (PalletDelivery $palletDelivery) {
    $fulfilmentCustomer = $palletDelivery->fulfilmentCustomer;
    $recurringBill      = $fulfilmentCustomer->currentRecurringBill;
    expect($recurringBill->stats->number_transactions)->toBe(4);

    $pallet = $palletDelivery->pallets->last();
    /** @var Location $location */
    $location = $this->warehouse->locations->last();
    expect($location->stats->number_pallets)->toBe(1)
        ->and($palletDelivery->state)->toBe(PalletDeliveryStateEnum::BOOKING_IN);

    BookInPallet::make()->action($pallet, ['location_id' => $location->id]);
    $pallet->refresh();
    $location->refresh();

    $palletStateBookedInCount    = $palletDelivery->pallets()->where('state', PalletStateEnum::BOOKED_IN)->count();
    $palletStateNotReceivedCount = $palletDelivery->pallets()->where('state', PalletStateEnum::NOT_RECEIVED)->count();
    $palletStateReceivedCount    = $palletDelivery->pallets()->where('state', PalletStateEnum::RECEIVED)->count();
    $palletReceivedCount         = $palletStateReceivedCount + $palletStateNotReceivedCount + $palletStateBookedInCount;
    $palletNotInRentalCount      = $palletDelivery->pallets()
        ->where('state', '!=', PalletStateEnum::NOT_RECEIVED)->whereNull('rental_id')->count();

    $palletDelivery->refresh();
    $recurringBill->refresh();


    expect($recurringBill->stats->number_transactions)->toBe(4)
        ->and($pallet->location)->toBeInstanceOf(Location::class)
        ->and($palletReceivedCount)->toBe(3)
        ->and($palletStateNotReceivedCount)->toBe(1)
        ->and($palletNotInRentalCount)->toBe(0)
        ->and($pallet->location->id)->toBe($location->id)
        ->and($pallet->state)->toBe(PalletStateEnum::BOOKED_IN)
        ->and($pallet->status)->toBe(PalletStatusEnum::RECEIVING)
        ->and($location->stats->number_pallets)->toBe(2)
        ->and($palletDelivery->state)->toBe(PalletDeliveryStateEnum::BOOKING_IN);

    return $palletDelivery;
})->depends('set second pallet in the pallet delivery as not delivered 4th customer');


test('set pallet delivery as booked in', function (PalletDelivery $palletDelivery) {
    SendPalletDeliveryNotification::shouldRun()->andReturn();

    $fulfilmentCustomer = $palletDelivery->fulfilmentCustomer;
    $recurringBill      = $fulfilmentCustomer->currentRecurringBill;

    expect($recurringBill->stats->number_transactions)->toBe(4)
        ->and($recurringBill->stats->number_transactions_type_pallets)->toBe(2)
        ->and($palletDelivery->state)->toBe(PalletDeliveryStateEnum::BOOKING_IN);

    $palletDelivery = SetPalletDeliveryAsBookedIn::make()->action($palletDelivery);
    $palletDelivery->refresh();

    $fulfilmentCustomer->refresh();

    expect($palletDelivery->state)->toBe(PalletDeliveryStateEnum::BOOKED_IN)
        ->and($palletDelivery->booked_in_at)->toBeInstanceOf(Carbon::class)
        ->and($palletDelivery->stats->number_pallets)->toBe(3)
        ->and($palletDelivery->stats->number_pallets_with_stored_items)->toBe(0)
        ->and($palletDelivery->stats->number_stored_items)->toBe(0)
        ->and($palletDelivery->group->fulfilmentStats->number_recurring_bills)->toBe(2)
        ->and($palletDelivery->group->fulfilmentStats->number_recurring_bills_status_former)->toBe(0)
        ->and($palletDelivery->group->fulfilmentStats->number_recurring_bills_status_current)->toBe(2)
        ->and($palletDelivery->organisation->fulfilmentStats->number_recurring_bills)->toBe(2)
        ->and($palletDelivery->organisation->fulfilmentStats->number_recurring_bills_status_former)->toBe(0)
        ->and($palletDelivery->organisation->fulfilmentStats->number_recurring_bills_status_current)->toBe(2)
        ->and($palletDelivery->fulfilment->stats->number_recurring_bills)->toBe(2)
        ->and($palletDelivery->fulfilment->stats->number_recurring_bills_status_former)->toBe(0)
        ->and($palletDelivery->fulfilment->stats->number_recurring_bills_status_current)->toBe(2)
        ->and($palletDelivery->fulfilmentCustomer->number_recurring_bills)->toBe(1)
        ->and($palletDelivery->fulfilmentCustomer->number_recurring_bills_status_former)->toBe(0)
        ->and($palletDelivery->fulfilmentCustomer->number_recurring_bills_status_current)->toBe(1)
        ->and($fulfilmentCustomer->currentRecurringBill)->toBeInstanceOf(RecurringBill::class);

    $recurringBill->refresh();

    expect($recurringBill->stats->number_transactions)->toBe(4)
        ->and($recurringBill->stats->number_transactions_type_pallets)->toBe(2)
        ->and($recurringBill->stats->number_transactions_type_stored_items)->toBe(0);


    return $palletDelivery;
})->depends('set location of third pallet in the pallet delivery 4th customer');

test('set second pallet delivery as booked in', function (PalletDelivery $palletDelivery) {
    SendPalletDeliveryNotification::shouldRun()->andReturn();

    $fulfilmentCustomer = $palletDelivery->fulfilmentCustomer;
    expect($palletDelivery->state)->toBe(PalletDeliveryStateEnum::BOOKING_IN);

    $palletDelivery = SetPalletDeliveryAsBookedIn::make()->action($palletDelivery);
    $palletDelivery->refresh();
    $fulfilmentCustomer->refresh();


    expect($palletDelivery->state)->toBe(PalletDeliveryStateEnum::BOOKED_IN)
        ->and($palletDelivery->booked_in_at)->toBeInstanceOf(Carbon::class)
        ->and($palletDelivery->stats->number_pallets)->toBe(1)
        ->and($palletDelivery->stats->number_pallets_with_stored_items)->toBe(0)
        ->and($palletDelivery->stats->number_stored_items)->toBe(0)
        ->and($palletDelivery->group->fulfilmentStats->number_recurring_bills)->toBe(2)
        ->and($palletDelivery->group->fulfilmentStats->number_recurring_bills_status_former)->toBe(0)
        ->and($palletDelivery->group->fulfilmentStats->number_recurring_bills_status_current)->toBe(2)
        ->and($palletDelivery->organisation->fulfilmentStats->number_recurring_bills)->toBe(2)
        ->and($palletDelivery->organisation->fulfilmentStats->number_recurring_bills_status_former)->toBe(0)
        ->and($palletDelivery->organisation->fulfilmentStats->number_recurring_bills_status_current)->toBe(2)
        ->and($palletDelivery->fulfilment->stats->number_recurring_bills)->toBe(2)
        ->and($palletDelivery->fulfilment->stats->number_recurring_bills_status_former)->toBe(0)
        ->and($palletDelivery->fulfilment->stats->number_recurring_bills_status_current)->toBe(2)
        ->and($palletDelivery->fulfilmentCustomer->number_recurring_bills)->toBe(1)
        ->and($palletDelivery->fulfilmentCustomer->number_recurring_bills_status_former)->toBe(0)
        ->and($palletDelivery->fulfilmentCustomer->number_recurring_bills_status_current)->toBe(1)
        ->and($fulfilmentCustomer->currentRecurringBill)->toBeInstanceOf(RecurringBill::class);

    $recurringBill = $fulfilmentCustomer->currentRecurringBill;
    expect($recurringBill->stats->number_transactions)->toBe(2)
        ->and($recurringBill->stats->number_transactions_type_pallets)->toBe(1)
        ->and($recurringBill->stats->number_transactions_type_stored_items)->toBe(0);

    $firstPallet = $palletDelivery->pallets->first();

    expect($firstPallet->state)->toBe(PalletStateEnum::STORING);


    return $palletDelivery;
})->depends('set rental to only pallet in the second pallet delivery');

test('recurring bill next cycle', function (PalletDelivery $palletDelivery) {
    $fulfilmentCustomer = $palletDelivery->fulfilmentCustomer;
    $rentalAgreement    = $fulfilmentCustomer->rentalAgreement;

    $currentBill = $fulfilmentCustomer->currentRecurringBill;
    $nextCycle   = StoreRecurringBill::make()->action(
        $rentalAgreement,
        [
            'start_date' => $currentBill->end_date
        ]
    );
    expect($nextCycle)->toBeInstanceOf(RecurringBill::class)
        ->and($nextCycle->start_date)->not()->toBe($currentBill->start_date);

    return $nextCycle;
})->depends('set pallet delivery as booked in');

test('second recurring bill next cycle', function (PalletDelivery $palletDelivery) {
    $fulfilmentCustomer = $palletDelivery->fulfilmentCustomer;
    $rentalAgreement    = $fulfilmentCustomer->rentalAgreement;

    $currentBill = $fulfilmentCustomer->currentRecurringBill;
    $nextCycle   = StoreRecurringBill::make()->action(
        $rentalAgreement,
        [
            'start_date' => $currentBill->end_date
        ]
    );

    expect($nextCycle)->toBeInstanceOf(RecurringBill::class)
        ->and($nextCycle->start_date)->not()->toBe($currentBill->start_date);


    return $nextCycle;
})->depends('set second pallet delivery as booked in');


test('create pallet return', function (PalletDelivery $palletDelivery) {
    SendPalletReturnNotification::shouldRun()
        ->andReturn();


    $fulfilmentCustomer = $palletDelivery->fulfilmentCustomer;

    $palletReturn = StorePalletReturn::make()->action(
        $fulfilmentCustomer,
        [
            'warehouse_id' => $this->warehouse->id,
        ]
    );

    $fulfilmentCustomer->refresh();
    expect($palletReturn)->toBeInstanceOf(PalletReturn::class)
        ->and($palletReturn->state)->toBe(PalletReturnStateEnum::IN_PROCESS)
        ->and($palletReturn->stats->number_pallets)->toBe(0)
        ->and($fulfilmentCustomer->fulfilment->stats->number_pallet_returns)->toBe(1)
        ->and($fulfilmentCustomer->number_pallet_returns)->toBe(1)
        ->and($fulfilmentCustomer->number_pallet_returns_state_in_process)->toBe(1);

    return $palletReturn;
})->depends('set pallet delivery as booked in');

test('update pallet return', function (PalletReturn $palletReturn) {
    $fulfilmentCustomer = $palletReturn->fulfilmentCustomer;

    $updatedPalletReturn = UpdatePalletReturn::make()->action(
        $palletReturn,
        [
            'customer_notes' => 'note',
        ]
    );
    $fulfilmentCustomer->refresh();
    expect($updatedPalletReturn)->toBeInstanceOf(PalletReturn::class)
        ->and($palletReturn->state)->toBe(PalletReturnStateEnum::IN_PROCESS)
        ->and($palletReturn->customer_notes)->toBe('note')
        ->and($palletReturn->stats->number_pallets)->toBe(0)
        ->and($fulfilmentCustomer->fulfilment->stats->number_pallet_returns)->toBe(1)
        ->and($fulfilmentCustomer->number_pallet_returns)->toBe(1)
        ->and($fulfilmentCustomer->number_pallet_returns_state_in_process)->toBe(1);

    return $palletReturn;
})->depends('create pallet return');

test('store pallet to return', function (PalletReturn $palletReturn) {
    $fulfilmentCustomer = $palletReturn->fulfilmentCustomer;

    $pallet = StorePallet::make()->action(
        $fulfilmentCustomer,
        array_merge([
            'warehouse_id' => $this->warehouse->id,
        ], Pallet::factory()->definition())
    );


    $palletReturn = AttachPalletsToReturn::make()->action(
        $palletReturn,
        [
            'pallets' => [
                $pallet->id
            ],
        ]
    );
    $palletReturn->refresh();
    $fulfilmentCustomer->refresh();
    $firstPallet = $palletReturn->pallets->first();
    expect($palletReturn)->toBeInstanceOf(PalletReturn::class)
        ->and($palletReturn->stats->number_pallets)->toBe(1)
        ->and($firstPallet)->toBeInstanceOf(Pallet::class)
        ->and($firstPallet->status)->toBe(PalletStatusEnum::RETURNING)
        ->and($firstPallet->state)->toBe(PalletStateEnum::REQUEST_RETURN_IN_PROCESS)
        ->and($firstPallet->pallet_return_id)->toBe($palletReturn->id);

    return $palletReturn;
})->depends('create pallet return');

test('Update pallet reference', function (PalletReturn $palletReturn) {
    /** @var Pallet $pallet */
    $pallet = $palletReturn->fulfilmentCustomer
            ->pallets()
            ->whereNotIn('pallets.id', $palletReturn->pallets()->pluck('pallets.id'))
            ->first();

    $newReference = 'GHO-p0006';

    $pallet = UpdatePallet::make()->action(
        $pallet,
        [
            'reference' => 'GHO-p0006',
            'state'     => PalletStateEnum::STORING,
            'status'    => PalletStatusEnum::STORING
        ]
    );
    expect($pallet)->toBeInstanceOf(Pallet::class)
        ->and($pallet->reference)->toBe($newReference);
})->depends('store pallet to return');

test('import pallets in return (xlsx)  whole pallets ', function (PalletReturn $palletReturn) {
    Storage::fake('local');

    $tmpPath = 'tmp/uploads/';

    $filePath = base_path('tests/fixtures/returnPalletItems.xlsx');
    $file     = new UploadedFile($filePath, 'returnPalletItems.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    Storage::fake('local')->put($tmpPath, $file);
    $palletReturn->refresh();
    expect($palletReturn->pallets()->count())->toBe(1)
        ->and($palletReturn->stats->number_pallets)->toBe(1);


    $upload = ImportPalletReturnItem::run($palletReturn, $file);
    $palletReturn->refresh();


    expect($palletReturn)->toBeInstanceOf(PalletReturn::class)
        ->and($palletReturn->pallets()->count())->toBe(2)
        ->and($palletReturn->stats->number_pallets)->toBe(2)
        ->and($upload)->toBeInstanceOf(Upload::class)
        ->and($upload->model)->toBe('PalletReturnItem')
        ->and($upload->number_rows)->toBe(1)
        ->and($upload->number_success)->toBe(1)
        ->and($upload->number_fails)->toBe(0);

    return $palletReturn;
})->depends('store pallet to return'); // Kirin Redo this test

test('import pallets in return (xlsx) again', function (PalletReturn $palletReturn) {
    Storage::fake('local');

    $tmpPath = 'tmp/uploads/';

    $filePath = base_path('tests/fixtures/returnPalletItems.xlsx');
    $file     = new UploadedFile($filePath, 'returnPalletItems.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    Storage::fake('local')->put($tmpPath, $file);
    $palletReturn->refresh();
    expect($palletReturn->pallets()->count())->toBe(2)
        ->and($palletReturn->stats->number_pallets)->toBe(2);

    $upload = ImportPalletReturnItem::run($palletReturn, $file);
    $palletReturn->refresh();

    expect($palletReturn)->toBeInstanceOf(PalletReturn::class)
        ->and($palletReturn->pallets()->count())->toBe(2)
        ->and($palletReturn->stats->number_pallets)->toBe(2)
        ->and($upload)->toBeInstanceOf(Upload::class)
        ->and($upload->model)->toBe('PalletReturnItem')
        ->and($upload->number_rows)->toBe(1)
        ->and($upload->number_success)->toBe(0)
        ->and($upload->number_fails)->toBe(1);

    return $palletReturn;
})->depends('import pallets in return (xlsx)  whole pallets ');

test('import pallets in return (xlsx) invalid pallet reference', function (PalletReturn $palletReturn) {
    Storage::fake('local');

    $tmpPath = 'tmp/uploads/';

    $filePath = base_path('tests/fixtures/PalletReturnInvalid.xlsx');
    $file     = new UploadedFile($filePath, 'PalletReturnInvalid.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    Storage::fake('local')->put($tmpPath, $file);
    $palletReturn->refresh();
    expect($palletReturn->pallets()->count())->toBe(2)
        ->and($palletReturn->stats->number_pallets)->toBe(2);

    $upload = ImportPalletReturnItem::run($palletReturn, $file);
    $palletReturn->refresh();

    expect($palletReturn)->toBeInstanceOf(PalletReturn::class)
        ->and($palletReturn->pallets()->count())->toBe(2)
        ->and($palletReturn->stats->number_pallets)->toBe(2)
        ->and($upload)->toBeInstanceOf(Upload::class)
        ->and($upload->model)->toBe('PalletReturnItem')
        ->and($upload->number_rows)->toBe(1)
        ->and($upload->number_success)->toBe(0)
        ->and($upload->number_fails)->toBe(1);

    return $palletReturn;
})->depends('import pallets in return (xlsx)  whole pallets ');

test('update rental agreement clause again', function (PalletReturn $palletReturn) {
    $rentalAgreement        = $palletReturn->fulfilmentCustomer->rentalAgreement;
    $service                = $palletReturn->services()->first();
    $updatedRentalAgreement = UpdateRentalAgreement::make()->action(
        $rentalAgreement,
        [
            'update_all' => true,
            'clauses'    => [
                'rentals'  => [
                    [
                        'asset_id'       => $rentalAgreement->fulfilmentCustomer->fulfilment->rentals->first()->asset_id,
                        'percentage_off' => 30,
                    ],
                    [
                        'asset_id'       => $rentalAgreement->fulfilmentCustomer->fulfilment->rentals->last()->asset_id,
                        'percentage_off' => 50,
                    ],
                ],
                'services' => [
                    [
                        'asset_id'       => $service->asset_id,
                        'percentage_off' => 10,
                    ],
                ]
            ]
        ]
    );
    $updatedRentalAgreement->refresh();
    $palletReturn->refresh();
    expect($updatedRentalAgreement->stats->number_rental_agreement_clauses)->toBe(3)
        ->and($updatedRentalAgreement->stats->number_rental_agreement_clauses_type_rental)->toBe(2)
        ->and($updatedRentalAgreement->stats->number_rental_agreement_clauses_type_service)->toBe(1)
        ->and($palletReturn->gross_amount)->not->tobe($palletReturn->net_amount);

    return $rentalAgreement;
})->depends('import pallets in return (xlsx)  whole pallets ');

test('submit pallet return', function (PalletReturn $palletReturn) {
    SendPalletReturnNotification::shouldRun()
        ->andReturn();

    $fulfilmentCustomer = $palletReturn->fulfilmentCustomer;

    $submittedPalletReturn = SubmitAndConfirmPalletReturn::make()->action($palletReturn);

    $fulfilmentCustomer->refresh();
    $firstPallet = $submittedPalletReturn->pallets->first();
    expect($submittedPalletReturn)->toBeInstanceOf(PalletReturn::class)
        ->and($submittedPalletReturn->state)->toBe(PalletReturnStateEnum::CONFIRMED)
        ->and($firstPallet)->toBeInstanceOf(Pallet::class)
        ->and($firstPallet->status)->toBe(PalletStatusEnum::RETURNING);

    return $submittedPalletReturn;
})->depends('store pallet to return');


test('picking pallet to return', function (PalletReturn $submittedPalletReturn) {
    SendPalletReturnNotification::shouldRun()
        ->andReturn();

    $fulfilmentCustomer = $submittedPalletReturn->fulfilmentCustomer;


    $pickingPalletReturn = PickingPalletReturn::make()->action(
        $submittedPalletReturn,
    );
    // dd($storedPallet);
    $fulfilmentCustomer->refresh();
    $firstPallet = $pickingPalletReturn->pallets->first();
    expect($pickingPalletReturn)->toBeInstanceOf(PalletReturn::class)
        ->and($firstPallet)->toBeInstanceOf(Pallet::class)
        ->and($firstPallet->status)->toBe(PalletStatusEnum::RETURNING)
        ->and($firstPallet->state)->toBe(PalletStateEnum::PICKING);

    return $pickingPalletReturn;
})->depends('submit pallet return');

test('picked pallet to return', function (PalletReturn $pickingPalletReturn) {
    SendPalletReturnNotification::shouldRun()
        ->andReturn();

    $fulfilmentCustomer = $pickingPalletReturn->fulfilmentCustomer;


    $pickedPalletReturn = PickedPalletReturn::make()->action(
        $pickingPalletReturn,
    );
    // dd($storedPallet);
    $fulfilmentCustomer->refresh();
    expect($pickedPalletReturn)->toBeInstanceOf(PalletReturn::class)
        ->and($pickedPalletReturn->state)->toBe(PalletReturnStateEnum::PICKED);


    return $pickedPalletReturn;
})->depends('picking pallet to return');

test('cancel pallet return', function (PalletReturn $palletReturn) {
    SendPalletReturnNotification::shouldRun()
        ->andReturn();

    $fulfilmentCustomer = $palletReturn->fulfilmentCustomer;

    $canceledPalletReturn = CancelPalletReturn::make()->action(
        $fulfilmentCustomer,
        $palletReturn
    );
    $fulfilmentCustomer->refresh();
    expect($canceledPalletReturn)->toBeInstanceOf(PalletReturn::class)
        ->and($canceledPalletReturn->state)->toBe(PalletReturnStateEnum::CANCEL);

    return $canceledPalletReturn;
})->depends('create pallet return');

test('confirm pallet return', function (PalletReturn $palletReturn) {
    SendPalletReturnNotification::shouldRun()
        ->andReturn();

    $fulfilmentCustomer = $palletReturn->fulfilmentCustomer;

    $confirmedPalletReturn = ConfirmPalletReturn::make()->action(
        $palletReturn
    );
    $fulfilmentCustomer->refresh();
    expect($confirmedPalletReturn)->toBeInstanceOf(PalletReturn::class)
        ->and($confirmedPalletReturn->state)->toBe(PalletReturnStateEnum::CONFIRMED);

    return $confirmedPalletReturn;
})->depends('create pallet return');

test('dispatch pallet return', function (PalletReturn $palletReturn) {
    SendPalletReturnNotification::shouldRun()
        ->andReturn();
    $fulfilmentCustomer = $palletReturn->fulfilmentCustomer;


    $dispatchedPalletReturn = DispatchPalletReturn::make()->action(
        $palletReturn
    );
    $fulfilmentCustomer->refresh();
    expect($dispatchedPalletReturn)->toBeInstanceOf(PalletReturn::class)
        ->and($dispatchedPalletReturn->state)->toBe(PalletReturnStateEnum::DISPATCHED);

    return $dispatchedPalletReturn;
})->depends('create pallet return');

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
        ->and($pallet->status)->toBe(PalletStatusEnum::IN_PROCESS)
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

test('update pallet', function (Pallet $pallet) {
    $updatedPallet = UpdatePallet::make()->action(
        $pallet,
        [
            'state'  => PalletStateEnum::DAMAGED,
            'status' => PalletStatusEnum::INCIDENT,
            'notes'  => 'sorry'
        ]
    );


    expect($updatedPallet)->toBeInstanceOf(Pallet::class)
        ->and($updatedPallet->state)->toBe(PalletStateEnum::DAMAGED)
        ->and($updatedPallet->status)->toBe(PalletStatusEnum::INCIDENT)
        ->and($updatedPallet->notes)->toBe('sorry');

    return $updatedPallet;
})->depends('create pallet no delivery');

test('can not delete delete pallet that is already received', function (Pallet $pallet) {

    $pallet->refresh();
    $palletDelivery = $pallet->palletDelivery;
    DeletePallet::make()->action(
        $pallet,
        []
    );


    $palletDeleted = !Pallet::find($pallet->id);
    $palletDelivery->refresh();

    expect($palletDeleted)->toBeTrue()
    ->and($palletDelivery->stats->number_services)->toBe(0);

    return 'OK';
})->depends('add pallet to pallet delivery')->throws(Exception::class);

test('Return pallet to customer', function (Pallet $pallet) {
    $returnedPallet = ReturnPalletToCustomer::make()->action(
        $pallet,
    );

    expect($returnedPallet)->toBeInstanceOf(Pallet::class)
        ->and($returnedPallet->state)->toBe(PalletStateEnum::DISPATCHED)
        ->and($returnedPallet->status)->toBe(PalletStatusEnum::RETURNED);

    return $returnedPallet;
})->depends('create pallet no delivery');

test('Set pallet as damaged', function (Pallet $pallet) {
    $damagedPallet = SetPalletAsDamaged::make()->action(
        $pallet,
        [
            'message'     => 'pallet damaged',
            'reporter_id' => $this->user->id
        ]
    );

    expect($damagedPallet)->toBeInstanceOf(Pallet::class)
        ->and($damagedPallet->state)->toBe(PalletStateEnum::DAMAGED)
        ->and($damagedPallet->status)->toBe(PalletStatusEnum::INCIDENT);

    return $damagedPallet;
})->depends('create pallet no delivery');

test('Set pallet as lost', function (Pallet $pallet) {
    $user = $this->user;
    $this->actingAs($user);
    $lostPallet = SetPalletAsLost::make()->action(
        $pallet,
        [
            'message'     => 'ehe',
            'reporter_id' => $this->user->id
        ]
    );

    expect($lostPallet)->toBeInstanceOf(Pallet::class)
        ->and($lostPallet->state)->toBe(PalletStateEnum::LOST)
        ->and($lostPallet->status)->toBe(PalletStatusEnum::INCIDENT);

    return $lostPallet;
})->depends('create pallet no delivery');

test('create third pallet delivery (stored item test)', function ($fulfilmentCustomer) {
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
        ->and($palletDelivery->stats->number_pallets)->toBe(0)
        ->and($fulfilmentCustomer->number_pallet_deliveries)->toBe(2)
        ->and($fulfilmentCustomer->number_pallets)->toBe(1);

    return $palletDelivery;
})->depends('create 5th fulfilment customer');

test('add pallet to third pallet delivery', function (PalletDelivery $palletDelivery) {
    $pallet = StorePalletCreatedInPalletDelivery::make()->action(
        $palletDelivery,
        [
            'customer_reference' => 'pallet_A123',
            'type'               => PalletTypeEnum::PALLET->value,
        ]
    );

    $palletDelivery->refresh();
    expect($pallet)->toBeInstanceOf(Pallet::class)
        ->and($palletDelivery->stats->number_services)->toBe(1)
        ->and($pallet->state)->toBe(PalletStateEnum::IN_PROCESS)
        ->and($pallet->status)->toBe(PalletStatusEnum::IN_PROCESS)
        ->and($pallet->type)->toBe(PalletTypeEnum::PALLET)
        ->and($pallet->source_id)->toBeNull()
        ->and($pallet->customer_reference)->toBeString()
        ->and($pallet->received_at)->toBeNull()
        ->and($pallet->fulfilmentCustomer)->toBeInstanceOf(FulfilmentCustomer::class)
        ->and($pallet->fulfilmentCustomer->number_pallets)->toBe(2)
        ->and($pallet->fulfilmentCustomer->number_stored_items)->toBe(0)
        ->and($palletDelivery->stats->number_pallets)->toBe(1)
        ->and($palletDelivery->stats->number_pallets_type_pallet)->toBe(1);


    return $pallet;
})->depends('create third pallet delivery (stored item test)');

test('create stored item', function ($fulfilmentCustomer) {
    $storedItem = StoreStoredItem::make()->action(
        $fulfilmentCustomer,
        [
            'reference' => 'Test',
        ]
    );
    $fulfilmentCustomer->refresh();
    expect($storedItem)->toBeInstanceOf(StoredItem::class)
        ->and($fulfilmentCustomer->number_stored_items)->toBe(1);

    return $storedItem;
})->depends('create 5th fulfilment customer');

test('create stored item and attach to pallet', function (Pallet $pallet) {
    $storedItem = StoreStoredItem::make()->action(
        $pallet->fulfilmentCustomer,
        [
            'reference' => 'Blab',
        ]
    );

    SyncStoredItemToPallet::make()->action(
        $pallet,
        [
            'stored_item_ids' => [
                $storedItem->id => [
                    'quantity' => 1
                ]
            ]
        ]
    );

    $pallet->refresh();
    expect($storedItem)->toBeInstanceOf(StoredItem::class)
        ->and($pallet)->toBeInstanceOf(Pallet::class)
        ->and($pallet->storedItems()->count())->toBe(1);

    return $storedItem;
})->depends('add pallet to third pallet delivery');

test('create stored item, attach to pallet and delete', function (Pallet $pallet) {
    $storedItem = StoreStoredItem::make()->action(
        $pallet->fulfilmentCustomer,
        [
            'reference' => 'stored_item_A',
        ]
    );

    SyncStoredItemToPallet::make()->action(
        $pallet,
        [
            'stored_item_ids' => [
                $storedItem->id => [
                    'quantity' => 1
                ]
            ]
        ]
    );

    DeleteStoredItem::make()->action($storedItem, []);

    $pallet->refresh();
    expect($pallet)->toBeInstanceOf(Pallet::class)
        ->and($pallet->storedItems()->count())->toBe(0)
        ->and(StoredItem::find($storedItem->id))->toBeNull();

    return $pallet;
})->depends('add pallet to third pallet delivery');

test('create fourth pallet delivery (pallet import test)', function ($fulfilmentCustomer) {
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
        ->and($palletDelivery->stats->number_pallets)->toBe(0)
        ->and($fulfilmentCustomer->number_pallet_deliveries)->toBe(3)
        ->and($fulfilmentCustomer->number_pallets)->toBe(2);

    return $palletDelivery;
})->depends('create 5th fulfilment customer');

test('import pallet (xlsx)', function (PalletDelivery $palletDelivery) {
    Storage::fake('local');

    $tmpPath = 'tmp/uploads/';

    $filePath = base_path('tests/fixtures/PalletsOnlyTest.xlsx');
    $file     = new UploadedFile($filePath, 'PalletsOnlyTest.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    Storage::fake('local')->put($tmpPath, $file);

    expect($palletDelivery->stats->number_pallets)->toBe(0);

    $upload = ImportPalletsInPalletDelivery::run($palletDelivery, $file, [
        'with_stored_item' => false
    ]);
    $palletDelivery->refresh();
    expect($upload)->toBeInstanceOf(Upload::class)
        ->and($upload->number_rows)->toBe(3)
        ->and($upload->number_success)->toBe(3)
        ->and($upload->number_fails)->toBe(0)
        ->and($palletDelivery->stats->number_pallets)->toBe(3)
        ->and($palletDelivery->stats->number_pallets_type_pallet)->toBe(1)
        ->and($palletDelivery->stats->number_pallets_type_box)->toBe(1)
        ->and($palletDelivery->stats->number_pallets_type_oversize)->toBe(1);


    return $palletDelivery;
})->depends('create fourth pallet delivery (pallet import test)');

test('import pallet (xlsx) duplicate', function (PalletDelivery $palletDelivery) {
    Storage::fake('local');

    $tmpPath = 'tmp/uploads/';

    $filePath = base_path('tests/fixtures/PalletsOnlyTest.xlsx');
    $file     = new UploadedFile($filePath, 'PalletsOnlyTest.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    Storage::fake('local')->put($tmpPath, $file);

    expect($palletDelivery->stats->number_pallets)->toBe(3)
        ->and($palletDelivery->stats->number_pallets_type_pallet)->toBe(1)
        ->and($palletDelivery->stats->number_pallets_type_box)->toBe(1)
        ->and($palletDelivery->stats->number_pallets_type_oversize)->toBe(1);

    $upload = ImportPalletsInPalletDelivery::run($palletDelivery, $file, [
        'with_stored_item' => false
    ]);
    $palletDelivery->refresh();
    expect($upload)->toBeInstanceOf(Upload::class)
        ->and($upload->number_rows)->toBe(3)
        ->and($upload->number_success)->toBe(0)
        ->and($upload->number_fails)->toBe(3)
        ->and($palletDelivery->stats->number_pallets)->toBe(3)
        ->and($palletDelivery->stats->number_pallets_type_pallet)->toBe(1)
        ->and($palletDelivery->stats->number_pallets_type_box)->toBe(1)
        ->and($palletDelivery->stats->number_pallets_type_oversize)->toBe(1);


    return $palletDelivery;
})->depends('import pallet (xlsx)');

test('import pallet (xlsx) invalid types', function (PalletDelivery $palletDelivery) {
    Storage::fake('local');

    $tmpPath = 'tmp/uploads/';

    $filePath = base_path('tests/fixtures/PalletsOnlyTestInvalidType.xlsx');
    $file     = new UploadedFile($filePath, 'PalletsOnlyTestInvalidType.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    Storage::fake('local')->put($tmpPath, $file);

    expect($palletDelivery->stats->number_pallets)->toBe(3)
        ->and($palletDelivery->stats->number_pallets_type_pallet)->toBe(1)
        ->and($palletDelivery->stats->number_pallets_type_box)->toBe(1)
        ->and($palletDelivery->stats->number_pallets_type_oversize)->toBe(1);

    $upload = ImportPalletsInPalletDelivery::run($palletDelivery, $file, [
        'with_stored_item' => false
    ]);
    $palletDelivery->refresh();
    expect($upload)->toBeInstanceOf(Upload::class)
        ->and($upload->number_rows)->toBe(3)
        ->and($upload->number_success)->toBe(3)
        ->and($upload->number_fails)->toBe(0)
        ->and($palletDelivery->stats->number_pallets)->toBe(6)
        ->and($palletDelivery->stats->number_pallets_type_pallet)->toBe(4)
        ->and($palletDelivery->stats->number_pallets_type_box)->toBe(1)
        ->and($palletDelivery->stats->number_pallets_type_oversize)->toBe(1);


    return $palletDelivery;
})->depends('import pallet (xlsx) duplicate');

test('create third fulfilment customer', function (Fulfilment $fulfilment) {
    $fulfilmentCustomer = StoreFulfilmentCustomer::make()->action(
        $fulfilment,
        [
            'state'           => CustomerStateEnum::ACTIVE,
            'status'          => CustomerStatusEnum::APPROVED,
            'contact_name'    => 'John Dow',
            'company_name'    => 'Acme',
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
        ->and($fulfilmentCustomer->dropshipping)->toBeTrue();

    return $fulfilmentCustomer;
})->depends('create fulfilment shop');

test('create third rental agreement', function (FulfilmentCustomer $fulfilmentCustomer) {
    $rentalAgreement = StoreRentalAgreement::make()->action(
        $fulfilmentCustomer,
        [
            'state'         => RentalAgreementStateEnum::ACTIVE,
            'billing_cycle' => RentalAgreementBillingCycleEnum::MONTHLY,
            'pallets_limit' => null,
            'username'      => 'test-b',
            'email'         => 'test-bus@testmail.com',
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
})->depends('create third fulfilment customer');

test('create fifth pallet delivery (stored item import test)', function ($fulfilmentCustomer) {
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
        ->and($palletDelivery->state)->toBe(PalletDeliveryStateEnum::IN_PROCESS);

    return $palletDelivery;
})->depends('create third fulfilment customer');

test('add pallet to fifth pallet delivery', function (PalletDelivery $palletDelivery) {
    $pallet = StorePalletCreatedInPalletDelivery::make()->action(
        $palletDelivery,
        [
            'customer_reference' => 'A1234',
            'type'               => PalletTypeEnum::PALLET->value,
        ]
    );

    $palletDelivery->refresh();
    expect($pallet)->toBeInstanceOf(Pallet::class)
        ->and($palletDelivery->stats->number_services)->toBe(1)
        ->and($pallet->state)->toBe(PalletStateEnum::IN_PROCESS)
        ->and($pallet->status)->toBe(PalletStatusEnum::IN_PROCESS)
        ->and($pallet->type)->toBe(PalletTypeEnum::PALLET)
        ->and($pallet->fulfilmentCustomer->number_pallets)->toBe(1)
        ->and($palletDelivery->stats->number_pallets)->toBe(1)
        ->and($palletDelivery->stats->number_pallets_type_pallet)->toBe(1);


    return $pallet;
})->depends('create fifth pallet delivery (stored item import test)');

test('create 2 stored item and attach to pallet (5th delivery)', function (Pallet $pallet) {
    $storedItem       = StoreStoredItem::make()->action(
        $pallet->fulfilmentCustomer,
        [
            'reference' => 'Blab',
        ]
    );
    $secondStoredItem = StoreStoredItem::make()->action(
        $pallet->fulfilmentCustomer,
        [
            'reference' => 'Blob',
        ]
    );

    SyncStoredItemToPallet::make()->action(
        $pallet,
        [
            'stored_item_ids' => [
                $storedItem->id       => [
                    'quantity' => 8
                ],
                $secondStoredItem->id => [
                    'quantity' => 10
                ]
            ]
        ]
    );

    $pallet->refresh();

    expect($storedItem)->toBeInstanceOf(StoredItem::class)
        ->and($pallet)->toBeInstanceOf(Pallet::class)
        ->and($pallet->storedItems()->count())->toBe(2);

    return $pallet;
})->depends('add pallet to fifth pallet delivery');

test('submit and confirm fifth pallet delivery', function (Pallet $pallet) {
    SendPalletDeliveryNotification::shouldRun()->andReturn();

    $palletDelivery = SubmitAndConfirmPalletDelivery::make()->action($pallet->palletDelivery);

    $pallet = $palletDelivery->pallets->first();

    $palletDelivery->refresh();

    expect($palletDelivery->state)->toBe(PalletDeliveryStateEnum::CONFIRMED)
        ->and($palletDelivery->confirmed_at)->toBeInstanceOf(Carbon::class)
        ->and($palletDelivery->stats->number_pallets)->toBe(1)
        ->and($pallet->storedItems()->count())->toBe(2)
        ->and($pallet->state)->toBe(PalletStateEnum::CONFIRMED)
        ->and($pallet->status)->toBe(PalletStatusEnum::RECEIVING);

    return $palletDelivery;
})->depends('create 2 stored item and attach to pallet (5th delivery)');

test('receive fifth pallet delivery', function (PalletDelivery $palletDelivery) {
    SendPalletDeliveryNotification::shouldRun()->andReturn();

    $palletDelivery = ReceivePalletDelivery::make()->action($palletDelivery);

    $palletDelivery->refresh();

    expect($palletDelivery->state)->toBe(PalletDeliveryStateEnum::RECEIVED)
        ->and($palletDelivery->received_at)->toBeInstanceOf(Carbon::class);

    return $palletDelivery;
})->depends('submit and confirm fifth pallet delivery');

test('start booking-in fifth pallet delivery', function (PalletDelivery $palletDelivery) {
    $palletDelivery = StartBookingPalletDelivery::make()->action($palletDelivery);

    $palletDelivery->refresh();


    expect($palletDelivery->state)->toBe(PalletDeliveryStateEnum::BOOKING_IN)
        ->and($palletDelivery->booking_in_at)->toBeInstanceOf(Carbon::class);

    return $palletDelivery;
})->depends('receive fifth pallet delivery');

test('set location of only pallet in the fifth pallet delivery', function (PalletDelivery $palletDelivery) {
    $pallet = $palletDelivery->pallets->first();
    /** @var Location $location */
    $location = $this->warehouse->locations()->first();

    BookInPallet::make()->action($pallet, ['location_id' => $location->id]);
    $pallet->refresh();
    expect($pallet->location)->toBeInstanceOf(Location::class)
        ->and($pallet->location->id)->toBe($location->id)
        ->and($pallet->received_at)->toBeInstanceOf(Carbon::class)
        ->and($pallet->booked_in_at)->toBeInstanceOf(Carbon::class)
        ->and($pallet->set_as_not_received_at)->toBeNull()
        ->and($pallet->state)->toBe(PalletStateEnum::BOOKED_IN)
        ->and($pallet->status)->toBe(PalletStatusEnum::RECEIVING);

    return $palletDelivery;
})->depends('start booking-in fifth pallet delivery');

test('set rental to only pallet in the fifth pallet delivery', function (PalletDelivery $palletDelivery) {
    $pallet = $palletDelivery->pallets->first();
    $rental = $palletDelivery->fulfilment->rentals->last();
    expect($rental)->toBeInstanceOf(Rental::class);

    SetPalletRental::make()->action($pallet, ['rental_id' => $rental->id]);
    $pallet->refresh();
    $palletNotInRentalCount = $palletDelivery->pallets()->whereNull('rental_id')->count();

    expect($pallet->rental)->toBeInstanceOf(Rental::class)
        ->and($palletNotInRentalCount)->toBe(0);


    return $palletDelivery;
})->depends('set location of only pallet in the fifth pallet delivery');

test('set fifth pallet delivery as booked in', function (PalletDelivery $palletDelivery) {
    SendPalletDeliveryNotification::shouldRun()->andReturn();

    $fulfilmentCustomer = $palletDelivery->fulfilmentCustomer;
    expect($palletDelivery->state)->toBe(PalletDeliveryStateEnum::BOOKING_IN);

    $palletDelivery = SetPalletDeliveryAsBookedIn::make()->action($palletDelivery);
    $palletDelivery->refresh();
    $fulfilmentCustomer->refresh();


    expect($palletDelivery->state)->toBe(PalletDeliveryStateEnum::BOOKED_IN)
        ->and($palletDelivery->booked_in_at)->toBeInstanceOf(Carbon::class);

    $firstPallet = $palletDelivery->pallets->first();

    expect($firstPallet->state)->toBe(PalletStateEnum::STORING);

    return $palletDelivery;
})->depends('set rental to only pallet in the fifth pallet delivery');

test('create second pallet return', function (PalletDelivery $palletDelivery) {
    SendPalletReturnNotification::shouldRun()
        ->andReturn();

    $fulfilmentCustomer = $palletDelivery->fulfilmentCustomer;

    $palletReturn = StorePalletReturn::make()->actionWithStoredItems(
        $fulfilmentCustomer,
        [
            'warehouse_id' => $this->warehouse->id,
        ]
    );
    $fulfilmentCustomer->refresh();
    expect($palletReturn)->toBeInstanceOf(PalletReturn::class)
        ->and($palletReturn->state)->toBe(PalletReturnStateEnum::IN_PROCESS)
        ->and($palletReturn->stats->number_pallets)->toBe(0)
        ->and($palletReturn->type)->toBe(PalletReturnTypeEnum::STORED_ITEM)
        ->and($fulfilmentCustomer->fulfilment->stats->number_pallet_returns)->toBe(2)
        ->and($fulfilmentCustomer->number_pallet_returns)->toBe(1)
        ->and($fulfilmentCustomer->number_pallet_returns_state_in_process)->toBe(1);

    return $palletReturn;
})->depends('set fifth pallet delivery as booked in');

test('attach stored item second pallet return', function (PalletReturn $palletReturn) {
    SendPalletReturnNotification::shouldRun()
        ->andReturn();

    $fulfilmentCustomer = $palletReturn->fulfilmentCustomer;

    $storedItem       = $fulfilmentCustomer->storedItems()->first();
    $palletStoredItem = $storedItem->palletStoredItems()->first();

    AttachStoredItemToReturn::make()->action(
        $palletReturn,
        $palletStoredItem,
        [
            'quantity_ordered' => 2,
        ]
    );

    $palletReturn->refresh();
    expect($palletReturn)->toBeInstanceOf(PalletReturn::class)
        ->and($palletReturn->storedItems()->count())->toBe(1);

    return $palletReturn;
})->depends('create second pallet return');

test('attach stored item second pallet return with 0 quantity', function (PalletReturn $palletReturn) {
    SendPalletReturnNotification::shouldRun()
        ->andReturn();

    $fulfilmentCustomer = $palletReturn->fulfilmentCustomer;

    $storedItem       = $fulfilmentCustomer->storedItems()->first();
    $palletStoredItem = $storedItem->palletStoredItems()->first();

    AttachStoredItemToReturn::make()->action(
        $palletReturn,
        $palletStoredItem,
        [
            'quantity_ordered' => 0,
        ]
    );

    $palletReturn->refresh();
    expect($palletReturn)->toBeInstanceOf(PalletReturn::class)
        ->and($palletReturn->storedItems()->count())->toBe(0);

    return $palletReturn;
})->depends('create second pallet return');

test('attach stored item second pallet return again', function (PalletReturn $palletReturn) {
    SendPalletReturnNotification::shouldRun()
        ->andReturn();

    $fulfilmentCustomer = $palletReturn->fulfilmentCustomer;

    $storedItem       = $fulfilmentCustomer->storedItems()->first();
    $palletStoredItem = $storedItem->palletStoredItems()->first();

    AttachStoredItemToReturn::make()->action(
        $palletReturn,
        $palletStoredItem,
        [
            'quantity_ordered' => 4,
        ]
    );

    $palletReturn->refresh();
    expect($palletReturn)->toBeInstanceOf(PalletReturn::class)
        ->and($palletReturn->storedItems()->count())->toBe(1);

    return $palletReturn;
})->depends('create second pallet return');

test('submit second pallet return', function (PalletReturn $palletReturn) {
    SendPalletReturnNotification::shouldRun()
        ->andReturn();

    $fulfilmentCustomer = $palletReturn->fulfilmentCustomer;

    $submittedPalletReturn = SubmitAndConfirmPalletReturn::make()->action($palletReturn);

    $fulfilmentCustomer->refresh();
    expect($submittedPalletReturn)->toBeInstanceOf(PalletReturn::class)
        ->and($submittedPalletReturn->state)->toBe(PalletReturnStateEnum::CONFIRMED);

    return $submittedPalletReturn;
})->depends('create second pallet return');


test('picking second pallet to return', function (PalletReturn $submittedPalletReturn) {
    SendPalletReturnNotification::shouldRun()
        ->andReturn();

    $fulfilmentCustomer = $submittedPalletReturn->fulfilmentCustomer;


    $pickingPalletReturn = PickingPalletReturn::make()->action(
        $submittedPalletReturn,
    );
    // dd($storedPallet);
    $fulfilmentCustomer->refresh();
    expect($pickingPalletReturn)->toBeInstanceOf(PalletReturn::class)
        ->and($pickingPalletReturn->state)->toBe(PalletReturnStateEnum::PICKING);

    return $pickingPalletReturn;
})->depends('submit second pallet return');

test('pick pallet return item', function (PalletReturn $palletReturn) {
    SendPalletReturnNotification::shouldRun()
        ->andReturn();

    $palletReturnItem = PalletReturnItem::where('pallet_return_id', $palletReturn->id)->first();

    $palletReturnItem = PickPalletReturnItemInPalletReturnWithStoredItem::make()->action(
        $palletReturnItem,
        [
            'quantity_picked' => 4
        ],
    );

    $palletReturn->refresh();
    expect($palletReturn)->toBeInstanceOf(PalletReturn::class)
        ->and($palletReturn->state)->toBe(PalletReturnStateEnum::PICKED)
        ->and($palletReturnItem)->toBeInstanceOf(PalletReturnItem::class)
        ->and($palletReturnItem->quantity_picked)->toBe(4)
        ->and($palletReturnItem->state)->toBe(PalletReturnItemStateEnum::PICKED);

    return $palletReturn;
})->depends('picking second pallet to return');

test('hydrate fulfilment command', function () {
    $this->artisan('hydrate:fulfilments '.$this->organisation->slug)->assertExitCode(0);
});

test('hydrate fulfilment customer command', function () {
    $this->artisan('hydrate:fulfilment_customers '.$this->organisation->slug)->assertExitCode(0);
});

test('hydrate pallet delivery command', function () {
    $this->artisan('hydrate:pallet_deliveries  '.$this->organisation->slug)->assertExitCode(0);
});

test('hydrate rental agreements command', function () {
    $this->artisan('hydrate:rental_agreements  '.$this->organisation->slug)->assertExitCode(0);
});


test('create sixth pallet delivery (consolidation test)', function ($fulfilmentCustomer) {
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
        ->and($palletDelivery->state)->toBe(PalletDeliveryStateEnum::IN_PROCESS);

    return $palletDelivery;
})->depends('create third fulfilment customer');

test('add pallet to sixth pallet delivery', function (PalletDelivery $palletDelivery) {
    $pallet = StorePalletCreatedInPalletDelivery::make()->action(
        $palletDelivery,
        [
            'customer_reference' => 'RefA',
            'type'               => PalletTypeEnum::PALLET->value,
        ]
    );

    $palletDelivery->refresh();
    expect($pallet)->toBeInstanceOf(Pallet::class)
        ->and($palletDelivery->stats->number_services)->toBe(1)
        ->and($pallet->state)->toBe(PalletStateEnum::IN_PROCESS)
        ->and($pallet->status)->toBe(PalletStatusEnum::IN_PROCESS)
        ->and($pallet->type)->toBe(PalletTypeEnum::PALLET)
        ->and($pallet->fulfilmentCustomer->number_pallets)->toBe(2)
        ->and($palletDelivery->stats->number_pallets)->toBe(1)
        ->and($palletDelivery->stats->number_pallets_type_pallet)->toBe(1);


    return $pallet;
})->depends('create sixth pallet delivery (consolidation test)');

test('create 2 stored item and attach to pallet (6th delivery)', function (Pallet $pallet) {
    $storedItem       = StoreStoredItem::make()->action(
        $pallet->fulfilmentCustomer,
        [
            'reference' => 'Bao',
        ]
    );
    $secondStoredItem = StoreStoredItem::make()->action(
        $pallet->fulfilmentCustomer,
        [
            'reference' => 'Boa',
        ]
    );

    SyncStoredItemToPallet::make()->action(
        $pallet,
        [
            'stored_item_ids' => [
                $storedItem->id       => [
                    'quantity' => 8
                ],
                $secondStoredItem->id => [
                    'quantity' => 10
                ]
            ]
        ]
    );

    $pallet->refresh();

    expect($storedItem)->toBeInstanceOf(StoredItem::class)
        ->and($pallet)->toBeInstanceOf(Pallet::class)
        ->and($pallet->storedItems()->count())->toBe(2);

    return $pallet;
})->depends('add pallet to sixth pallet delivery');

test('submit and confirm sixth pallet delivery', function (Pallet $pallet) {
    SendPalletDeliveryNotification::shouldRun()->andReturn();

    $palletDelivery = SubmitAndConfirmPalletDelivery::make()->action($pallet->palletDelivery);

    $pallet = $palletDelivery->pallets->first();

    $palletDelivery->refresh();

    expect($palletDelivery->state)->toBe(PalletDeliveryStateEnum::CONFIRMED)
        ->and($palletDelivery->confirmed_at)->toBeInstanceOf(Carbon::class)
        ->and($palletDelivery->stats->number_pallets)->toBe(1)
        ->and($pallet->storedItems()->count())->toBe(2)
        ->and($pallet->state)->toBe(PalletStateEnum::CONFIRMED)
        ->and($pallet->status)->toBe(PalletStatusEnum::RECEIVING);

    return $palletDelivery;
})->depends('create 2 stored item and attach to pallet (6th delivery)');

test('receive sixth pallet delivery', function (PalletDelivery $palletDelivery) {
    SendPalletDeliveryNotification::shouldRun()->andReturn();

    $palletDelivery = ReceivePalletDelivery::make()->action($palletDelivery);

    $palletDelivery->refresh();

    expect($palletDelivery->state)->toBe(PalletDeliveryStateEnum::RECEIVED)
        ->and($palletDelivery->received_at)->toBeInstanceOf(Carbon::class);

    return $palletDelivery;
})->depends('submit and confirm sixth pallet delivery');

test('start booking-in sixth pallet delivery', function (PalletDelivery $palletDelivery) {
    $palletDelivery = StartBookingPalletDelivery::make()->action($palletDelivery);

    $palletDelivery->refresh();


    expect($palletDelivery->state)->toBe(PalletDeliveryStateEnum::BOOKING_IN)
        ->and($palletDelivery->booking_in_at)->toBeInstanceOf(Carbon::class);

    return $palletDelivery;
})->depends('receive sixth pallet delivery');

test('set location of only pallet in the sixth pallet delivery', function (PalletDelivery $palletDelivery) {
    $pallet = $palletDelivery->pallets->first();
    /** @var Location $location */
    $location = $this->warehouse->locations()->first();

    BookInPallet::make()->action($pallet, ['location_id' => $location->id]);
    $pallet->refresh();
    expect($pallet->location)->toBeInstanceOf(Location::class)
        ->and($pallet->location->id)->toBe($location->id)
        ->and($pallet->received_at)->toBeInstanceOf(Carbon::class)
        ->and($pallet->booked_in_at)->toBeInstanceOf(Carbon::class)
        ->and($pallet->set_as_not_received_at)->toBeNull()
        ->and($pallet->state)->toBe(PalletStateEnum::BOOKED_IN)
        ->and($pallet->status)->toBe(PalletStatusEnum::RECEIVING);

    return $palletDelivery;
})->depends('start booking-in sixth pallet delivery');

test('set rental to only pallet in the sixth pallet delivery', function (PalletDelivery $palletDelivery) {
    $pallet = $palletDelivery->pallets->first();
    $rental = $palletDelivery->fulfilment->rentals->last();
    expect($rental)->toBeInstanceOf(Rental::class);

    SetPalletRental::make()->action($pallet, ['rental_id' => $rental->id]);
    $pallet->refresh();
    $palletNotInRentalCount = $palletDelivery->pallets()->whereNull('rental_id')->count();

    expect($pallet->rental)->toBeInstanceOf(Rental::class)
        ->and($palletNotInRentalCount)->toBe(0);


    return $palletDelivery;
})->depends('set location of only pallet in the sixth pallet delivery');

test('set sixth pallet delivery as booked in', function (PalletDelivery $palletDelivery) {
    SendPalletDeliveryNotification::shouldRun()->andReturn();

    $fulfilmentCustomer = $palletDelivery->fulfilmentCustomer;
    expect($palletDelivery->state)->toBe(PalletDeliveryStateEnum::BOOKING_IN);

    $palletDelivery = SetPalletDeliveryAsBookedIn::make()->action($palletDelivery);
    $palletDelivery->refresh();
    $fulfilmentCustomer->refresh();


    expect($palletDelivery->state)->toBe(PalletDeliveryStateEnum::BOOKED_IN)
        ->and($palletDelivery->booked_in_at)->toBeInstanceOf(Carbon::class);

    $firstPallet = $palletDelivery->pallets->first();

    expect($firstPallet->state)->toBe(PalletStateEnum::STORING);

    return $palletDelivery->fulfilmentCustomer;
})->depends('set rental to only pallet in the sixth pallet delivery');

test('check current recurring bill', function ($fulfilmentCustomer) {
    $recurringBill = $fulfilmentCustomer->currentRecurringBill;
    expect($recurringBill)->toBeInstanceOf(RecurringBill::class)
        ->and($recurringBill->transactions()->count())->toBe(4);

    return $fulfilmentCustomer;
})->depends('set sixth pallet delivery as booked in');

test('consolidate recurring bill', function ($fulfilmentCustomer) {
    $recurringBill = $fulfilmentCustomer->currentRecurringBill;

    ConsolidateRecurringBill::make()->action($recurringBill);

    $recurringBill->refresh();
    $fulfilmentCustomer->refresh();

    $newRecurringBill = $fulfilmentCustomer->currentRecurringBill;
    // dd($recurringBill->transactions);

    expect($newRecurringBill)->not->toBe($recurringBill)
        ->and($newRecurringBill)->toBeInstanceOf(RecurringBill::class)
        ->and($newRecurringBill->status)->toBe(RecurringBillStatusEnum::CURRENT)
        ->and($newRecurringBill->transactions()->count())->toBe(2)
        ->and($recurringBill->status)->toBe(RecurringBillStatusEnum::FORMER)
        ->and($recurringBill->invoices)->not->toBeNull()
        ->and($recurringBill->invoices)->toBeInstanceOf(Invoice::class)
        ->and($fulfilmentCustomer->recurringBills()->count())->toBe(2);

    return $fulfilmentCustomer;
})->depends('check current recurring bill');

test('update third rental agreement cause', function ($fulfilmentCustomer) {
    $recurringBillTransaction = $fulfilmentCustomer->currentRecurringBill->transactions->first();

    $rentalAgreement = UpdateRentalAgreement::make()->action(
        $fulfilmentCustomer->rentalAgreement,
        [
            'update_all' => true,
            'clauses'    => [
                'rentals' => [
                    [
                        'asset_id'       => $recurringBillTransaction->asset_id,
                        'percentage_off' => 30,
                    ],
                    [
                        'asset_id'       => $fulfilmentCustomer->fulfilment->rentals->last()->asset_id,
                        'percentage_off' => 50,
                    ],
                ]
            ]
        ]
    );
    $rentalAgreement->refresh();
    $fulfilmentCustomer->refresh();
    $fulfilmentCustomer->currentRecurringBill->refresh();
    $recurringBillTransaction->refresh();

    expect($rentalAgreement->stats->number_rental_agreement_clauses)->toBe(2)
        ->and($rentalAgreement->stats->number_rental_agreement_clauses_type_rental)->toBe(2)
        ->and($rentalAgreement->clauses->first()->percentage_off)->toEqualWithDelta(30, .001)
        ->and($rentalAgreement->clauses->last()->percentage_off)->toEqualWithDelta(50, .001)
        ->and($rentalAgreement->stats->number_rental_agreement_snapshots)->toBe(2)
        ->and($recurringBillTransaction->clause)->not->toBeNull();

    return $fulfilmentCustomer;
})->depends('consolidate recurring bill');

test('pay invoice (full)', function ($fulfilmentCustomer) {
    $oldRecurringBill = $fulfilmentCustomer->recurringBills->first();
    $invoice          = $oldRecurringBill->invoices;

    $paymentAccountShop = $invoice->shop->paymentAccountShops()->first();
    $paymentAccount     = $paymentAccountShop->paymentAccount;
    $payment            = PayInvoice::make()->action($invoice, $paymentAccount, [
        'amount' => $invoice->total_amount,
        'status' => PaymentStatusEnum::SUCCESS->value,
        'state'  => PaymentStateEnum::COMPLETED->value
    ]);
    $invoice->refresh();

    expect($invoice->total_amount)->tobe($invoice->payment_amount)
        ->and($payment)->toBeInstanceOf(Payment::class)
        ->and($payment->status)->toBe(PaymentStatusEnum::SUCCESS)
        ->and($payment->state)->toBe(PaymentStateEnum::COMPLETED);

    return $fulfilmentCustomer;
})->depends('update third rental agreement cause');

test('consolidate 2nd recurring bill', function ($fulfilmentCustomer) {
    $recurringBill = $fulfilmentCustomer->currentRecurringBill;
    // dd($recurringBill->transactions);
    ConsolidateRecurringBill::make()->action($recurringBill);

    $recurringBill->refresh();
    $fulfilmentCustomer->refresh();

    $newRecurringBill = $fulfilmentCustomer->currentRecurringBill;

    expect($newRecurringBill)->not->toBe($recurringBill)
        ->and($newRecurringBill)->toBeInstanceOf(RecurringBill::class)
        ->and($newRecurringBill->status)->toBe(RecurringBillStatusEnum::CURRENT)
        ->and($newRecurringBill->transactions()->count())->toBe(2)
        ->and($recurringBill->status)->toBe(RecurringBillStatusEnum::FORMER)
        ->and($recurringBill->invoices)->not->toBeNull()
        ->and($recurringBill->invoices)->toBeInstanceOf(Invoice::class)
        ->and($fulfilmentCustomer->recurringBills()->count())->toBe(3);

    return $recurringBill->invoices;
})->depends('pay invoice (full)');

test('pay invoice (half)', function ($invoice) {
    $paymentAccountShop = $invoice->shop->paymentAccountShops()->first();
    $paymentAccount     = $paymentAccountShop->paymentAccount;

    $payment = PayInvoice::make()->action($invoice, $paymentAccount, [
        'amount' => 70,
        'status' => PaymentStatusEnum::SUCCESS->value,
        'state'  => PaymentStateEnum::COMPLETED->value
    ]);
    $invoice->refresh();

    expect($invoice->total_amount)->not->tobe($invoice->payment_amount)
        ->and($payment)->toBeInstanceOf(Payment::class)
        ->and($payment->status)->toBe(PaymentStatusEnum::SUCCESS)
        ->and($payment->state)->toBe(PaymentStateEnum::COMPLETED);

    return $invoice;
})->depends('consolidate 2nd recurring bill');

test('pay invoice (other half)', function ($invoice) {
    $paymentAccountShop = $invoice->shop->paymentAccountShops()->first();
    $paymentAccount     = $paymentAccountShop->paymentAccount;
    $fulfilmentCustomer = $invoice->customer->fulfilmentCustomer;
    $payment            = PayInvoice::make()->action($invoice, $paymentAccount, [
        'amount' => 70,
        'status' => PaymentStatusEnum::SUCCESS->value,
        'state'  => PaymentStateEnum::COMPLETED->value
    ]);
    $invoice->refresh();

    expect($invoice->total_amount)->toBe($invoice->payment_amount)
        ->and($payment)->toBeInstanceOf(Payment::class)
        ->and($payment->status)->toBe(PaymentStatusEnum::SUCCESS)
        ->and($payment->state)->toBe(PaymentStateEnum::COMPLETED);

    return $fulfilmentCustomer;
})->depends('pay invoice (half)');

test('consolidate 3rd recurring bill', function ($fulfilmentCustomer) {
    $recurringBill = $fulfilmentCustomer->currentRecurringBill;

    ConsolidateRecurringBill::make()->action($recurringBill);

    $recurringBill->refresh();
    $fulfilmentCustomer->refresh();
    $newRecurringBill = $fulfilmentCustomer->currentRecurringBill;

    expect($newRecurringBill)->not->toBe($recurringBill)
        ->and($newRecurringBill)->toBeInstanceOf(RecurringBill::class)
        ->and($newRecurringBill->status)->toBe(RecurringBillStatusEnum::CURRENT)
        ->and($newRecurringBill->transactions()->count())->toBe(2)
        ->and($recurringBill->status)->toBe(RecurringBillStatusEnum::FORMER)
        ->and($recurringBill->invoices)->not->toBeNull()
        ->and($recurringBill->invoices)->toBeInstanceOf(Invoice::class)
        ->and($fulfilmentCustomer->recurringBills()->count())->toBe(4);

    return $recurringBill->invoices;
})->depends('pay invoice (other half)');

test('pay invoice (exceed)', function ($invoice) {
    expect($invoice->total_amount)->toBe("140.00")
        ->and($invoice->payment_amount)->toBe("0.00");

    $customer           = $invoice->customer;
    $paymentAccountShop = $invoice->shop->paymentAccountShops()->first();
    $paymentAccount     = $paymentAccountShop->paymentAccount;
    $fulfilmentCustomer = $invoice->customer->fulfilmentCustomer;
    $payment            = PayInvoice::make()->action($invoice, $paymentAccount, [
        'amount' => 200,
        'status' => PaymentStatusEnum::SUCCESS->value,
        'state'  => PaymentStateEnum::COMPLETED->value
    ]);
    $customer->refresh();
    $invoice->refresh();

    expect($payment)->toBeInstanceOf(Payment::class)
        ->and($payment->status)->toBe(PaymentStatusEnum::SUCCESS)
        ->and($payment->state)->toBe(PaymentStateEnum::COMPLETED)
        ->and($customer->creditTransactions)->not->toBeNull()
        ->and($customer->balance)->toBe("60.00")
        ->and($customer->creditTransactions->first()->amount)->toBe("60.00");

    return $fulfilmentCustomer;
})->depends('consolidate 3rd recurring bill');

test('create seventh pallet delivery (pallet with stored items import test)', function ($fulfilmentCustomer) {
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
        ->and($palletDelivery->stats->number_pallets)->toBe(0);

    return $palletDelivery;
})->depends('create 5th fulfilment customer');

test('import pallet with stored items (xlsx)', function (PalletDelivery $palletDelivery) {
    Storage::fake('local');

    $tmpPath = 'tmp/uploads/';

    $filePath = base_path('tests/fixtures/PalletStoredItemsTest.xlsx');
    $file     = new UploadedFile($filePath, 'PalletStoredItemsTest.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    Storage::fake('local')->put($tmpPath, $file);
    $fulfilmentCustomer = $palletDelivery->fulfilmentCustomer;
    expect($palletDelivery->stats->number_pallets)->toBe(0)
        ->and($fulfilmentCustomer->number_stored_items)->toBe(2);

    $upload = ImportPalletsInPalletDeliveryWithStoredItems::run($palletDelivery, $file, [
    ]);

    $palletDelivery->refresh();
    $fulfilmentCustomer->refresh();

    expect($upload)->toBeInstanceOf(Upload::class)
        ->and($upload->number_rows)->toBe(5)
        ->and($upload->number_success)->toBe(3)
        ->and($upload->number_fails)->toBe(0)
        ->and($palletDelivery->stats->number_pallets)->toBe(3)
        ->and($palletDelivery->stats->number_pallets_type_pallet)->toBe(1)
        ->and($palletDelivery->stats->number_pallets_type_box)->toBe(1)
        ->and($palletDelivery->stats->number_pallets_type_oversize)->toBe(1)
        ->and($fulfilmentCustomer->number_stored_items)->toBe(5);


    return $palletDelivery;
})->depends('create seventh pallet delivery (pallet with stored items import test)');

test('import pallet with stored items (xlsx) duplicate', function (PalletDelivery $palletDelivery) {
    Storage::fake('local');

    $tmpPath = 'tmp/uploads/';

    $filePath = base_path('tests/fixtures/PalletStoredItemsTest.xlsx');
    $file     = new UploadedFile($filePath, 'PalletStoredItemsTest.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    Storage::fake('local')->put($tmpPath, $file);
    $fulfilmentCustomer = $palletDelivery->fulfilmentCustomer;
    expect($palletDelivery->stats->number_pallets)->toBe(3)
        ->and($fulfilmentCustomer->number_stored_items)->toBe(5);

    $upload = ImportPalletsInPalletDeliveryWithStoredItems::run($palletDelivery, $file, [
    ]);

    $palletDelivery->refresh();


    expect($upload)->toBeInstanceOf(Upload::class)
        ->and($upload->number_rows)->toBe(5)
        ->and($upload->number_success)->toBe(0)
        ->and($upload->number_fails)->toBe(5)
        ->and($palletDelivery->stats->number_pallets)->toBe(3)
        ->and($palletDelivery->stats->number_pallets_type_pallet)->toBe(1)
        ->and($palletDelivery->stats->number_pallets_type_box)->toBe(1)
        ->and($palletDelivery->stats->number_pallets_type_oversize)->toBe(1);

    return $palletDelivery;
})->depends('import pallet with stored items (xlsx)');

test('hydrate pallet return command', function () {
    $this->artisan('hydrate:pallet_returns  '.$this->organisation->slug)->assertExitCode(0);
});

test('fulfilment customers search', function () {
    $this->artisan('search:fulfilment_customers')->assertExitCode(0);

    $fulfilmentCustomers = FulfilmentCustomer::first();
    ReindexFulfilmentCustomerSearch::run($fulfilmentCustomers);
    expect($fulfilmentCustomers->universalSearch()->count())->toBe(1);
});

test('update current recurring bills', function () {
    $this->artisan('current_recurring_bills:update_temporal_aggregates')->assertExitCode(0);
});

test('create stored item audit', function (FulfilmentCustomer $fulfilmentCustomer) {
    $storedItemAudit = StoreStoredItemAudit::make()->action($fulfilmentCustomer, [
    ]);

    $fulfilmentCustomer->refresh();
    expect($storedItemAudit)->toBeInstanceOf(StoredItemAudit::class)
        ->and($fulfilmentCustomer->number_stored_item_audits)->toBe(1);

    return $storedItemAudit;
})->depends('create third fulfilment customer');

test('update stored item audit', function (StoredItemAudit $storedItemAudit) {
    $storedItemAudit = UpdateStoredItemAudit::make()->action($storedItemAudit, [
        'public_notes' => 'Public notes',
    ]);

    expect($storedItemAudit)->toBeInstanceOf(StoredItemAudit::class)
        ->and($storedItemAudit->public_notes)->toBe('Public notes');

    return $storedItemAudit;
})->depends('create stored item audit');

test('create stored item audit delta', function (StoredItemAudit $storedItemAudit) {
    $fulfilmentCustomer = $storedItemAudit->fulfilmentCustomer;
    $palletStoredItems  = PalletStoredItem::whereHas('pallet', function ($query) use ($fulfilmentCustomer) {
        $query->where('fulfilment_customer_id', $fulfilmentCustomer->id);
    })->get();

    /** @var PalletStoredItem $palletStoredItem */

    $palletStoredItem = $palletStoredItems->first();

    $storedItemAuditDelta = StoreStoredItemAuditDelta::make()->action($storedItemAudit, [
        'pallet_id'        => $palletStoredItem->pallet_id,
        'stored_item_id'   => $palletStoredItem->stored_item_id,
        'audited_quantity' => 15,
        'user_id'          => $this->user->id
    ]);

    $storedItemAudit->refresh();

    expect($storedItemAudit)->toBeInstanceOf(StoredItemAudit::class)
        ->and($storedItemAudit->number_audited_stored_items)->toBe(1)
        ->and($storedItemAuditDelta)->toBeInstanceOf(StoredItemAuditDelta::class)
        ->and((int) $storedItemAuditDelta->audited_quantity)->toBe(15);
    return $storedItemAuditDelta;
})->depends('update stored item audit');

test('update stored item audit delta', function (StoredItemAuditDelta $storedItemAuditDelta) {
    $storedItemAuditDelta = UpdateStoredItemAuditDelta::make()->action($storedItemAuditDelta, [
        'audited_quantity' => 17,
        'user_id'          => $this->user->id
    ]);

    $storedItemAuditDelta->refresh();

    expect($storedItemAuditDelta)->toBeInstanceOf(StoredItemAuditDelta::class)
        ->and(intval($storedItemAuditDelta->audited_quantity))->toBe(17);

    return $storedItemAuditDelta;
})->depends('create stored item audit delta');

test('delete stored item audit delta', function (StoredItemAuditDelta $storedItemAuditDelta) {
    $storedItemAudit = $storedItemAuditDelta->storedItemAudit;

    DeleteStoredItemAuditDelta::make()->action($storedItemAuditDelta, []);

    $storedItemAudit->refresh();

    expect($storedItemAudit)->toBeInstanceOf(StoredItemAudit::class)
        ->and($storedItemAudit->number_audited_stored_items)->toBe(0);

    return $storedItemAudit;
})->depends('update stored item audit delta');

test('complete stored item audit', function (StoredItemAudit $storedItemAudit) {
    $fulfilmentCustomer = $storedItemAudit->fulfilmentCustomer;
    $palletStoredItems  = PalletStoredItem::whereHas('pallet', function ($query) use ($fulfilmentCustomer) {
        $query->where('fulfilment_customer_id', $fulfilmentCustomer->id);
    })->get();

    /** @var PalletStoredItem $palletStoredItem */

    $palletStoredItem = $palletStoredItems->first();

    $storedItem = StoreStoredItem::make()->action(
        $fulfilmentCustomer,
        [
            'reference' => 'referenceA',
            'name'      => 'jeff'
        ]
    );

    $storedItemAuditDelta = StoreStoredItemAuditDelta::make()->action($storedItemAudit, [
        'pallet_id'        => $palletStoredItem->pallet_id,
        'stored_item_id'   => $palletStoredItem->stored_item_id,
        'audited_quantity' => 15,
        'user_id'          => $this->user->id
    ]);

    $storedItemAuditDelta2 = StoreStoredItemAuditDelta::make()->action($storedItemAudit, [
        'pallet_id'        => $palletStoredItem->pallet_id,
        'stored_item_id'   => $storedItem->id,
        'audited_quantity' => 10,
        'user_id'          => $this->user->id
    ]);

    $storedItemAudit->refresh();

    expect($storedItemAuditDelta)->toBeInstanceOf(StoredItemAuditDelta::class)
        ->and($storedItemAuditDelta->state)->toBe(StoredItemAuditDeltaStateEnum::IN_PROCESS)
        ->and($storedItemAuditDelta2)->toBeInstanceOf(StoredItemAuditDelta::class)
        ->and($storedItemAuditDelta2->state)->toBe(StoredItemAuditDeltaStateEnum::IN_PROCESS);

    $storedItemAudit = CompleteStoredItemAudit::make()->action($storedItemAudit, []);

    $storedItemAudit->refresh();

    $delta  = $storedItemAudit->deltas()->first();
    $delta2 = $storedItemAudit->deltas()->skip(1)->first();

    expect($storedItemAudit)->toBeInstanceOf(StoredItemAudit::class)
        ->and($storedItemAudit->state)->toBe(StoredItemAuditStateEnum::COMPLETED)
        ->and($delta)->toBeInstanceOf(StoredItemAuditDelta::class)
        ->and($delta->state)->toBe(StoredItemAuditDeltaStateEnum::COMPLETED)
        ->and($delta2)->toBeInstanceOf(StoredItemAuditDelta::class)
        ->and($delta2->state)->toBe(StoredItemAuditDeltaStateEnum::COMPLETED);

    return $storedItemAudit;
})->depends('delete stored item audit delta');

test('store standalone invoice', function () {
    $fulfilmentCustomer = FulfilmentCustomer::first();

    $invoice = StoreStandaloneFulfilmentInvoice::make()->action($fulfilmentCustomer, []);

    expect($invoice)->toBeInstanceOf(Invoice::class);

    return $invoice;
});

test('store standalone invoice transaction', function (Invoice $invoice) {
    /** @var Service $service */
    $service            = Service::first();
    $invoiceTransaction = StoreStandaloneFulfilmentInvoiceTransaction::make()->action($invoice, $service->historicAsset, [
        'quantity' => 10
    ]);
    $invoice->refresh();
    expect($invoice)->toBeInstanceOf(Invoice::class)
        ->and($invoice->in_process)->toBeTrue()
        ->and($invoiceTransaction)->toBeInstanceOf(InvoiceTransaction::class)
        ->and($invoiceTransaction->in_process)->toBeTrue()
        ->and(intval($invoiceTransaction->quantity))->toBe(10);

    return $invoiceTransaction;
})->depends('store standalone invoice');

test('update standalone invoice transaction', function (InvoiceTransaction $invoiceTransaction) {
    $invoiceTransaction = UpdateStandaloneFulfilmentInvoiceTransaction::make()->action($invoiceTransaction, [
        'quantity' => 100
    ]);
    $invoiceTransaction->refresh();

    expect($invoiceTransaction)->toBeInstanceOf(InvoiceTransaction::class)
        ->and($invoiceTransaction->in_process)->toBeTrue()
        ->and(intval($invoiceTransaction->quantity))->toBe(100);

    return $invoiceTransaction;
})->depends('store standalone invoice transaction');

test('delete standalone invoice transaction', function (InvoiceTransaction $invoiceTransaction) {
    $invoice = $invoiceTransaction->invoice;
    expect($invoice->invoiceTransactions()->count())->toBe(1);

    DeleteStandaloneFulfilmentInvoiceTransaction::make()->action($invoiceTransaction);
    $invoice->refresh();

    expect($invoice->invoiceTransactions()->count())->toBe(0);

    return $invoice;
})->depends('update standalone invoice transaction');

test('complete standalone invoice', function (Invoice $invoice) {
    $service            = Service::first();
    $invoiceTransaction = StoreStandaloneFulfilmentInvoiceTransaction::make()->action($invoice, $service->historicAsset, [
        'quantity' => 1000
    ]);
    $invoice->refresh();
    expect($invoice)->toBeInstanceOf(Invoice::class)
        ->and($invoice->in_process)->toBeTrue()
        ->and($invoiceTransaction)->toBeInstanceOf(InvoiceTransaction::class)
        ->and($invoiceTransaction->in_process)->toBeTrue()
        ->and(intval($invoiceTransaction->quantity))->toBe(1000);

    $invoice = CompleteStandaloneFulfilmentInvoice::make()->action($invoice);

    $invoice->refresh();
    expect($invoice)->toBeInstanceOf(Invoice::class)
        ->and($invoice->in_process)->toBeFalse();

    return $invoice;
})->depends('delete standalone invoice transaction');

// partial refund
test('create partial refund', function () {

    $invoice = Invoice::where('pay_status', InvoicePayStatusEnum::PAID)->first();
    $refund = StoreRefund::make()->action($invoice, []);

    expect($refund)->toBeInstanceOf(Invoice::class)
        ->and($refund->in_process)->toBeTrue()
        ->and($refund->original_invoice_id)->toBe($invoice->id)
        ->and($refund->type)->toBe(InvoiceTypeEnum::REFUND);

    return [$invoice, $refund];
});

test('store partial refund transaction', function (array $data) {
    [$invoice, $refund] = $data;
    $invoiceTransaction = $invoice->invoiceTransactions()->first();
    $refundTransaction = StoreRefundInvoiceTransaction::make()->action($refund, $invoiceTransaction, [
        'net_amount' => $invoiceTransaction->net_amount / 2,
    ]);

    $refund->refresh();

    expect($refund)->toBeInstanceOf(Invoice::class)
        ->and($refund->in_process)->toBeTrue()
        ->and($refundTransaction)->toBeInstanceOf(InvoiceTransaction::class)
        ->and($refundTransaction->in_process)->toBeTrue()
        ->and(floatval($refundTransaction->net_amount))->toBe(($invoiceTransaction->net_amount / 2) * -1);

    return $refundTransaction;
})->depends('create partial refund');

test('finalise refund', function (array $data, InvoiceTransaction $refundTransaction) {
    [$invoice, $refund] = $data;

    FinaliseRefund::make()->action($refund, []);

    $refund->refresh();
    $invoice->refresh();

    $refundTransaction->refresh();

    expect($refund)->toBeInstanceOf(Invoice::class)
        ->and($refund->in_process)->toBeFalse()
        ->and($refundTransaction)->toBeInstanceOf(InvoiceTransaction::class)
        ->and($refundTransaction->in_process)->toBeFalse();

    return [$invoice, $refund];

})->depends('create partial refund', 'store partial refund transaction');


// refund to credit balance
test('refund to credit balance', function (array $data) {
    /** @var Invoice $invoice */
    /** @var Invoice $refund */
    [$invoice, $refund] = $data;

    $invoice = RefundToCredit::make()->action($invoice, [
        'amount' => $refund->total_amount
    ]);

    $invoice->refresh();
    $refund->refresh();

    $refundPayment = $refund->payments->first();
    $refundPaymentInvoice = $invoice->payments->where('type', PaymentTypeEnum::REFUND)->first();

    expect($refund)->toBeInstanceOf(Invoice::class)
        ->and($refund->pay_status)->toBe(InvoicePayStatusEnum::PAID)
        ->and($refund->payments->count())->toBe(1)
        ->and($refundPayment->amount)->toBe($refund->total_amount)
        ->and($refundPaymentInvoice->amount)->toBe($refund->total_amount)
        ->and($refundPayment->type)->toBe(PaymentTypeEnum::REFUND)
        ->and($refundPayment->status)->toBe(PaymentStatusEnum::SUCCESS)
        ->and($refundPayment->state)->toBe(PaymentStateEnum::COMPLETED);

})->depends('finalise refund');


test('store audit for pallet 6th customer', function () {


    $fulfilmentCustomer = FulfilmentCustomer::find(6);
    $pallet             = Pallet::where('state', PalletStateEnum::STORING)->where('fulfilment_customer_id', $fulfilmentCustomer->id)->first();
    $palletAudit        = StoreStoredItemAuditFromPallet::make()->action($pallet, []);

    expect($palletAudit)->toBeInstanceOf(StoredItemAudit::class)
        ->and($palletAudit->fulfilment_customer_id)->toBe($fulfilmentCustomer->id)
        ->and($palletAudit->state)->toBe(StoredItemAuditStateEnum::IN_PROCESS);
});

test('inventory  hydrator', function () {
    $this->artisan('hydrate -s ful')->assertExitCode(0);
});


// case approve customer
test('create fulfilment customer (pending approval)', function (Fulfilment $fulfilment) {
    $fulfilment->fulfilmentCustomers()->delete();
    $fulfilmentCustomer = StoreFulfilmentCustomer::make()->action(
        $fulfilment,
        [
            'state'           => CustomerStateEnum::IN_PROCESS,
            'status'          => CustomerStatusEnum::PENDING_APPROVAL,
            'contact_name'    => 'Contact A',
            'company_name'    => 'Company A',
            'interest'        => ['pallets_storage', 'items_storage', 'dropshipping'],
            'contact_address' => Address::factory()->definition(),
        ]
    );

    expect($fulfilmentCustomer)->toBeInstanceOf(FulfilmentCustomer::class)
        ->and($fulfilmentCustomer->customer)->toBeInstanceOf(Customer::class)
        ->and($fulfilmentCustomer->customer->status)->toBe(CustomerStatusEnum::PENDING_APPROVAL)
        ->and($fulfilmentCustomer->customer->state)->toBe(CustomerStateEnum::IN_PROCESS)
        ->and($fulfilmentCustomer->customer->is_fulfilment)->toBeTrue()
        ->and($fulfilment->shop->crmStats->number_customers_status_pending_approval)->toBe(3);

    return $fulfilmentCustomer;
})->depends('create fulfilment shop');

test('approve fulfilment customer', function (FulfilmentCustomer $fulfilmentCustomer) {
    $customer = ApproveCustomer::make()->action($fulfilmentCustomer->customer, []);

    expect($customer)->toBeInstanceOf(Customer::class)
        ->and($customer->status)->toBe(CustomerStatusEnum::APPROVED)
        ->and($customer->is_fulfilment)->toBeTrue()
        ->and($customer->shop->crmStats->number_customers_status_approved)->toBe(4);

    //  $fulfilmentCustomer->forceDelete();
})->depends('create fulfilment customer (pending approval)');
