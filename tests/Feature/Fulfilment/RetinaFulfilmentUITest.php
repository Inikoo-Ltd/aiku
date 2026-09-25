<?php

/*
 * Author: Arya Permana <aryapermana02@gmail.com>
 * Created: Wed, 19 Jun 2024 09:24:40 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Billables\Rental\StoreRental;
use App\Actions\Fulfilment\PalletDelivery\ConfirmPalletDelivery;
use App\Actions\Fulfilment\PalletDelivery\StorePalletDelivery;
use App\Actions\Fulfilment\PalletReturn\StorePalletReturn;
use App\Actions\Fulfilment\RecurringBill\StoreRecurringBill;
use App\Actions\Fulfilment\RentalAgreement\StoreRentalAgreement;
use App\Actions\Fulfilment\Space\StoreSpace;
use App\Actions\Fulfilment\StoredItem\StoreStoredItem;
use App\Actions\Fulfilment\StoredItemAudit\StoreStoredItemAudit;
use App\Actions\Retina\Fulfilment\Pallet\StoreRetinaPalletFromDelivery;
use App\Actions\Retina\SysAdmin\StoreRetinaWebUser;
use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\CRM\WebUser\StoreWebUser;
use App\Actions\Web\Website\LaunchWebsite;
use App\Actions\Web\Website\UI\DetectWebsiteFromDomain;
use App\Enums\Billables\Rental\RentalTypeEnum;
use App\Enums\Billables\Rental\RentalUnitEnum;
use App\Enums\CRM\Customer\CustomerStatusEnum;
use App\Enums\Fulfilment\Pallet\PalletTypeEnum;
use App\Enums\Fulfilment\PalletDelivery\PalletDeliveryStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Enums\Fulfilment\RentalAgreement\RentalAgreementBillingCycleEnum;
use App\Enums\Fulfilment\RentalAgreement\RentalAgreementStateEnum;
use App\Enums\UI\Fulfilment\PalletDeliveriesTabsEnum;
use App\Enums\UI\Fulfilment\PalletDeliveryTabsEnum;
use App\Enums\UI\Fulfilment\StoredItemTabsEnum;
use App\Enums\Web\Website\WebsiteStateEnum;
use App\Models\Billables\Rental;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Fulfilment\RecurringBill;
use App\Models\Fulfilment\RentalAgreement;
use App\Models\Fulfilment\Space;
use App\Models\Fulfilment\StoredItem;
use App\Models\Fulfilment\StoredItemAudit;
use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Str;
use App\Models\CRM\Customer;
use App\Models\CRM\WebUser;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

uses()->group('ui');

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
    $this->customer = createCustomer($this->fulfilment->shop);
    $this->customer->update(['status' => CustomerStatusEnum::APPROVED]);

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

    $pallet = Pallet::first();
    if (!$pallet) {
        data_set($storeData, 'type', PalletTypeEnum::PALLET);
        data_set($storeData, 'customer_reference', 'ref');
        data_set($storeData, 'notes', 'ref notes');

        $pallet = StoreRetinaPalletFromDelivery::make()->action(
            $this->palletDelivery,
            $storeData
        );
    }

    $this->pallet = $pallet;

    ConfirmPalletDelivery::make()->action($this->palletDelivery);

    $this->pallet->refresh();

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

    $storedItem = StoredItem::first();
    if (!$storedItem) {
        data_set($storeData, 'reference', 'stored-item-ref');

        $storedItem = StoreStoredItem::make()->action(
            $this->customer->fulfilmentCustomer,
            $storeData
        );
    }

    $this->storedItem = $storedItem;

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

    $rental = Rental::first();
    if (!$rental) {
        $rental = StoreRental::make()->action(
            $this->fulfilment->shop,
            [
                'code'  => 'rent-code',
                'name'  => 'rent name',
                'price' => 10,
                'unit'  => RentalUnitEnum::DAY->value,
                'type'  => RentalTypeEnum::SPACE
            ]
        );
    }

    $this->rental = $rental;

    $space = Space::first();
    if (!$space) {
        $space = StoreSpace::make()->action(
            $this->customer->fulfilmentCustomer,
            [
                'reference'       => 'space-ref',
                'exclude_weekend' => true,
                'start_at'        => now(),
                'rental_id'       => $this->rental->id
            ]
        );
    }

    $this->space = $space;

    $this->webUser = createWebUser($this->customer);

    Config::set(
        'inertia.testing.page_paths',
        [resource_path('js/Pages/Retina')]
    );

    DetectWebsiteFromDomain::mock()
        ->shouldReceive('handle')
        ->with('localhost')
        ->andReturn($this->website);
});

test('show log in', function () {
    $this->withoutExceptionHandling();
    $response = $this->get(route('retina.login.show'));
    $response->assertInertia(function (AssertableInertia $page) {
        $page->component('Auth/RetinaLogin');
    });
});

test('show redirect if not logged in', function () {
    $response = $this->get(route('retina.dashboard.show'));
    $response->assertRedirect('app/login');
});

test('show dashboard', function () {
    actingAs($this->webUser, 'retina');
    $response = $this->get(route('retina.dashboard.show'));
    $response->assertInertia(function (AssertableInertia $page) {
        $page->component('Dashboard/RetinaFulfilmentDashboard');
    });
});

test('show storage dashboard', function () {
    actingAs($this->webUser, 'retina');
    $response = $this->get(route('retina.fulfilment.storage.dashboard'));
    $response->assertInertia(function (AssertableInertia $page) {
        $page->component('Storage/RetinaStorageDashboard');
    });
});

test('show profile', function () {
    actingAs($this->webUser, 'retina');
    $response = $this->get(route('retina.profile.show'));
    $response->assertInertia(function (AssertableInertia $page) {
        $page->component('EditModel')
            ->has(
                'formData.args.updateRoute',
                fn (AssertableInertia $page) => $page
                    ->where('name', 'retina.models.profile.update')
            );
    });
});

test('create rental agreement for 4th customer', function () {
    $rentalAgreement = StoreRentalAgreement::make()->action(
        $this->webUser->customer->fulfilmentCustomer,
        [
            'billing_cycle' => RentalAgreementBillingCycleEnum::MONTHLY,
            'state'         => RentalAgreementStateEnum::ACTIVE,
            'username'      => 'test',
            'email'         => 'hello@aiku.io',
        ]
    );

    expect($rentalAgreement)->toBeInstanceOf(RentalAgreement::class);
});

test('index pallets', function () {
    actingAs($this->webUser, 'retina');
    $this->withoutExceptionHandling();
    $response = $this->get(route('retina.fulfilment.storage.pallets.index'));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Storage/RetinaPallets')
            ->has('title')
            ->has('pageHead')
            ->has('data');
    });
});

test('index pallets sub nav storing', function () {
    actingAs($this->webUser, 'retina');
    $this->withoutExceptionHandling();
    $response = $this->get(route('retina.fulfilment.storage.pallets.storing_pallets.index'));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Storage/RetinaPallets')
            ->has('title')
            ->has('pageHead')
            ->has('data');
    });
});

test('index pallets sub nav returned', function () {
    actingAs($this->webUser, 'retina');
    $this->withoutExceptionHandling();
    $response = $this->get(route('retina.fulfilment.storage.pallets.returned_pallets.index'));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Storage/RetinaPallets')
            ->has('title')
            ->has('pageHead')
            ->has('data');
    });
});

test('index pallets sub nav in process', function () {
    actingAs($this->webUser, 'retina');
    $this->withoutExceptionHandling();
    $response = $this->get(route('retina.fulfilment.storage.pallets.in_process_pallets.index'));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Storage/RetinaPallets')
            ->has('title')
            ->has('pageHead')
            ->has('data');
    });
});

test('index pallets sub nav incidents', function () {
    actingAs($this->webUser, 'retina');
    $this->withoutExceptionHandling();
    $response = $this->get(route('retina.fulfilment.storage.pallets.incidents_pallets.index'));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Storage/RetinaPallets')
            ->has('title')
            ->has('pageHead')
            ->has('data');
    });
});

test('show pallet', function () {
    $this->withoutExceptionHandling();
    actingAs($this->webUser, 'retina');
    $response = $this->get(route('retina.fulfilment.storage.pallets.show', [$this->pallet->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Storage/RetinaPallet')
            ->has('title')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $this->pallet->reference)
                    ->etc()
            )
            ->has('tabs');
    });
});

test('show pallet navigation stays inside its status bucket', function () {
    $this->withoutExceptionHandling();
    actingAs($this->webUser, 'retina');

    $fulfilmentCustomer = $this->webUser->customer->fulfilmentCustomer;

    $storing = Pallet::where('fulfilment_customer_id', $fulfilmentCustomer->id)
        ->whereNotNull('slug')
        ->take(3)
        ->get();

    expect($storing)->not->toBeEmpty();

    Pallet::whereIn('id', $storing->pluck('id'))->update(['status' => PalletStatusEnum::STORING]);

    $other = Pallet::where('fulfilment_customer_id', $fulfilmentCustomer->id)
        ->whereNotIn('id', $storing->pluck('id'))
        ->whereNotNull('slug')
        ->first();

    if ($other) {
        $other->update(['status' => PalletStatusEnum::RETURNED]);
    }

    $storingSlugs = Pallet::whereIn('id', $storing->pluck('id'))->pluck('slug')->all();

    foreach ($storing as $pallet) {
        $response = $this->get(route('retina.fulfilment.storage.pallets.show', [$pallet->slug]).'?bucket=storing');

        $response->assertInertia(function (AssertableInertia $page) use ($storingSlugs, $other) {
            $navigation = $page->toArray()['props']['navigation'];

            foreach (['previous', 'next'] as $direction) {
                if ($navigation[$direction]) {
                    expect($navigation[$direction]['label'])->toBeIn($storingSlugs);

                    if ($other) {
                        expect($navigation[$direction]['label'])->not->toBe($other->slug);
                    }
                }
            }
        });
    }
});

test('edit pallet', function () {
    $this->withoutExceptionHandling();
    actingAs($this->webUser, 'retina');
    $response = $this->get(route('retina.fulfilment.storage.pallets.edit', [$this->pallet->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->has(
                'formData.args.updateRoute',
                fn (AssertableInertia $page) => $page
                    ->where('name', 'retina.models.pallet.update')
                    ->etc()
            );
    });
});

test('index pallet deliveries', function () {
    actingAs($this->webUser, 'retina');
    $this->withoutExceptionHandling();
    $response = $this->get(route('retina.fulfilment.storage.pallet_deliveries.index'));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Storage/RetinaPalletDeliveries')
            ->has('title')
            ->has('pageHead')
            ->has('data');
    });
});

test('index pallet deliveries (tabs upload)', function () {
    actingAs($this->webUser, 'retina');
    $this->withoutExceptionHandling();
    $response = $this->get(route('retina.fulfilment.storage.pallet_deliveries.index', [
        'tab' => PalletDeliveriesTabsEnum::UPLOADS->value
    ]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Storage/RetinaPalletDeliveries')
            ->has('title')
            ->has('pageHead')
            ->has(PalletDeliveriesTabsEnum::UPLOADS->value);
    });
});

test('show pallet delivery (pallet tab)', function () {
    // $this->withoutExceptionHandling();
    actingAs($this->webUser, 'retina');
    $response = $this->get(route('retina.fulfilment.storage.pallet_deliveries.show', [$this->palletDelivery->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Storage/RetinaPalletDelivery')
            ->has('title')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $this->palletDelivery->slug)
                    ->etc()
            )
            ->has('tabs');
    });
});

test('show pallet delivery (attachments tab)', function () {
    // $this->withoutExceptionHandling();
    actingAs($this->webUser, 'retina');

    $response = $this->get(route('retina.fulfilment.storage.pallet_deliveries.show', [
        $this->palletDelivery->slug,
        'tab' => PalletDeliveryTabsEnum::ATTACHMENTS->value
    ]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Storage/RetinaPalletDelivery')
            ->has('title')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $this->palletDelivery->slug)
                    ->etc()
            )
            ->has('tabs');
    });
});

test('show pallet delivery state in process', function () {
    // $this->withoutExceptionHandling();
    actingAs($this->webUser, 'retina');

    $palletDelivery = $this->palletDelivery;

    if ($palletDelivery->state != PalletDeliveryStateEnum::IN_PROCESS) {
        $palletDelivery->update(['state' => PalletDeliveryStateEnum::IN_PROCESS]);
    }

    $palletDelivery->fulfilmentCustomer->rentalAgreement->update([
        'pallets_limit' => 10,
    ]);

    $response = $this->get(route('retina.fulfilment.storage.pallet_deliveries.show', [$this->palletDelivery->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Storage/RetinaPalletDelivery')
            ->has('title')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $this->palletDelivery->slug)
                    ->etc()
            )
            ->has('tabs');
    });

    $palletDelivery->update(['state' => PalletDeliveryStateEnum::CONFIRMED]);
    $palletDelivery->fulfilmentCustomer->rentalAgreement->update([
        'pallets_limit' => null,
    ]);
});

test('show pallet delivery state booked in or booking in', function () {
    // $this->withoutExceptionHandling();
    actingAs($this->webUser, 'retina');

    $palletDelivery = $this->palletDelivery;

    if ($palletDelivery->state != PalletDeliveryStateEnum::BOOKED_IN || $palletDelivery->state != PalletDeliveryStateEnum::BOOKING_IN) {
        $palletDelivery->update(['state' => PalletDeliveryStateEnum::BOOKED_IN]);
    }

    $response = $this->get(route('retina.fulfilment.storage.pallet_deliveries.show', [$this->palletDelivery->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Storage/RetinaPalletDelivery')
            ->has('title')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $this->palletDelivery->slug)
                    ->etc()
            )
            ->has('tabs');
    });

    $palletDelivery->update(['state' => PalletDeliveryStateEnum::CONFIRMED]);
});

test('show pallet delivery (services tab)', function () {
    $this->withoutExceptionHandling();
    actingAs($this->webUser, 'retina');
    $response = $this->get('/app/fulfilment/storage/pallet-deliveries/'.$this->palletDelivery->slug.'?tab=services');
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Storage/RetinaPalletDelivery')
            ->has('title')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->etc()
            )
            ->has('tabs');
    });
});

test('show pallet delivery (physical goods tab)', function () {
    $this->withoutExceptionHandling();
    actingAs($this->webUser, 'retina');
    $response = $this->get('/app/fulfilment/storage/pallet-deliveries/'.$this->palletDelivery->slug.'?tab=physical_goods');
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Storage/RetinaPalletDelivery')
            ->has('title')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->etc()
            )
            ->has('tabs');
    });
});

test('show sysadmin dashboard', function () {
    actingAs($this->webUser, 'retina');
    $response = $this->get(route('retina.sysadmin.fulfilment.dashboard'));
    $response->assertInertia(function (AssertableInertia $page) {
        $page->component('SysAdmin/RetinaSysAdminDashboard');
    });
});

test('index pallet returns', function () {
    actingAs($this->webUser, 'retina');
    $this->withoutExceptionHandling();
    $response = $this->get(route('retina.fulfilment.storage.pallet_returns.index'));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Storage/RetinaPalletReturns')
            ->has('title')
            ->has('pageHead')
            ->has('data');
    });
});

test('show pallet return (pallet tab)', function () {
    // $this->withoutExceptionHandling();
    actingAs($this->webUser, 'retina');
    $response = $this->get(route('retina.fulfilment.storage.pallet_returns.show', [$this->palletReturn->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Storage/RetinaPalletReturn')
            ->has('title')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $this->palletReturn->reference)
                    ->etc()
            )
            ->has('tabs');
    });
});

test('show pallet return (stored item tab)', function () {
    $this->withoutExceptionHandling();
    actingAs($this->webUser, 'retina');
    $response = $this->get('/app/fulfilment/storage/pallet-returns/'.$this->palletReturn->slug.'?tab=stored_items');
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Storage/RetinaPalletReturn')
            ->has('title')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $this->palletReturn->reference)
                    ->etc()
            )
            ->has('tabs');
    });
});

test('show pallet return (services tab)', function () {
    // $this->withoutExceptionHandling();
    actingAs($this->webUser, 'retina');
    $response = $this->get('/app/fulfilment/storage/pallet-returns/'.$this->palletReturn->slug.'?tab=services');
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Storage/RetinaPalletReturn')
            ->has('title')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $this->palletReturn->reference)
                    ->etc()
            )
            ->has('tabs');
    });
});

test('show pallet return (physical goods tab)', function () {
    // $this->withoutExceptionHandling();
    actingAs($this->webUser, 'retina');
    $response = $this->get('/app/fulfilment/storage/pallet-returns/'.$this->palletReturn->slug.'?tab=physical_goods');
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Storage/RetinaPalletReturn')
            ->has('title')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $this->palletReturn->reference)
                    ->etc()
            )
            ->has('tabs');
    });
});

test('index web users', function () {
    // $this->withoutExceptionHandling();
    actingAs($this->webUser, 'retina');
    $response = $this->get(route('retina.sysadmin.web-users.index'));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('SysAdmin/RetinaWebUsers')
            ->has('title')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'Users')
                    ->etc()
            )
            ->has('labels')
            ->has('data');
    });
});

test('create web user', function () {
    // $this->withoutExceptionHandling();
    actingAs($this->webUser, 'retina');
    $response = $this->get(route('retina.sysadmin.web-users.create'));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->has('title')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'Create User')
                    ->etc()
            )
            ->has('formData');
    });
});

test('edit sysadmin settings', function () {
    // $this->withoutExceptionHandling();
    actingAs($this->webUser, 'retina');
    $response = $this->get(route('retina.sysadmin.settings.edit'));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'Account settings')
                    ->etc()
            )
            ->has('formData');
    });
});

test('index stored items', function () {
    // $this->withoutExceptionHandling();
    actingAs($this->webUser, 'retina');
    $response = $this->get(route('retina.fulfilment.itemised_storage.stored_items.index'));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Storage/RetinaStoredItems')
            ->has('title')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'SKOs')
                    ->etc()
            )
            ->has('data');
    });
});

test('index stored item audits', function () {
    $this->withoutExceptionHandling();
    actingAs($this->webUser, 'retina');
    $response = $this->get(route('retina.fulfilment.itemised_storage.stored_items_audits.index'));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Storage/RetinaStoredItemsAudits')
            ->has('title')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'stored item audits')
                    ->etc()
            )
            ->has('data');
    });
});

test('show stored item audit', function () {
    $this->withoutExceptionHandling();
    actingAs($this->webUser, 'retina');
    $response = $this->get(route('retina.fulfilment.itemised_storage.stored_items_audits.show', [
        $this->storedItemAudit->slug
    ]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Storage/RetinaStoredItemsAudit')
            ->has('title')
            ->has('breadcrumbs')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', "SKOs audit")
                    ->etc()
            )
            ->has('pallets')
            ->has('data');
    });
});

test('show dropshipping dashboard', function () {
    actingAs($this->webUser, 'retina');
    $response = $this->get(route('retina.dropshipping.customer_sales_channels.create'));
    $response->assertInertia(function (AssertableInertia $page) {
        $page->component('Dropshipping/DropshippingCreateChannel');
    });
});

test('index pricing (goods)', function () {
    // $this->withoutExceptionHandling();
    actingAs($this->webUser, 'retina');
    $response = $this->get(route('retina.fulfilment.pricing.goods'));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Pricing/RetinaGoods')
            ->has('title')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'Goods')
                    ->etc()
            )
            ->has('data');
    });
});

test('index pricing (services)', function () {
    // $this->withoutExceptionHandling();
    actingAs($this->webUser, 'retina');
    $response = $this->get(route('retina.fulfilment.pricing.services'));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Pricing/RetinaServices')
            ->has('title')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'Services')
                    ->etc()
            )
            ->has('data');
    });
});

test('index pricing (rentals)', function () {
    // $this->withoutExceptionHandling();
    actingAs($this->webUser, 'retina');
    $response = $this->get(route('retina.fulfilment.pricing.rentals'));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Pricing/RetinaRentals')
            ->has('title')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'Rentals')
                    ->etc()
            )
            ->has('data');
    });
});

test('index spaces', function () {
    // $this->withoutExceptionHandling();
    actingAs($this->webUser, 'retina');
    $response = $this->get(route('retina.fulfilment.spaces.index'));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Space/RetinaSpaces')
            ->has('title')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'Spaces')
                    ->etc()
            )
            ->has('data');
    });
});

test('show space', function () {
    // $this->withoutExceptionHandling();
    actingAs($this->webUser, 'retina');
    $response = $this->get(route('retina.fulfilment.spaces.show', [$this->space->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Space/RetinaSpace')
            ->has('title')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $this->space->reference)
                    ->etc()
            )
            ->has('tabs')
            ->has('showcase');
    });
});

test('show billing dashboard', function () {
    actingAs($this->webUser, 'retina');
    $response = $this->get(route('retina.fulfilment.billing.dashboard'));
    $response->assertInertia(function (AssertableInertia $page) {
        $page->component('Billing/RetinaBillingDashboard');
    });
});

test('index invoices', function () {
    // $this->withoutExceptionHandling();
    actingAs($this->webUser, 'retina');
    $response = $this->get(route('retina.fulfilment.billing.invoices.index'));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Billing/RetinaInvoices')
            ->has('title')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'Invoices')
                    ->etc()
            )
            ->has('data');
    });
});

test('show next bill', function () {
    $this->withoutExceptionHandling();
    actingAs($this->webUser, 'retina');
    $response = $this->get(route('retina.fulfilment.billing.next_recurring_bill'));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Billing/RetinaRecurringBill')
            ->has('title')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->etc()
            )
            ->has('currency')
            ->has('box_stats')
            ->has('tabs');
    });
});


test('show pallet return with stored item', function () {
    $this->withoutExceptionHandling();
    actingAs($this->webUser, 'retina');
    $response = $this->get(route('retina.fulfilment.storage.pallet_returns.with-stored-items.show', [
        $this->palletReturn->slug
    ]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Storage/RetinaPalletReturn')
            ->has('title')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $this->palletReturn->reference)
                    ->etc()
            )
            ->has('box_stats')
            ->has('notes_data')
            ->has('tabs');
    });
});

test('show stored item', function () {
    actingAs($this->webUser, 'retina');
    $response = $this->get(route('retina.fulfilment.itemised_storage.stored_items.show', [
        $this->storedItem->slug
    ]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Storage/RetinaStoredItem')
            ->has('title')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $this->storedItem->slug)
                    ->etc()
            )
            ->has('showcase')
            ->has('tabs');
    });
});

test('show stored item (tab pallets)', function () {
    actingAs($this->webUser, 'retina');
    $response = $this->get(route('retina.fulfilment.itemised_storage.stored_items.show', [
        $this->storedItem->slug,
        'tab' => StoredItemTabsEnum::PALLETS->value
    ]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Storage/RetinaStoredItem')
            ->has('title')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $this->storedItem->slug)
                    ->etc()
            )
            ->has(StoredItemTabsEnum::PALLETS->value)
            ->has('tabs');
    });
});

test('show stored item (tab audits)', function () {
    actingAs($this->webUser, 'retina');
    $response = $this->get(route('retina.fulfilment.itemised_storage.stored_items.show', [
        $this->storedItem->slug,
        'tab' => StoredItemTabsEnum::AUDITS->value
    ]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Storage/RetinaStoredItem')
            ->has('title')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $this->storedItem->slug)
                    ->etc()
            )
            ->has(StoredItemTabsEnum::AUDITS->value)
            ->has('tabs');
    });
});

test('show stored item (tab movements)', function () {
    actingAs($this->webUser, 'retina');
    $response = $this->get(route('retina.fulfilment.itemised_storage.stored_items.show', [
        $this->storedItem->slug,
        'tab' => StoredItemTabsEnum::MOVEMENTS->value
    ]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Storage/RetinaStoredItem')
            ->has('title')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $this->storedItem->slug)
                    ->etc()
            )
            ->has(StoredItemTabsEnum::MOVEMENTS->value)
            ->has('tabs');
    });
});

test('show stored item (tab history)', function () {
    actingAs($this->webUser, 'retina');
    $response = $this->get(route('retina.fulfilment.itemised_storage.stored_items.show', [
        $this->storedItem->slug,
        'tab' => StoredItemTabsEnum::HISTORY->value
    ]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Storage/RetinaStoredItem')
            ->has('title')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $this->storedItem->slug)
                    ->etc()
            )
            ->has(StoredItemTabsEnum::HISTORY->value)
            ->has('tabs');
    });
});

test('show web user', function () {
    actingAs($this->webUser, 'retina');
    $response = $this->get(route('retina.sysadmin.web-users.show', [
        $this->webUser->slug
    ]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('SysAdmin/RetinaWebUser')
            ->has('title')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $this->webUser->username)
                    ->etc()
            )
            ->has('data');
    });
});

test('edit web user', function () {
    actingAs($this->webUser, 'retina');
    $response = $this->get(route('retina.sysadmin.web-users.edit', [
        $this->webUser->slug
    ]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'Edit web user')
                    ->etc()
            )
            ->has('formData');
    });
});

test('root web user can update own customer', function () {
    actingAs($this->webUser, 'retina');
    $response = $this->patch(route('retina.models.customer.update', $this->customer->id), [
        'contact_name' => 'Updated Contact Name',
    ]);
    $response->assertRedirect();
    expect($this->customer->refresh()->contact_name)->toBe('Updated Contact Name');
});

test('web user cannot update another customer', function () {
    actingAs($this->webUser, 'retina');
    $otherCustomer = \App\Actions\CRM\Customer\StoreCustomer::make()->action(
        $this->fulfilment->shop,
        \App\Models\CRM\Customer::factory()->definition()
    );
    $originalContactName = $otherCustomer->contact_name;
    $response = $this->patch(route('retina.models.customer.update', $otherCustomer->id), [
        'contact_name' => 'Should Not Update',
    ]);
    $response->assertForbidden();
    expect($otherCustomer->refresh()->contact_name)->toBe($originalContactName);
});

test('retina lists the pallets of the customer own pallet return and refuses the pallet return of another customer', function () {
    actingAs($this->webUser, 'retina');
    $otherCustomer = \App\Actions\CRM\Customer\StoreCustomer::make()->action(
        $this->fulfilment->shop,
        \App\Models\CRM\Customer::factory()->definition()
    );
    $otherPalletReturn = StorePalletReturn::make()->action($otherCustomer->fulfilmentCustomer, [
        'warehouse_id' => $this->warehouse->id,
        'state'        => PalletReturnStateEnum::IN_PROCESS,
    ]);

    $this->getJson(route('retina.json.pallet-return.pallets.index', $otherPalletReturn->slug))->assertForbidden();
    $this->getJson(route('retina.json.pallet-return.pallets.index', $this->palletReturn->slug))->assertOk();
});

test('retina fulfilment writes refuse the pallet return, pallet delivery and stored item of another customer and let the owner through', function () {
    \Illuminate\Support\Facades\Queue::fake();
    actingAs($this->webUser, 'retina');

    $otherFulfilmentCustomer = \App\Actions\CRM\Customer\StoreCustomer::make()->action(
        $this->fulfilment->shop,
        \App\Models\CRM\Customer::factory()->definition()
    )->fulfilmentCustomer;

    $other = [
        'palletReturn'   => StorePalletReturn::make()->action($otherFulfilmentCustomer, ['warehouse_id' => $this->warehouse->id, 'state' => PalletReturnStateEnum::IN_PROCESS]),
        'palletDelivery' => StorePalletDelivery::make()->action($otherFulfilmentCustomer, ['warehouse_id' => $this->warehouse->id, 'state' => PalletDeliveryStateEnum::IN_PROCESS]),
        'storedItem'     => StoreStoredItem::make()->action($otherFulfilmentCustomer, ['reference' => 'other-stored-item']),
    ];
    $own = [
        'palletReturn'   => $this->palletReturn,
        'palletDelivery' => $this->palletDelivery,
        'storedItem'     => $this->storedItem,
    ];

    $writes = [
        ['post', 'retina.models.pallet-return.attachment.attach', 'palletReturn'],
        ['post', 'retina.models.pallet-return.transaction.store', 'palletReturn'],
        ['post', 'retina.models.pallet-return.pallet-return-item.upload', 'palletReturn'],
        ['patch', 'retina.models.pallet-return.address.switch', 'palletReturn'],
        ['post', 'retina.fulfilment.dropshipping.customer_sales_channels.shipment.store_tiktok', 'palletReturn'],
        ['post', 'retina.models.pallet-delivery.attachment.attach', 'palletDelivery'],
        ['post', 'retina.models.pallet-delivery.transaction.store', 'palletDelivery'],
        ['patch', 'retina.models.stored-items.update', 'storedItem'],
    ];

    foreach ($writes as [$verb, $routeName, $parameter]) {
        $route = app('router')->getRoutes()->getByName($routeName);
        $key   = fn (array $models) => $models[$parameter]->getAttribute($route->bindingFieldFor($parameter) ?? $models[$parameter]->getRouteKeyName());

        expect($this->json($verb, route($routeName, $key($other)))->status())->toBe(403, $routeName.' with the record of another customer')
            ->and($this->json($verb, route($routeName, $key($own)))->status())->not->toBe(403, $routeName.' with the customer own record');
    }
});

test('retina fulfilment read routes refuse the pallets, spaces, stored items and exports of another customer and let the owner through', function () {
    actingAs($this->webUser, 'retina');

    $otherFulfilmentCustomer = \App\Actions\CRM\Customer\StoreCustomer::make()->action(
        $this->fulfilment->shop,
        \App\Models\CRM\Customer::factory()->definition()
    )->fulfilmentCustomer;
    $otherPalletDelivery = StorePalletDelivery::make()->action($otherFulfilmentCustomer, ['warehouse_id' => $this->warehouse->id, 'state' => PalletDeliveryStateEnum::IN_PROCESS]);

    $other = [
        'fulfilmentCustomer' => $otherFulfilmentCustomer,
        'palletDelivery'     => $otherPalletDelivery,
        'pallet'             => StoreRetinaPalletFromDelivery::make()->action($otherPalletDelivery, ['type' => PalletTypeEnum::PALLET, 'customer_reference' => 'other-ref', 'notes' => 'other notes']),
        'storedItem'         => StoreStoredItem::make()->action($otherFulfilmentCustomer, ['reference' => 'other-read-item']),
    ];
    $own = [
        'fulfilmentCustomer' => $this->customer->fulfilmentCustomer,
        'palletDelivery'     => $this->palletDelivery,
        'pallet'             => $this->pallet,
        'storedItem'         => $this->storedItem,
    ];

    foreach ([$own['pallet'], $other['pallet']] as $pallet) {
        $pallet->slug ?: $pallet->update(['slug' => 'read-pallet-'.$pallet->id]);
    }

    foreach ([
        'retina.fulfilment.itemised_storage.stored-items.pdf.export'                  => 'fulfilmentCustomer',
        'retina.fulfilment.storage.pallet_returns.pallets.uploads.templates'          => 'fulfilmentCustomer',
        'retina.fulfilment.storage.pallet_returns.stored-items.uploads.templates'     => 'fulfilmentCustomer',
        'retina.fulfilment.itemised_storage.stored_items.show'                        => 'storedItem',
        'retina.fulfilment.storage.pallets.show'                                      => 'pallet',
        'retina.fulfilment.storage.pallets.edit'                                      => 'pallet',
        'retina.models.pallet-delivery.pdf'                                           => 'palletDelivery',
    ] as $routeName => $parameter) {
        $route = app('router')->getRoutes()->getByName($routeName);
        $key   = fn (array $models) => $models[$parameter]->getAttribute($route->bindingFieldFor($parameter) ?? $models[$parameter]->getRouteKeyName());

        expect($this->get(route($routeName, $key($other)))->getStatusCode())->toBe(403, $routeName.' with the record of another customer')
            ->and($this->get(route($routeName, $key($own)))->getStatusCode())->not->toBe(403, $routeName.' with the customer own record');
    }
});

test('logging in flags the browser so the storefront can paint logged in before the first hit lands', function () {
    $this->webUser->update(['password' => \Illuminate\Support\Facades\Hash::make('test')]);

    $response = $this->post(route('retina.login.store'), [
        'username' => $this->webUser->username,
        'password' => 'test',
    ]);

    $response->assertSuccessful();

    $authCookie = collect($response->headers->getCookies())
        ->first(fn ($cookie) => $cookie->getName() === 'iris_vua');

    expect($authCookie)->not->toBeNull()
        ->and($authCookie->isHttpOnly())->toBeFalse();
});

test('only the main user manages the customer web users, and the main user can not be disabled or deleted', function () {
    $this->webUser->updateQuietly(['is_root' => true, 'status' => true]);
    $mainUser = $this->webUser;

    $staffUser = StoreRetinaWebUser::make()->action($this->customer, [
        'username' => 'staff-'.Str::lower(Str::random(6)),
        'email'    => Str::random(8).'@example.com',
        'password' => 'secret',
    ]);

    $otherCustomer = StoreCustomer::make()->action($this->fulfilment->shop, Customer::factory()->definition());
    $otherWebUser  = StoreWebUser::make()->action($otherCustomer, [
        'username' => 'other-'.Str::lower(Str::random(6)),
        'email'    => Str::random(8).'@example.com',
        'password' => 'secret',
    ]);

    actingAs($staffUser, 'retina');
    $this->patch(route('retina.models.web-users.update', $mainUser->id), ['contact_name' => 'Hijacked'])->assertForbidden();
    $this->delete(route('retina.models.web-users.delete', $mainUser->id))->assertForbidden();
    expect($mainUser->fresh()->contact_name)->not->toBe('Hijacked');

    actingAs($mainUser, 'retina');
    $this->get(route('retina.sysadmin.web-users.show', $otherWebUser->slug))->assertForbidden();
    $this->get(route('retina.sysadmin.web-users.edit', $otherWebUser->slug))->assertForbidden();
    $this->patch(route('retina.models.web-users.update', $otherWebUser->id), ['status' => false])->assertForbidden();
    expect($otherWebUser->fresh()->status)->toBeTrue();

    $this->get(route('retina.sysadmin.web-users.edit', $mainUser->slug))
        ->assertInertia(fn (AssertableInertia $page) => $page->component('EditModel')->count('formData.blueprint', 1));
    $this->get(route('retina.sysadmin.web-users.edit', $staffUser->slug))
        ->assertInertia(fn (AssertableInertia $page) => $page->component('EditModel')->count('formData.blueprint', 2));

    $this->patch(route('retina.models.web-users.update', $staffUser->id), ['status' => false])->assertSessionHasNoErrors();
    expect($staffUser->fresh()->status)->toBeFalse();

    $this->patch(route('retina.models.web-users.update', $mainUser->id), ['status' => false])->assertSessionHasErrors('status');
    expect($mainUser->fresh()->status)->toBeTrue();

    $this->delete(route('retina.models.web-users.delete', $mainUser->id))->assertSessionHasErrors('web_user');
    expect(WebUser::find($mainUser->id))->not->toBeNull();

    $retinaSessionKey = 'login_retina_'.sha1(SessionGuard::class);
    $fromStorefront   = ['Referer' => 'http://localhost/app'];

    auth()->forgetGuards();
    $this->withSession([$retinaSessionKey => $mainUser->id])->get(route('retina.dashboard.show'))->assertOk();

    auth()->forgetGuards();
    $this->withSession([$retinaSessionKey => $mainUser->id])->getJson(route('retina.api.profile'), $fromStorefront)->assertOk();

    auth()->forgetGuards();
    $this->withSession([$retinaSessionKey => $staffUser->id])->get(route('retina.dashboard.show'))->assertRedirect(route('retina.login.show'));

    auth()->forgetGuards();
    $this->withSession([$retinaSessionKey => $staffUser->id])->getJson(route('retina.api.profile'), $fromStorefront)->assertUnauthorized();

    auth()->forgetGuards();
    actingAs($mainUser, 'retina');
    $this->delete(route('retina.models.web-users.delete', $staffUser->id))->assertRedirect(route('retina.sysadmin.web-users.index'));
    expect(WebUser::withTrashed()->find($staffUser->id))->toBeNull();
});
