<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 29-10-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Analytics\GetSectionRoute;
use App\Actions\Dispatching\DeliveryNote\StoreDeliveryNote;
use App\Actions\Inventory\Warehouse\StoreWarehouse;
use App\Actions\Ordering\Order\StoreOrder;
use App\Enums\Analytics\AikuSection\AikuSectionEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\UI\Dispatch\DeliveryNoteTabsEnum;
use App\Models\Analytics\AikuScopedSection;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Helpers\Address;
use App\Models\Inventory\Warehouse;
use App\Models\Ordering\Order;
use Inertia\Testing\AssertableInertia;

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

    $this->group = $this->organisation->group;
    $this->adminGuest = createAdminGuest($this->group);
    $this->customer = createCustomer($this->shop);
    $warehouse = Warehouse::first();
    if (!$warehouse) {
        data_set($storeData, "code", "CODE");
        data_set($storeData, "name", "NAME");

        $warehouse = StoreWarehouse::make()->action($this->organisation, $storeData);
    }
    $this->warehouse = $warehouse;

    $order = Order::first();

    if (!$order) {
        $billingAddress  = new Address(Address::factory()->definition());
        $deliveryAddress = new Address(Address::factory()->definition());

        $modelData = Order::factory()->definition();
        data_set($modelData, 'billing_address', $billingAddress);
        data_set($modelData, 'delivery_address', $deliveryAddress);


        $order = StoreOrder::make()->action($this->customer, $modelData);
    }
    $this->order = $order;


    $deliveryNote = DeliveryNote::first();
    if (!$deliveryNote) {
        $arrayData = [
            'reference'           => 'A123456',
            'state'               => DeliveryNoteStateEnum::UNASSIGNED,
            'email'               => 'test@email.com',
            'phone'               => '+62081353890000',
            'date'                => date('Y-m-d'),
            'delivery_address'    => new Address(Address::factory()->definition()),
            'warehouse_id'        => $this->warehouse->id
        ];
        $deliveryNote = StoreDeliveryNote::make()->action($this->order, $arrayData);
    }
    $this->deliveryNote = $deliveryNote;
    $this->artisan('group:seed_aiku_scoped_sections')->assertExitCode(0);

    Config::set("inertia.testing.page_paths", [resource_path("js/Pages/Grp")]);
    actingAs($this->adminGuest->getUser());
});

test("UI Index dispatching delivery-notes", function () {
    $this->withoutExceptionHandling();

    $response = get(
        route("grp.org.warehouses.show.dispatching.delivery-notes", [
            $this->organisation->slug,
            $this->warehouse->slug,
        ])
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component("Org/Dispatching/DeliveryNotes")
            ->where("title", 'delivery notes')
            ->has("breadcrumbs", 3)
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page
                ->where("title", "Delivery notes")
                ->etc()
            )
            ->has("data");
    });
});

test("UI Index dispatching show delivery-notes", function () {
    $this->withoutExceptionHandling();
    $response = get(
        route("grp.org.warehouses.show.dispatching.delivery-notes.show", [
            $this->organisation->slug,
            $this->warehouse->slug,
            $this->deliveryNote->slug
        ])
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component("Org/Dispatching/DeliveryNote")
            ->where("title", 'delivery note')
            ->has("breadcrumbs", 3)
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page
                ->where("title", $this->deliveryNote->reference)
                ->where("model", 'Delivery Note')
                ->etc()
            )
            ->has('delivery_note')
            ->has("timelines")
            ->has("box_stats")
            ->has("routes")
            ->has(DeliveryNoteTabsEnum::ITEMS->value)
            ->has("tabs");
    });
});

test("UI Index dispatching show delivery-notes (tab picking)", function () {
    $response = get(
        route("grp.org.warehouses.show.dispatching.delivery-notes.show", [
            $this->organisation->slug,
            $this->warehouse->slug,
            $this->deliveryNote->slug
        ])
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component("Org/Dispatching/DeliveryNote")
            ->where("title", 'delivery note')
            ->has("breadcrumbs", 3)
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page
                ->where("title", $this->deliveryNote->reference)
                ->where("model", 'Delivery Note')
                ->etc()
            )
            ->has('delivery_note')
            ->has("alert")
            ->has("notes")
            ->has("timelines")
            ->has("box_stats")
            ->has("routes")
            ->has(DeliveryNoteTabsEnum::PICKINGS->value)
            ->has("tabs");
    });
})->todo();

test('UI get section route dispatching show', function () {
    $sectionScope = GetSectionRoute::make()->handle('grp.org.warehouses.show.dispatching.delivery-notes.show', [
        'organisation' => $this->organisation->slug,
        'warehouse' =>  $this->warehouse->slug,
        'deliveryNote' => $this->deliveryNote->slug
    ]);

    expect($sectionScope)->toBeInstanceOf(AikuScopedSection::class)
        ->and($sectionScope->code)->toBe(AikuSectionEnum::INVENTORY_DISPATCHING->value)
        ->and($sectionScope->model_slug)->toBe($this->warehouse->slug);
});
