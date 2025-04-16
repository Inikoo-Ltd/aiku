<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 26 Nov 2024 20:58:56 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Accounting\Invoice\StoreRefund;
use App\Actions\Accounting\StandaloneFulfilmentInvoice\StoreStandaloneFulfilmentInvoice;
use App\Actions\Analytics\GetSectionRoute;
use App\Actions\Billables\Rental\StoreRental;
use App\Actions\Billables\Service\StoreService;
use App\Actions\Catalogue\Shop\StoreShop;
use App\Actions\Catalogue\Shop\UpdateShop;
use App\Actions\Comms\OrgPostRoom\StoreOrgPostRoom;
use App\Actions\Comms\Outbox\StoreOutbox;
use App\Actions\Fulfilment\Pallet\AttachPalletToReturn;
use App\Actions\Fulfilment\Pallet\BookInPallet;
use App\Actions\Fulfilment\Pallet\StorePallet;
use App\Actions\Fulfilment\Pallet\StorePalletCreatedInPalletDelivery;
use App\Actions\Fulfilment\PalletDelivery\Notifications\SendPalletDeliveryNotification;
use App\Actions\Fulfilment\PalletDelivery\ReceivePalletDelivery;
use App\Actions\Fulfilment\PalletDelivery\SetPalletDeliveryAsBookedIn;
use App\Actions\Fulfilment\PalletDelivery\StartBookingPalletDelivery;
use App\Actions\Fulfilment\PalletDelivery\StorePalletDelivery;
use App\Actions\Fulfilment\PalletDelivery\SubmitAndConfirmPalletDelivery;
use App\Actions\Fulfilment\PalletReturn\DispatchPalletReturn;
use App\Actions\Fulfilment\PalletReturn\Notifications\SendPalletReturnNotification;
use App\Actions\Fulfilment\PalletReturn\PickedPalletReturn;
use App\Actions\Fulfilment\PalletReturn\PickingPalletReturn;
use App\Actions\Fulfilment\PalletReturn\StorePalletReturn;
use App\Actions\Fulfilment\PalletReturn\SubmitAndConfirmPalletReturn;
use App\Actions\Fulfilment\RecurringBill\ConsolidateRecurringBill;
use App\Actions\Fulfilment\RecurringBill\StoreRecurringBill;
use App\Actions\Fulfilment\RentalAgreement\StoreRentalAgreement;
use App\Actions\Fulfilment\Space\StoreSpace;
use App\Actions\Fulfilment\StoredItem\StoreStoredItem;
use App\Actions\Fulfilment\StoredItemAudit\StoreStoredItemAudit;
use App\Actions\Fulfilment\StoredItemAudit\StoreStoredItemAuditFromPallet;
use App\Actions\Inventory\Location\StoreLocation;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Analytics\AikuSection\AikuSectionEnum;
use App\Enums\Billables\Rental\RentalStateEnum;
use App\Enums\Billables\Rental\RentalUnitEnum;
use App\Enums\Billables\Service\ServiceStateEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\Outbox\OutboxStateEnum;
use App\Enums\Comms\Outbox\OutboxTypeEnum;
use App\Enums\Comms\PostRoom\PostRoomCodeEnum;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Enums\Fulfilment\Pallet\PalletTypeEnum;
use App\Enums\Fulfilment\PalletDelivery\PalletDeliveryStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Enums\Fulfilment\RecurringBill\RecurringBillStatusEnum;
use App\Enums\Fulfilment\RentalAgreement\RentalAgreementBillingCycleEnum;
use App\Enums\Fulfilment\RentalAgreement\RentalAgreementStateEnum;
use App\Enums\Fulfilment\StoredItemAudit\StoredItemAuditStateEnum;
use App\Enums\UI\Accounting\InvoicesInFulfilmentCustomerTabsEnum;
use App\Enums\UI\Fulfilment\FulfilmentAssetsTabsEnum;
use App\Enums\UI\Fulfilment\FulfilmentsTabsEnum;
use App\Enums\UI\Fulfilment\PhysicalGoodsTabsEnum;
use App\Enums\UI\Fulfilment\RentalsTabsEnum;
use App\Enums\UI\Fulfilment\ServicesTabsEnum;
use App\Models\Accounting\Invoice;
use App\Models\Analytics\AikuScopedSection;
use App\Models\Billables\Rental;
use App\Models\Billables\Service;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Outbox;
use App\Models\Comms\PostRoom;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Fulfilment\RecurringBill;
use App\Models\Fulfilment\RentalAgreement;
use App\Models\Fulfilment\StoredItem;
use App\Models\Fulfilment\StoredItemAudit;
use App\Models\Inventory\Location;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

uses()->group('ui');


beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    SendPalletDeliveryNotification::shouldRun()
        ->andReturn();
    SendPalletReturnNotification::shouldRun()
        ->andReturn();

    $this->organisation = createOrganisation();
    $this->adminGuest   = createAdminGuest($this->organisation->group);
    $this->warehouse    = createWarehouse();
    $this->fulfilment   = createFulfilment($this->organisation);
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

    $shop = Shop::first();
    if (!$shop) {
        $storeData = Shop::factory()->definition();
        data_set($storeData, 'type', ShopTypeEnum::FULFILMENT);
        data_set($storeData, 'warehouses', [$this->warehouse->id]);

        $shop = StoreShop::make()->action(
            $this->organisation,
            $storeData
        );
    }
    $this->shop = $shop;

    $this->shop = UpdateShop::make()->action($this->shop, ['state' => ShopStateEnum::OPEN]);

    list(
        $this->tradeUnit,
        $this->product
        ) = createProduct($this->shop);

    $this->website = createWebsite($this->shop);

    $this->customer = createCustomer($this->shop);

    $postRoom = PostRoom::where('code', PostRoomCodeEnum::MARKETING_NOTIFICATION)->first();

    $orgPostRoom = StoreOrgPostRoom::make()->action($postRoom, $this->organisation, []);

    $marketingOutbox = Outbox::where('shop_id', $this->shop->id)->where('type', OutboxTypeEnum::CUSTOMER_NOTIFICATION)->first();

    if (!$marketingOutbox) {
        StoreOutbox::make()->action($orgPostRoom, $this->shop, [
            'code'  => OutboxCodeEnum::SEND_INVOICE_TO_CUSTOMER,
            'type'  => OutboxTypeEnum::CUSTOMER_NOTIFICATION,
            'state' => OutboxStateEnum::ACTIVE,
            'name'  => 'invoice outbox'
        ]);
    }


    $pallet = Pallet::first();
    if (!$pallet) {
        $storeData = Pallet::factory()->definition();
        data_set($storeData, 'state', PalletStateEnum::SUBMITTED);
        data_set($storeData, 'warehouse_id', $this->warehouse->id);


        $pallet = StorePallet::make()->action(
            $this->customer->fulfilmentCustomer,
            $storeData
        );
    }

    $this->pallet = $pallet;

    $palletDelivery = PalletDelivery::first();
    if (!$palletDelivery) {
        data_set($storeData, 'warehouse_id', $this->warehouse->id);
        data_set($storeData, 'state', PalletDeliveryStateEnum::IN_PROCESS);

        $palletDelivery = StorePalletDelivery::make()->action(
            $this->customer->fulfilmentCustomer,
            $storeData
        );
    }

    $this->palletDelivery = $palletDelivery;

    $palletReturn = PalletReturn::first();
    if (!$palletReturn) {
        data_set($storeData, 'warehouse_id', $this->warehouse->id);
        data_set($storeData, 'state', PalletReturnStateEnum::IN_PROCESS);

        $palletReturn = StorePalletReturn::make()->action(
            $this->customer->fulfilmentCustomer,
            $storeData
        );
    }

    $this->palletReturn = $palletReturn;

    $storedItemReturn = PalletReturn::where('type', PalletReturnTypeEnum::STORED_ITEM)->first();
    if (!$storedItemReturn) {
        data_set($storeData, 'warehouse_id', $this->warehouse->id);
        data_set($storeData, 'state', PalletReturnStateEnum::IN_PROCESS);

        $storedItemReturn = StorePalletReturn::make()->actionWithStoredItems(
            $this->customer->fulfilmentCustomer,
            $storeData
        );
    }

    $this->storedItemReturn = $storedItemReturn;

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

    $storedItem = StoredItem::first();
    if (!$storedItem) {
        data_set($storeData, 'reference', 'stored-item-ref');

        $storedItem = StoreStoredItem::make()->action(
            $this->customer->fulfilmentCustomer,
            $storeData
        );
    }

    $this->storedItem = $storedItem;

    $rentalAgreement = RentalAgreement::where('fulfilment_customer_id', $this->customer->fulfilmentCustomer->id)->first();
    if (!$rentalAgreement) {
        data_set($storeData, 'billing_cycle', RentalAgreementBillingCycleEnum::MONTHLY);
        data_set($storeData, 'state', RentalAgreementStateEnum::ACTIVE);
        data_set($storeData, 'username', 'test');
        data_set($storeData, 'email', 'test@aiku.io');


        $rentalAgreement = StoreRentalAgreement::make()->action(
            $this->customer->fulfilmentCustomer,
            $storeData
        );
    }
    $this->rentalAgreement = $rentalAgreement;

    $recurringBill = RecurringBill::first();
    if (!$recurringBill) {
        data_set($storeData, 'start_date', now());

        $recurringBill = StoreRecurringBill::make()->action(
            $this->rentalAgreement,
            $storeData
        );
    }
    $this->recurringBill = $recurringBill;

    $invoice = Invoice::where("type", InvoiceTypeEnum::INVOICE->value)->first();
    if (!$invoice) {
        $invoice = ConsolidateRecurringBill::make()->action($recurringBill);
    }

    $this->invoice = $invoice;

    $refund = Invoice::where("type", InvoiceTypeEnum::REFUND->value)->first();
    if (!$refund) {
        $refund = StoreRefund::make()->action($invoice, []);
    }
    $this->refund = $refund;


    $space = $this->customer->fulfilmentCustomer->spaces()->first();
    if (!$space) {
        $space = StoreSpace::run(
            $this->customer->fulfilmentCustomer,
            [
                'reference'       => 'test',
                'exclude_weekend' => false,
                'start_at'        => now(),
                'end_at'          => now()->addDays(10),
                'rental_id'       => $this->rental->id
            ]
        );
    }

    $this->space = $space;

    $storedItemAudit = StoredItemAudit::where('fulfilment_customer_id', $this->customer->fulfilmentCustomer->id)->first();
    if (!$storedItemAudit) {
        $storedItemAudit = StoreStoredItemAudit::make()->action(
            $this->customer->fulfilmentCustomer,
            [
                'warehouse_id' => $this->warehouse->id,
            ]
        );
    }
    $this->storedItemAudit = $storedItemAudit;

    $this->artisan('group:seed_aiku_scoped_sections')->assertExitCode(0);

    Config::set(
        'inertia.testing.page_paths',
        [resource_path('js/Pages/Grp')]
    );
    actingAs($this->adminGuest->getUser());
});

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

test('UI create fulfilment', function () {
    $response = get(route('grp.org.fulfilments.create', $this->organisation->slug));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->has('title')->has('formData')->has('pageHead')->has('breadcrumbs', 3);
    });
});

test('UI edit fulfilment', function () {
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.fulfilments.show.settings.edit', [$this->organisation->slug, $this->fulfilment->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('formData.blueprint.0.fields', 3)
            ->has('pageHead')
            ->has(
                'formData.args.updateRoute',
                fn(AssertableInertia $page) => $page
                    ->where('name', 'grp.models.fulfilment.update')
                    ->where('parameters', [$this->fulfilment->id])
            )
            ->has('breadcrumbs', 3);
    });
});

test('UI show fulfilment shop', function () {
    $fulfilment = $this->shop->fulfilment;
    $response   = get(
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
});


test('UI show fulfilment shop customers list', function () {
    $fulfilment = $this->shop->fulfilment;

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
            ->component('Org/Fulfilment/FulfilmentCustomers')
            ->has('title')
            ->has('breadcrumbs', 3);
    });
});

test('UI show fulfilment shop customers pending approval list', function () {
    $fulfilment = $this->shop->fulfilment;

    $response = get(
        route(
            'grp.org.fulfilments.show.crm.customers.pending_approval.index',
            [
                $this->organisation->slug,
                $fulfilment->slug
            ]
        )
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/FulfilmentCustomers')
            ->has('title')
            ->has('breadcrumbs', 3);
    });
});

test('UI show fulfilment shop customers reject list', function () {
    $fulfilment = $this->shop->fulfilment;

    $response = get(
        route(
            'grp.org.fulfilments.show.crm.customers.rejected.index',
            [
                $this->organisation->slug,
                $fulfilment->slug
            ]
        )
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/FulfilmentCustomers')
            ->has('title')
            ->has('breadcrumbs', 3);
    });
});


// Indexes

test('UI Index fulfilment assets', function () {
    $response = $this->get(route('grp.org.fulfilments.show.catalogue.index', [$this->organisation->slug, $this->fulfilment->slug]));

    expect(FulfilmentAssetsTabsEnum::DASHBOARD->value)->toBe('dashboard');

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/Products')
            ->has('title')
            ->has('tabs')
            ->has('breadcrumbs', 3);
    });
});

test('UI Index fulfilment physical goods', function () {
    $response = $this->get(route('grp.org.fulfilments.show.catalogue.physical_goods.index', [$this->organisation->slug, $this->fulfilment->slug]));

    expect(PhysicalGoodsTabsEnum::PHYSICAL_GOODS->value)->toBe('physical_goods');

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/PhysicalGoods')
            ->has('title')
            ->has('tabs')
            ->has('breadcrumbs', 4);
    });
});

test('UI Index fulfilment rentals', function () {
    $response = $this->get(route('grp.org.fulfilments.show.catalogue.rentals.index', [$this->organisation->slug, $this->fulfilment->slug]));

    expect(RentalsTabsEnum::RENTALS->value)->toBe('rentals');

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/Rentals')
            ->has('title')
            ->has('tabs')
            ->has('breadcrumbs', 4);
    });
});

test('UI Index fulfilment services', function () {
    $response = $this->get(route('grp.org.fulfilments.show.catalogue.services.index', [$this->organisation->slug, $this->fulfilment->slug]));

    expect(ServicesTabsEnum::SERVICES->value)->toBe('services');

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/Services')
            ->has('title')
            ->has('tabs')
            ->has('breadcrumbs', 4);
    });
});

// Fulfilment Customer

test('UI create fulfilment customer', function () {
    $response = get(route('grp.org.fulfilments.show.crm.customers.create', [$this->organisation->slug, $this->fulfilment->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->has('title')->has('formData')->has('pageHead')->has('breadcrumbs', 4);
    });
});

test('UI show fulfilment customer', function () {
    $response = get(route('grp.org.fulfilments.show.crm.customers.show', [$this->organisation->slug, $this->fulfilment->slug, $this->customer->fulfilmentCustomer->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/FulfilmentCustomer')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', $this->customer->name)
                    ->etc()
            )
            ->has('tabs');
    });
});

test('UI show fulfilment customer web users', function () {
    $response = get(route('grp.org.fulfilments.show.crm.customers.show.web-users.index', [$this->organisation->slug, $this->fulfilment->slug, $this->customer->fulfilmentCustomer->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Shop/CRM/WebUsers')
            ->has('title')
            ->has('breadcrumbs', 4)
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', $this->customer->name)
                    ->etc()
            )
            ->has('tabs');
    });
});

test('UI show fulfilment customer web users (tab requests)', function () {
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.fulfilments.show.crm.customers.show.web-users.index', [
        $this->organisation->slug,
        $this->fulfilment->slug,
        $this->customer->fulfilmentCustomer->slug,
        'tab' => 'requests'
    ]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Shop/CRM/WebUsers')
            ->has('title')
            ->has('breadcrumbs', 4)
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', $this->customer->name)
                    ->etc()
            )
            ->has('tabs')
            ->has('requests');
    });
});

test('UI show fulfilment customer (agreed prices tab)', function () {
    $response = get('http://app.aiku.test/org/'.$this->organisation->slug.'/fulfilments/'.$this->fulfilment->slug.'/customers/'.$this->customer->fulfilmentCustomer->slug.'?tab=agreed_prices');
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/FulfilmentCustomer')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->etc()
            );
    });
});

test('UI show fulfilment customer (tab balance)', function () {
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.fulfilments.show.crm.customers.show', [
        $this->organisation->slug,
        $this->fulfilment->slug,
        $this->customer->fulfilmentCustomer->slug,
        'tab' => 'balance'
    ]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/FulfilmentCustomer')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', $this->customer->name)
                    ->etc()
            )
            ->has('tabs')
            ->has('balance');
    });
});


test('UI edit fulfilment customer', function () {
    $response = get(route('grp.org.fulfilments.show.crm.customers.show.edit', [$this->organisation->slug, $this->fulfilment->slug, $this->customer->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('formData.blueprint.0.fields', 6)
            ->has('pageHead')
            ->has(
                'formData.args.updateRoute',
                fn(AssertableInertia $page) => $page
                    ->where('name', 'grp.models.fulfilment-customer.update')
                    ->where('parameters', [$this->customer->fulfilmentCustomer->id])
            )
            ->has('breadcrumbs', 3);
    });
});

test('UI index fulfilment invoices all', function () {
    $fulfilment = $this->shop->fulfilment;
    $response   = get(
        route(
            'grp.org.fulfilments.show.operations.invoices.all.index',
            [
                $this->organisation->slug,
                $fulfilment->slug
            ]
        )
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Accounting/Invoices')
            ->has('data')
            ->has('pageHead')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', 'Invoices')
                    ->has('subNavigation')
                    ->etc()
            );
    });
});

test('UI show invoice (in Operation)', function () {
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.fulfilments.show.operations.invoices.show', [$this->organisation->slug, $this->fulfilment->slug, $this->invoice->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Accounting/Invoice')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', $this->invoice->reference)
                    ->etc()
            )
            ->has('box_stats')
            ->has('order_summary')
            ->has('invoiceExportOptions')
            ->has('tabs');
    });
});

test('UI show invoice (in Fulfilment Customer)', function () {
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.fulfilments.show.crm.customers.show.invoices.show', [$this->organisation->slug, $this->fulfilment->slug, $this->customer->fulfilmentCustomer->slug, $this->invoice->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Accounting/Invoice')
            ->has('title')
            ->has('breadcrumbs', 4)
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', $this->invoice->reference)
                    ->etc()
            )
            ->has('box_stats')
            ->has('order_summary')
            ->has('invoiceExportOptions')
            ->has('tabs');
    });
});

test('UI index fulfilment invoices unpaid', function () {
    $fulfilment = $this->shop->fulfilment;
    $response   = get(
        route(
            'grp.org.fulfilments.show.operations.invoices.unpaid_invoices.index',
            [
                $this->organisation->slug,
                $fulfilment->slug
            ]
        )
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Accounting/Invoices')
            ->has('data')
            ->has('pageHead')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', 'Invoices')
                    ->has('subNavigation')
                    ->etc()
            );
    });
});

test('UI index fulfilment invoices paid', function () {
    $fulfilment = $this->shop->fulfilment;
    $response   = get(
        route(
            'grp.org.fulfilments.show.operations.invoices.paid_invoices.index',
            [
                $this->organisation->slug,
                $fulfilment->slug
            ]
        )
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Accounting/Invoices')
            ->has('data')
            ->has('pageHead')
            ->has('breadcrumbs')
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', 'Invoices')
                    ->has('subNavigation')
                    ->etc()
            );
    });
});

test('UI show fulfilment customer space sub navigation', function () {
    $response = get(route('grp.org.fulfilments.show.crm.customers.show.spaces.index', [$this->organisation->slug, $this->fulfilment->slug, $this->customer->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/Spaces')
            ->has('title')
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', $this->customer->name)
                    ->has('subNavigation')
                    ->etc()
            )
            ->has('data')
            ->has('breadcrumbs', 4);
    });
});

test('UI show fulfilment customer create space', function () {
    $response = get(route('grp.org.fulfilments.show.crm.customers.show.spaces.create', [$this->organisation->slug, $this->fulfilment->slug, $this->customer->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->has('title')
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', 'new space')
                    ->etc()
            )
            ->has('formData')
            ->has('breadcrumbs', 4);
    });
});

test('UI show fulfilment customer edit space', function () {
    $response = get(route('grp.org.fulfilments.show.crm.customers.show.spaces.edit', [$this->organisation->slug, $this->fulfilment->slug, $this->customer->slug, $this->space->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', 'edit space')
                    ->etc()
            )
            ->has('formData')
            ->has('breadcrumbs', 5);
    });
});

test('UI show fulfilment customer space', function () {
    $response = get(route('grp.org.fulfilments.show.crm.customers.show.spaces.show', [$this->organisation->slug, $this->fulfilment->slug, $this->customer->slug, $this->space->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/Space')
            ->has('title')
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', $this->space->reference)
                    ->etc()
            )
            ->has('tabs')
            ->has('showcase')
            ->has('breadcrumbs', 4);
    });
});

test('UI show fulfilment customer edit invoice', function () {
    $response = get(route('grp.org.fulfilments.show.crm.customers.show.invoices.edit', [$this->organisation->slug, $this->fulfilment->slug, $this->customer->slug, $this->invoice->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', 'Edit invoice')
                    ->etc()
            )
            ->has('formData')
            ->has('breadcrumbs', 4);
    });
});

test('UI show fulfilment customer refund', function () {
    $response = get(route('grp.org.fulfilments.show.crm.customers.show.invoices.show.refunds.show', [
        $this->organisation->slug,
        $this->fulfilment->slug,
        $this->customer->slug,
        $this->invoice->slug,
        $this->refund->slug
    ]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Accounting/Refund')
            ->has('title')
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', $this->refund->reference)
                    ->etc()
            )
            ->has('tabs')
            ->has('order_summary')
            ->has('box_stats')
            ->has('invoice_refund')
            ->has('breadcrumbs', 5);
        if (!$this->refund->in_process) {
            $page->has('exportPdfRoute');
        } else {
            $page->missing('exportPdfRoute');
        }
    });
});

test('UI show fulfilment customer stored item', function () {
    $response = get(route('grp.org.fulfilments.show.crm.customers.show.stored-items.create', [$this->organisation->slug, $this->fulfilment->slug, $this->customer->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->has('title')
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', 'new SKU')
                    ->etc()
            )
            ->has('formData')
            ->has('breadcrumbs', 5);
    });
});

test('UI index refund', function () {
    $response = get(route('grp.org.fulfilments.show.operations.invoices.refunds.index', [$this->organisation->slug, $this->fulfilment->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Accounting/Refunds')
            ->has('title')
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', 'Refunds')
                    ->has('subNavigation')
                    ->etc()
            )
            ->has('breadcrumbs', 3);
    });
});

test('UI show fulfilment customer pallet sub navigation', function () {
    $response = get(route('grp.org.fulfilments.show.crm.customers.show.pallets.index', [$this->organisation->slug, $this->fulfilment->slug, $this->customer->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/PalletsInCustomer')
            ->has('title')
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', $this->customer->name)
                    ->has('subNavigation')
                    ->etc()
            )
            ->has('tabs')
            ->has('breadcrumbs', 4);
    });
});

test('UI show fulfilment customer delivery sub navigation', function () {
    $response = get(route('grp.org.fulfilments.show.crm.customers.show.pallet_deliveries.index', [$this->organisation->slug, $this->fulfilment->slug, $this->customer->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/PalletDeliveries')
            ->has('title')
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', $this->customer->name)
                    ->has('subNavigation')
                    ->etc()
            )
            ->has('tabs')
            ->has('breadcrumbs', 4);
    });
});

test('UI show fulfilment customer pallet return sub navigation', function () {
    $response = get(route('grp.org.fulfilments.show.crm.customers.show.pallet_returns.index', [$this->organisation->slug, $this->fulfilment->slug, $this->customer->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/PalletReturns')
            ->has('title')
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', $this->customer->name)
                    ->has('subNavigation')
                    ->etc()
            )
            ->has('tabs')
            ->has('breadcrumbs', 4);
    });
});

test('UI show fulfilment customer recurring bills sub navigation', function () {
    $response = get(route('grp.org.fulfilments.show.crm.customers.show.recurring_bills.index', [$this->organisation->slug, $this->fulfilment->slug, $this->customer->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/RecurringBills')
            ->has('title')
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', $this->customer->name)
                    ->has('subNavigation')
                    ->etc()
            )
            ->has('data')
            ->has('breadcrumbs', 4);
    });
});

test('UI show fulfilment customer invoice sub navigation', function () {
    $response = get(route('grp.org.fulfilments.show.crm.customers.show.invoices.index', [$this->organisation->slug, $this->fulfilment->slug, $this->customer->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Accounting/Invoices')
            ->has('title')
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', $this->customer->name)
                    ->has('subNavigation')
                    ->etc()
            )
            ->has('breadcrumbs', 4);
        if (!app()->environment('production')) {
            $page->has('tabs');
        } else {
            $page->has('data');
        }
    });
});

test('UI show fulfilment customer invoice sub navigation (tab in process)', function () {
    $response = get(route('grp.org.fulfilments.show.crm.customers.show.invoices.index', [
        $this->organisation->slug,
        $this->fulfilment->slug,
        $this->customer->slug,
        'tab' => InvoicesInFulfilmentCustomerTabsEnum::IN_PROCESS->value
    ]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Accounting/Invoices')
            ->has('title')
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', $this->customer->name)
                    ->has('subNavigation')
                    ->etc()
            )
            ->has('breadcrumbs', 4);
        if (!app()->environment('production')) {
            $page->has(InvoicesInFulfilmentCustomerTabsEnum::IN_PROCESS->value);
            $page->has('tabs');
        } else {
            $page->has('data');
        }
    });
});

test('UI show standalone invoice fulfilment customer', function () {
    $standaloneInvoice = StoreStandaloneFulfilmentInvoice::make()->action($this->customer->fulfilmentCustomer, []);

    $response = get(route('grp.org.fulfilments.show.crm.customers.show.invoices.in-process.show', [
        $this->organisation->slug,
        $this->fulfilment->slug,
        $this->customer->slug,
        $standaloneInvoice->slug
    ]));

    $response->assertInertia(function (AssertableInertia $page) use ($standaloneInvoice) {
        $page
            ->component('Org/Accounting/InvoiceManual')
            ->has('title')
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', $standaloneInvoice->reference)
                    ->has('subNavigation')
                    ->etc()
            )
            ->has('tabs')
            ->has('order_summary')
            ->has('exportPdfRoute')
            ->has('box_stats')
            ->has('invoice')
            ->has('breadcrumbs', 4);
    });
});


test('UI show recurring bills in fulfilment customer', function () {
    $response = get(route('grp.org.fulfilments.show.crm.customers.show.recurring_bills.show', [
        $this->organisation->slug,
        $this->fulfilment->slug,
        $this->customer->slug,
        $this->recurringBill->slug
    ]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/RecurringBill')
            ->has('title')
            ->has('navigation')
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', $this->recurringBill->slug)
                    ->has('subNavigation')
                    ->etc()
            )
            ->has('breadcrumbs', 4);
    });
});

// Pallets

test('UI Index pallets', function () {
    $response = $this->get(route('grp.org.fulfilments.show.operations.pallets.current.index', [$this->organisation->slug, $this->fulfilment->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/Pallets')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', 'Pallets')
                    ->has('subNavigation')
                    ->etc()
            )
            ->has('data');
    });
});

test('UI Index damaged pallets', function () {
    $response = $this->get(route('grp.org.fulfilments.show.operations.pallets.damaged.index', [$this->organisation->slug, $this->fulfilment->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/Pallets')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', 'Damaged pallets')
                    ->has('subNavigation')
                    ->etc()
            )
            ->has('data');
    });
});

test('UI Index lost pallets', function () {
    $response = $this->get(route('grp.org.fulfilments.show.operations.pallets.lost.index', [$this->organisation->slug, $this->fulfilment->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/Pallets')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', 'Lost pallets')
                    ->has('subNavigation')
                    ->etc()
            )
            ->has('data');
    });
});

test('UI Index returned pallets', function () {
    $response = $this->get(route('grp.org.fulfilments.show.operations.pallets.returned.index', [$this->organisation->slug, $this->fulfilment->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/Pallets')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', 'Returned pallets')
                    ->has('subNavigation')
                    ->etc()
            )
            ->has('data');
    });
});

test('UI show pallet', function () {
    $response = get(route('grp.org.fulfilments.show.operations.pallets.current.show', [$this->organisation->slug, $this->fulfilment->slug, $this->pallet->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/Pallet')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', $this->pallet->reference)
                    ->etc()
            )
            ->has('tabs');
    });
});

test('UI show pallet in fulfilment customer', function () {
    $response = get(route('grp.org.fulfilments.show.crm.customers.show.pallets.show', [
        $this->organisation->slug,
        $this->fulfilment->slug,
        $this->customer->fulfilmentCustomer->slug,
        $this->pallet->slug
    ]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/Pallet')
            ->has('title')
            ->has('breadcrumbs', 4)
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', $this->pallet->reference)
                    ->etc()
            )
            ->has('tabs');
    });
});

test('UI show pallet (Stored Items Tab)', function () {
    $response = get('http://app.aiku.test/org/'.$this->organisation->slug.'/fulfilments/'.$this->fulfilment->slug.'/pallets/'.$this->pallet->slug.'?tab=stored_items');
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/Pallet')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', $this->pallet->reference)
                    ->etc()
            )
            ->has('tabs');
    });
});

test('UI edit pallet', function () {
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.fulfilments.show.operations.pallets.current.edit', [$this->organisation->slug, $this->fulfilment->slug, $this->pallet->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('formData.blueprint.0.fields', 3)
            ->has('pageHead')
            ->has(
                'formData.args.updateRoute',
                fn(AssertableInertia $page) => $page
                    ->where('name', 'grp.models.pallet.update')
                    ->where('parameters', [$this->pallet->id])
            )
            ->has('breadcrumbs', 3);
    });
});

test('UI Index damaged pallets in warehouse', function () {
    $response = $this->get(route('grp.org.warehouses.show.inventory.pallets.damaged.index', [$this->organisation->slug, $this->warehouse->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/Pallets')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', 'Damaged pallets')
                    ->has('subNavigation')
                    ->etc()
            )
            ->has('data');
    });
})->todo();

test('UI Index returned pallets in warehouse', function () {
    $response = $this->get(route('grp.org.warehouses.show.inventory.pallets.returned.index', [$this->organisation->slug, $this->warehouse->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/Pallets')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', 'Returned pallets')
                    ->has('subNavigation')
                    ->etc()
            )
            ->has('data');
    });
})->todo();

test('UI Index pallets in warehouse', function () {
    $response = $this->get(route('grp.org.warehouses.show.inventory.pallets.current.index', [$this->organisation->slug, $this->warehouse->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Warehouse/Fulfilment/Pallets')
            ->has('title')
            ->has('breadcrumbs', 4)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', 'Pallets in warehouse')
                    ->has('subNavigation')
                    ->etc()
            )
            ->has('data');
    });
});

test('UI Index lost pallets in warehouse', function () {
    $response = $this->get(route('grp.org.warehouses.show.inventory.pallets.lost.index', [$this->organisation->slug, $this->warehouse->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/Pallets')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', 'Lost pallets')
                    ->has('subNavigation')
                    ->etc()
            )
            ->has('data');
    });
})->todo();

// Pallet Delivery

test('UI Index pallet deliveries', function () {
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.org.fulfilments.show.operations.pallet-deliveries.index', [$this->organisation->slug, $this->fulfilment->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/PalletDeliveries')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', 'fulfilment deliveries')
                    ->has('subNavigation')
                    ->has('actions')
                    ->etc()
            )
            ->has('data');
    });
});

test('UI show pallet delivery', function () {
    // $this->withoutExceptionHandling();
    $response = get(route('grp.org.fulfilments.show.operations.pallet-deliveries.show', [$this->organisation->slug, $this->fulfilment->slug, $this->palletDelivery->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/PalletDelivery')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', $this->palletDelivery->reference)
                    ->etc()
            )
            ->has('tabs');
    });
});

test('UI edit pallet delivery', function () {
    // $this->withoutExceptionHandling();
    $response = get(route('grp.org.fulfilments.show.operations.pallet-deliveries.edit', [$this->organisation->slug, $this->fulfilment->slug, $this->palletDelivery->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', 'Edit Pallet Delivery')
                    ->etc()
            )
            ->has('formData');
    });
});

test('UI show pallet delivery (Services Tab)', function () {
    $response = get('http://app.aiku.test/org/'.$this->organisation->slug.'/fulfilments/'.$this->fulfilment->slug.'/deliveries/'.$this->palletDelivery->slug.'?tab=services');
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/PalletDelivery')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', $this->palletDelivery->reference)
                    ->etc()
            )
            ->has('tabs');
    });
});

test('UI show pallet delivery (Physical goods Tab)', function () {
    $response = get('http://app.aiku.test/org/'.$this->organisation->slug.'/fulfilments/'.$this->fulfilment->slug.'/deliveries/'.$this->palletDelivery->slug.'?tab=physical_goods');
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/PalletDelivery')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', $this->palletDelivery->reference)
                    ->etc()
            )
            ->has('tabs');
    });
});


test('UI show pallet delivery (confirmed)', function () {
    $palletDelivery = $this->palletDelivery;

    StorePalletCreatedInPalletDelivery::make()->action(
        $palletDelivery,
        [
            'type'               => PalletTypeEnum::PALLET,
            'customer_reference' => 'A001BB'
        ]
    );

    $palletDelivery = SubmitAndConfirmPalletDelivery::make()->action($palletDelivery);
    $palletDelivery->refresh();

    $response = get(route('grp.org.fulfilments.show.operations.pallet-deliveries.show', [$this->organisation->slug, $this->fulfilment->slug, $palletDelivery->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/PalletDelivery')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', $this->palletDelivery->reference)
                    ->etc()
            )
            ->has('box_stats')
            ->has('data')
            ->has('tabs');
    });

    return $palletDelivery;
});

test('UI show pallet delivery (received)', function (PalletDelivery $palletDelivery) {
    StoreRental::make()->action(
        $palletDelivery->fulfilment->shop,
        [
            'price'                  => 100,
            'unit'                   => RentalUnitEnum::WEEK->value,
            'code'                   => 'R00002',
            'name'                   => 'Rental Asset B',
            'auto_assign_asset'      => 'Pallet',
            'auto_assign_asset_type' => PalletTypeEnum::PALLET->value
        ]
    );

    $palletDelivery = ReceivePalletDelivery::make()->action($palletDelivery);
    $palletDelivery->refresh();

    $response = get(route('grp.org.fulfilments.show.operations.pallet-deliveries.show', [$this->organisation->slug, $this->fulfilment->slug, $palletDelivery->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/PalletDelivery')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', $this->palletDelivery->reference)
                    ->etc()
            )
            ->has('box_stats')
            ->has('data')
            ->has('tabs');
    });

    return $palletDelivery;
})->depends('UI show pallet delivery (confirmed)');

test('UI show pallet delivery (booking in)', function (PalletDelivery $palletDelivery) {
    $palletDelivery = StartBookingPalletDelivery::make()->action($palletDelivery);
    $palletDelivery->refresh();
    $location = $this->warehouse->locations()->first();

    foreach ($palletDelivery->pallets as $pallet) {
        BookInPallet::make()->action($pallet, ['location_id' => $location->id]);
    }

    $response = get(route('grp.org.fulfilments.show.operations.pallet-deliveries.show', [$this->organisation->slug, $this->fulfilment->slug, $palletDelivery->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/PalletDelivery')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', $this->palletDelivery->reference)
                    ->etc()
            )
            ->has('box_stats')
            ->has('data')
            ->has('tabs');
    });

    return $palletDelivery;
})->depends('UI show pallet delivery (received)');

test('UI show pallet delivery (booked in)', function (PalletDelivery $palletDelivery) {
    $palletDelivery = SetPalletDeliveryAsBookedIn::make()->action($palletDelivery);
    $palletDelivery->refresh();

    $response = get(route('grp.org.fulfilments.show.operations.pallet-deliveries.show', [$this->organisation->slug, $this->fulfilment->slug, $palletDelivery->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/PalletDelivery')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', $this->palletDelivery->reference)
                    ->etc()
            )
            ->has('box_stats')
            ->has('data')
            ->has('tabs');
    });

    return $palletDelivery;
})->depends('UI show pallet delivery (booking in)');

// Pallet Return

test('UI Index pallet returns', function () {
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.org.fulfilments.show.operations.pallet-returns.index', [$this->organisation->slug, $this->fulfilment->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/PalletReturns')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', 'returns')
                    ->has('subNavigation')
                    ->has('actions')
                    ->etc()
            )
            ->has('data');
    });
});

test('UI show pallet return', function () {
    $response = get(route('grp.org.fulfilments.show.operations.pallet-returns.show', [$this->organisation->slug, $this->fulfilment->slug, $this->palletReturn->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/PalletReturn')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', $this->palletReturn->reference)
                    ->etc()
            )
            ->has('tabs');
    });
});

test('UI json get pallet return whole pallet', function () {
    $this->withoutExceptionHandling();
    $response = getJson(route('grp.json.pallet-return.pallets.index', [
        $this->palletReturn->slug
    ]));
    $response->assertStatus(200)
        ->assertJsonStructure([
            'data',
        ]);
});

test('UI show pallet return (physical goods tab)', function () {
    $response = get('http://app.aiku.test/org/'.$this->organisation->slug.'/fulfilments/'.$this->fulfilment->slug.'/returns/'.$this->palletReturn->slug.'?tab=physical_goods');
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/PalletReturn')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', $this->palletReturn->reference)
                    ->etc()
            )
            ->has('tabs');
    });
});

test('UI show pallet return (services tab)', function () {
    $response = get('http://app.aiku.test/org/'.$this->organisation->slug.'/fulfilments/'.$this->fulfilment->slug.'/returns/'.$this->palletReturn->slug.'?tab=services');
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/PalletReturn')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', $this->palletReturn->reference)
                    ->etc()
            )
            ->has('tabs');
    });
});

test('UI show pallet return (confirmed)', function () {
    $palletReturn       = $this->palletReturn;
    $fulfilmentCustomer = $this->customer->fulfilmentCustomer;
    $pallet             = $fulfilmentCustomer->pallets()->where('status', PalletStatusEnum::STORING)->first();
    AttachPalletToReturn::make()->action(
        $palletReturn,
        $pallet,
    );

    $palletReturn = SubmitAndConfirmPalletReturn::make()->action($palletReturn);
    $palletReturn->refresh();
    $response = get(route('grp.org.fulfilments.show.operations.pallet-returns.show', [$this->organisation->slug, $this->fulfilment->slug, $this->palletReturn->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/PalletReturn')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', $this->palletReturn->reference)
                    ->etc()
            )
            ->has('tabs');
    });

    return $palletReturn;
});

test('UI show pallet return (picking)', function (PalletReturn $palletReturn) {
    $palletReturn = PickingPalletReturn::make()->action($palletReturn);
    $palletReturn->refresh();

    $response = get(route('grp.org.fulfilments.show.operations.pallet-returns.show', [$this->organisation->slug, $this->fulfilment->slug, $this->palletReturn->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/PalletReturn')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', $this->palletReturn->reference)
                    ->etc()
            )
            ->has('tabs');
    });

    return $palletReturn;
})->depends('UI show pallet return (confirmed)');

test('UI show pallet return (picked)', function (PalletReturn $palletReturn) {
    $palletReturn = PickedPalletReturn::make()->action($palletReturn);
    $palletReturn->refresh();

    $response = get(route('grp.org.fulfilments.show.operations.pallet-returns.show', [$this->organisation->slug, $this->fulfilment->slug, $this->palletReturn->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/PalletReturn')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', $this->palletReturn->reference)
                    ->etc()
            )
            ->has('tabs');
    });

    return $palletReturn;
})->depends('UI show pallet return (picking)');

test('UI show pallet return (dispatched)', function (PalletReturn $palletReturn) {
    $palletReturn = DispatchPalletReturn::make()->action($palletReturn);
    $palletReturn->refresh();

    $response = get(route('grp.org.fulfilments.show.operations.pallet-returns.show', [$this->organisation->slug, $this->fulfilment->slug, $this->palletReturn->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/PalletReturn')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', $this->palletReturn->reference)
                    ->etc()
            )
            ->has('tabs');
    });

    return $palletReturn;
})->depends('UI show pallet return (picked)');


test('UI show pallet return with stored items', function () {
    // dd($this->customer->fulfilmentCustomer->pallets);
    $response = get(route('grp.org.fulfilments.show.operations.pallet-return-with-stored-items.show', [
        $this->organisation->slug,
        $this->fulfilment->slug,
        $this->storedItemReturn->slug
    ]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/PalletReturn')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has('interest')
            ->has('updateRoute')
            ->has('deleteServiceRoute')
            ->has('deletePhysicalGoodRoute')
            ->has('routeStorePallet')
            ->has('attachmentRoutes')
            ->has('data')
            ->has('box_stats')
            ->has('notes_data')
            ->has('stored_items_count')
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', $this->palletReturn->reference)
                    ->etc()
            )
            ->has('tabs');
    });
});

// Rental

test('UI show rental', function () {
    $response = get(route('grp.org.fulfilments.show.catalogue.rentals.show', [$this->organisation->slug, $this->fulfilment->slug, $this->rental->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/Rental')
            ->has('title')
            ->has('breadcrumbs', 4)
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', $this->rental->code)
                    ->etc()
            )
            ->has('tabs');
    });
});

test('UI edit rental', function () {
    $response = get(route('grp.org.fulfilments.show.catalogue.rentals.edit', [$this->organisation->slug, $this->fulfilment->slug, $this->rental->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('formData.blueprint.0.fields', 6)
            ->has('pageHead')
            ->has(
                'formData.args.updateRoute',
                fn(AssertableInertia $page) => $page
                    ->where('name', 'grp.models.rentals.update')
                    ->where('parameters', [
                        'rental' => $this->rental->id
                    ])
            )
            ->has('breadcrumbs', 4);
    });
});

test('UI create rental', function () {
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.fulfilments.show.catalogue.rentals.create', [$this->organisation->slug, $this->fulfilment->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->has('title')->has('formData')->has('pageHead')->has('breadcrumbs', 5);
    });
});

// Rental Agreement

test('UI create rental agreement', function () {
    $response = get(route('grp.org.fulfilments.show.crm.customers.show.rental-agreement.create', [$this->organisation->slug, $this->fulfilment->slug, $this->customer->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->has('title')->has('formData')->has('pageHead')->has('breadcrumbs', 4);
    });
});

test('UI edit rental agreement', function () {
    $this->withoutExceptionHandling();
    $fulfilmentCustomer = FulfilmentCustomer::find($this->rentalAgreement->fulfilment_customer_id);
    // dd($fulfilmentCustomer->fulfilment);
    $response = get(route('grp.org.fulfilments.show.crm.customers.show.rental-agreement.edit', [
        $fulfilmentCustomer->organisation->slug,
        $fulfilmentCustomer->fulfilment->slug,
        $fulfilmentCustomer->slug
    ]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('formData.blueprint.0.fields', 3)
            ->has('pageHead')
            ->has(
                'formData.args.updateRoute',
                fn(AssertableInertia $page) => $page
                    ->where('name', 'grp.models.rental-agreement.update')
                    ->where('parameters', ['rentalAgreement' => $this->rentalAgreement->id])
            )
            ->has('breadcrumbs', 4);
    });
});

// Billables

test('UI billables dashboard', function () {
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.shops.show.billables.dashboard', [$this->organisation->slug, $this->fulfilment->shop->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Billables/BillablesDashboard')
            ->has('title')
            ->has('breadcrumbs', 4)
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', 'billables dashboard')
                    ->etc()
            )
            ->has('tabs');
    });
})->todo();//permissions issue


// Service

test('UI show service', function () {
    $response = get(route('grp.org.fulfilments.show.catalogue.services.show', [$this->organisation->slug, $this->fulfilment->slug, $this->service->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/Service')
            ->has('title')
            ->has('breadcrumbs', 4)
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', $this->service->code)
                    ->etc()
            )
            ->has('tabs');
    });
});

test('UI edit service', function () {
    $response = get(route('grp.org.fulfilments.show.catalogue.services.edit', [$this->organisation->slug, $this->fulfilment->slug, $this->service->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('formData.blueprint.0.fields', 8)
            ->has('pageHead')
            ->has(
                'formData.args.updateRoute',
                fn(AssertableInertia $page) => $page
                    ->where('name', 'grp.models.services.update')
                    ->where('parameters', [
                        'service' => $this->service->id
                    ])
            )
            ->has('breadcrumbs', 4);
    });
});

test('UI create service', function () {
    $response = get(route('grp.org.fulfilments.show.catalogue.services.create', [$this->organisation->slug, $this->fulfilment->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->has('title')->has('formData')->has('pageHead')->has('breadcrumbs', 5);
    });
});

// Physical Goods

test('UI show physical goods', function () {
    $response = get(route('grp.org.fulfilments.show.catalogue.physical_goods.show', [$this->organisation->slug, $this->fulfilment->slug, $this->product->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/PhysicalGood')
            ->has('title')
            ->has('breadcrumbs', 4)
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', $this->product->code)
                    ->etc()
            )
            ->has('tabs');
    });
});

test('UI edit physical goods', function () {
    $response = get(route('grp.org.fulfilments.show.catalogue.physical_goods.edit', [$this->organisation->slug, $this->fulfilment->slug, $this->product->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('formData.blueprint.0.fields', 7)
            ->has('pageHead')
            ->has(
                'formData.args.updateRoute',
                fn(AssertableInertia $page) => $page
                    ->where('name', 'grp.models.product.update')
                    ->where('parameters', $this->product->id) //wrong route
            )
            ->has('breadcrumbs', 4);
    });
});

test('UI create physical goods', function () {
    $response = get(route('grp.org.fulfilments.show.catalogue.physical_goods.create', [$this->organisation->slug, $this->fulfilment->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->has('title')->has('formData')->has('pageHead')->has('breadcrumbs', 5);
    });
});


test('UI Index stored items', function () {
    $response = $this->get(route('grp.org.fulfilments.show.crm.customers.show.stored-items.index', [$this->organisation->slug, $this->fulfilment->slug, $this->customer->fulfilmentCustomer->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/StoredItems')
            ->has('title')
            ->has('breadcrumbs', 4)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->has('subNavigation')
                    ->etc()
            );
    });
});

test('UI show stored item', function () {
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.fulfilments.show.crm.customers.show.stored-items.show', [$this->organisation->slug, $this->fulfilment->slug, $this->customer->fulfilmentCustomer->slug, $this->storedItem->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/StoredItem')
            ->has('title')
            ->has('breadcrumbs', 4)
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', $this->storedItem->slug)
                    ->etc()
            )
            ->has('tabs');
    });
});

test('UI edit stored item', function () {
    $response = get(route('grp.org.fulfilments.show.crm.customers.show.stored-items.edit', [$this->organisation->slug, $this->fulfilment->slug, $this->customer->fulfilmentCustomer->slug, $this->storedItem->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('formData.blueprint.0.fields', 2)
            ->has('pageHead')
            ->has(
                'formData.args.updateRoute',
                fn(AssertableInertia $page) => $page
                    ->where('name', 'grp.models.stored-items.update')
                    ->where('parameters', $this->storedItem->id) //wrong route
            )
            ->has('breadcrumbs', 4);
    });
});

test('UI Index Recurring Bills', function () {
    $response = get(route('grp.org.fulfilments.show.operations.recurring_bills.index', [$this->organisation->slug, $this->fulfilment->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/RecurringBills')
            ->has('title')
            ->has('pageHead')
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', 'recurring bills')
                    ->has('subNavigation')
                    ->etc()
            );
    });
});

test('UI show Recurring Bill', function () {
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.fulfilments.show.operations.recurring_bills.show', [$this->organisation->slug, $this->fulfilment->slug, $this->recurringBill->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/RecurringBill')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', $this->recurringBill->slug)
                    ->etc()
            )
            ->has('timeline_rb')
            ->has('consolidateRoute')
            ->has('status_rb')
            ->has('box_stats')
            ->has('tabs');
    });
});

test('UI show Recurring Bill in operation (current)', function () {
    $this->withoutExceptionHandling();
    $recurringBill = RecurringBill::where("status", RecurringBillStatusEnum::CURRENT)->first();
    $response      = get(route('grp.org.fulfilments.show.operations.recurring_bills.current.show', [$this->organisation->slug, $this->fulfilment->slug, $recurringBill->slug]));
    $response->assertInertia(function (AssertableInertia $page) use ($recurringBill) {
        $page
            ->component('Org/Fulfilment/RecurringBill')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', $recurringBill->slug)
                    ->etc()
            )
            ->has('timeline_rb')
            ->has('consolidateRoute')
            ->has('status_rb')
            ->has('box_stats')
            ->has('tabs');
    });
});

test('UI show Recurring Bill in operation (former)', function () {
    $this->withoutExceptionHandling();
    $recurringBill = RecurringBill::where("status", RecurringBillStatusEnum::FORMER)->first();
    $response      = get(route('grp.org.fulfilments.show.operations.recurring_bills.former.show', [$this->organisation->slug, $this->fulfilment->slug, $recurringBill->slug]));
    $response->assertInertia(function (AssertableInertia $page) use ($recurringBill) {
        $page
            ->component('Org/Fulfilment/RecurringBill')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', $recurringBill->slug)
                    ->etc()
            )
            ->has('timeline_rb')
            ->has('consolidateRoute')
            ->has('status_rb')
            ->has('box_stats')
            ->has('tabs');
    });
});


test('UI get section route fulfilment catalogue index', function () {
    $sectionScope = GetSectionRoute::make()->handle('grp.org.fulfilments.show.catalogue.index', [
        'organisation' => $this->organisation->slug,
        'fulfilment'   => $this->fulfilment->slug
    ]);

    expect($sectionScope)->toBeInstanceOf(AikuScopedSection::class)
        ->and($sectionScope->code)->toBe(AikuSectionEnum::FULFILMENT_CATALOGUE->value)
        ->and($sectionScope->model_slug)->toBe($this->fulfilment->slug);
});

test('UI get section route fulfilment operations dashboard', function () {
    $sectionScope = GetSectionRoute::make()->handle('grp.org.fulfilments.show.operations.dashboard', [
        'organisation' => $this->organisation->slug,
        'fulfilment'   => $this->fulfilment->slug
    ]);

    expect($sectionScope)->toBeInstanceOf(AikuScopedSection::class)
        ->and($sectionScope->code)->toBe(AikuSectionEnum::FULFILMENT_OPERATION->value)
        ->and($sectionScope->model_slug)->toBe($this->fulfilment->slug);
});

test('UI get section route fulfilment web index', function () {
    $sectionScope = GetSectionRoute::make()->handle('grp.org.fulfilments.show.web.websites.index', [
        'organisation' => $this->organisation->slug,
        'fulfilment'   => $this->fulfilment->slug
    ]);

    expect($sectionScope)->toBeInstanceOf(AikuScopedSection::class)
        ->and($sectionScope->code)->toBe(AikuSectionEnum::FULFILMENT_WEBSITE->value)
        ->and($sectionScope->model_slug)->toBe($this->fulfilment->slug);
});

test('UI get section route fulfilment crm index', function () {
    $sectionScope = GetSectionRoute::make()->handle('grp.org.fulfilments.show.crm.customers.index', [
        'organisation' => $this->organisation->slug,
        'fulfilment'   => $this->fulfilment->slug
    ]);

    expect($sectionScope)->toBeInstanceOf(AikuScopedSection::class)
        ->and($sectionScope->code)->toBe(AikuSectionEnum::FULFILMENT_CRM->value)
        ->and($sectionScope->model_slug)->toBe($this->fulfilment->slug);
});

test('UI get section route fulfilment settings edit', function () {
    $sectionScope = GetSectionRoute::make()->handle('grp.org.fulfilments.show.settings.edit', [
        'organisation' => $this->organisation->slug,
        'fulfilment'   => $this->fulfilment->slug
    ]);

    expect($sectionScope)->toBeInstanceOf(AikuScopedSection::class)
        ->and($sectionScope->code)->toBe(AikuSectionEnum::FULFILMENT_SETTINGS->value)
        ->and($sectionScope->model_slug)->toBe($this->fulfilment->slug);
});

test('UI get section route org fulfilments index', function () {
    $sectionScope = GetSectionRoute::make()->handle('grp.org.fulfilments.index', [
        'organisation' => $this->organisation->slug,
    ]);

    expect($sectionScope)->toBeInstanceOf(AikuScopedSection::class)
        ->and($sectionScope->code)->toBe(AikuSectionEnum::ORG_FULFILMENT->value)
        ->and($sectionScope->model_slug)->toBe($this->organisation->slug);
});


// ui stored item audit
test('UI show stored item audit', function () {
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.fulfilments.show.crm.customers.show.stored-item-audits.show', [
        $this->organisation->slug,
        $this->fulfilment->slug,
        $this->customer->fulfilmentCustomer->slug,
        $this->storedItemAudit->slug
    ]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/StoredItemAudit')
            ->has('title')
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', "Customer's SKUs audit")
                    ->has('subNavigation')
                    ->etc()
            )
            ->has('breadcrumbs', 4);
    });
});

test('UI create stored item audit for pallet (first time)', function () {
    $rental = Rental::where('auto_assign_asset', 'Pallet')->first();
    $pallet = StorePallet::make()->action($this->customer->fulfilmentCustomer, [
        'customer_reference' => 'audit-pallet',
        'state'              => PalletStateEnum::STORING,
        'status'             => PalletStatusEnum::STORING,
        'type'               => PalletTypeEnum::PALLET,
        'warehouse_id'       => $this->warehouse->id,
        'location_id'        => $this->warehouse->locations()->first()->id,
        'rental_id'          => $rental->id
    ]);

    $this->withoutExceptionHandling();

    $storedItemAudit = StoredItemAudit::where('scope_id', $pallet->id)
        ->where('scope_type', 'Pallet')
        ->where('state', StoredItemAuditStateEnum::IN_PROCESS)
        ->first();

    expect($storedItemAudit)->toBeNull();

    $response = get(route('grp.org.fulfilments.show.crm.customers.show.pallets.stored-item-audits.create', [
        $this->organisation->slug,
        $this->fulfilment->slug,
        $this->customer->fulfilmentCustomer->slug,
        $pallet->slug,
    ]), []);

    $storedItemAudit = StoredItemAudit::where('scope_id', $pallet->id)
        ->where('scope_type', 'Pallet')
        ->where('state', StoredItemAuditStateEnum::IN_PROCESS)
        ->first();

    expect($storedItemAudit)->not()->toBeNull();

    $response->assertRedirect(route('grp.org.fulfilments.show.crm.customers.show.pallets.stored-item-audits.show', [
        $this->organisation->slug,
        $this->fulfilment->slug,
        $this->customer->fulfilmentCustomer->slug,
        $pallet->slug,
        $storedItemAudit->slug,
    ]));

    return $storedItemAudit;
});

test('UI create stored item audit for pallet (already created)', function (StoredItemAudit $storedItemAudit) {
    /** @var Pallet $pallet */
    $pallet = $storedItemAudit->scope;

    $this->withoutExceptionHandling();

    $storedItemAudit = StoredItemAudit::where('scope_id', $pallet->id)
        ->where('scope_type', 'Pallet')
        ->where('state', StoredItemAuditStateEnum::IN_PROCESS)
        ->first();

    expect($storedItemAudit)->not->toBeNull();

    $response = get(route('grp.org.fulfilments.show.crm.customers.show.pallets.stored-item-audits.create', [
        $this->organisation->slug,
        $this->fulfilment->slug,
        $this->customer->fulfilmentCustomer->slug,
        $pallet->slug,
    ]), []);

    $response->assertRedirect(route('grp.org.fulfilments.show.crm.customers.show.pallets.stored-item-audits.show', [
        $this->organisation->slug,
        $this->fulfilment->slug,
        $this->customer->fulfilmentCustomer->slug,
        $pallet->slug,
        $storedItemAudit->slug,
    ]));

    return $storedItemAudit;
})->depends('UI create stored item audit for pallet (first time)');

// ui stored item audit
test('UI show stored item audit for pallet', function () {
    $pallet      = Pallet::where('state', PalletStateEnum::STORING)->where('fulfilment_customer_id', $this->customer->fulfilmentCustomer->id)->first();
    $palletAudit = StoreStoredItemAuditFromPallet::make()->action($pallet, []);

    $this->withoutExceptionHandling();
    $response = get(route('grp.org.fulfilments.show.crm.customers.show.pallets.stored-item-audits.show', [
        $this->organisation->slug,
        $this->fulfilment->slug,
        $this->customer->fulfilmentCustomer->slug,
        $pallet->slug,
        $palletAudit->slug
    ]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Fulfilment/PalletAudit')
            ->has('title')
            ->has(
                'pageHead',
                fn(AssertableInertia $page) => $page
                    ->where('title', "Audit")
                    ->has('subNavigation')
                    ->etc()
            )
            ->has('breadcrumbs', 5);
    });
});


test('UI json index pallets in stored item', function () {
    $this->withoutExceptionHandling();
    $response = getJson(route('grp.org.fulfilments.show.crm.customers.show.pallets.stored-item.index', [
        $this->organisation->slug,
        $this->fulfilment->slug,
        $this->customer->fulfilmentCustomer->slug,
        $this->storedItem->slug
    ]));
    $response->assertStatus(200)
        ->assertJsonStructure([
            'data',
        ]);
});
