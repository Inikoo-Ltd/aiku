<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Dec 2024 15:01:28 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Feature;

use App\Actions\Analytics\GetSectionRoute;
use App\Actions\Goods\Stock\StoreStock;
use App\Actions\Goods\StockFamily\StoreStockFamily;
use App\Actions\Helpers\Tag\StoreTag;
use App\Actions\Inventory\Location\DeleteLocation;
use App\Actions\Inventory\Location\HydrateLocation;
use App\Actions\Inventory\Location\Search\ReindexLocationSearch;
use App\Actions\Inventory\Location\StoreLocation;
use App\Actions\Inventory\Location\Tags\SyncTagsLocation;
use App\Actions\Inventory\Location\UpdateLocation;
use App\Actions\Inventory\LocationOrgStock\AuditLocationOrgStock;
use App\Actions\Inventory\LocationOrgStock\DeleteLocationOrgStock;
use App\Actions\Inventory\LocationOrgStock\MoveOrgStockToOtherLocation;
use App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock;
use App\Actions\Inventory\LocationOrgStock\UpdateLocationOrgStock;
use App\Actions\Inventory\OrgStock\AddLostAndFoundOrgStock;
use App\Actions\Inventory\OrgStock\HydrateOrgStock;
use App\Actions\Inventory\OrgStock\RemoveLostAndFoundStock;
use App\Actions\Inventory\OrgStock\Search\ReindexOrgStockSearch;
use App\Actions\Inventory\OrgStock\StoreOrgStock;
use App\Actions\Inventory\OrgStock\UpdateOrgStock;
use App\Actions\Inventory\OrgStockFamily\HydrateOrgStockFamily;
use App\Actions\Inventory\OrgStockFamily\Search\ReindexOrgStockFamilySearch;
use App\Actions\Inventory\OrgStockFamily\StoreOrgStockFamily;
use App\Actions\Inventory\Warehouse\HydrateWarehouse;
use App\Actions\Inventory\Warehouse\Search\ReindexWarehouseSearch;
use App\Actions\Inventory\Warehouse\StoreWarehouse;
use App\Actions\Inventory\Warehouse\UpdateWarehouse;
use App\Actions\Inventory\WarehouseArea\HydrateWarehouseArea;
use App\Actions\Inventory\WarehouseArea\Search\ReindexWarehouseAreaSearch;
use App\Actions\Inventory\WarehouseArea\StoreWarehouseArea;
use App\Actions\Inventory\WarehouseArea\UpdateWarehouseArea;
use App\Enums\Analytics\AikuSection\AikuSectionEnum;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Enums\Inventory\LocationStock\LocationStockTypeEnum;
use App\Enums\Inventory\OrgStock\LostAndFoundOrgStockStateEnum;
use App\Enums\Inventory\OrgStockFamily\OrgStockFamilyStateEnum;
use App\Enums\UI\Inventory\LocationTabsEnum;
use App\Enums\UI\Inventory\OrgStockFamilyTabsEnum;
use App\Models\Analytics\AikuScopedSection;
use App\Models\Goods\Stock;
use App\Models\Goods\StockFamily;
use App\Models\Helpers\Tag;
use App\Models\Inventory\Location;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\LostAndFoundStock;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockFamily;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseArea;
use Config;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeAll(function () {
    loadDB();
});

beforeEach(
    function () {
        $this->organisation = createOrganisation();
        $this->group        = group();
        $this->guest        = createAdminGuest($this->group);

        $this->artisan('warehouse:seed-permissions')->assertExitCode(0);

        Config::set("inertia.testing.page_paths", [resource_path("js/Pages/Grp")]);
        $this->user = $this->guest->getUser();
        actingAs($this->user);

    }
);

test('create warehouse', function () {
    $warehouse = StoreWarehouse::make()->action(
        $this->organisation,
        [
            'code' => 'ts12',
            'name' => 'testName',
        ]
    );

    $user = $this->guest->getUser();
    $user->refresh();

    expect($warehouse)->toBeInstanceOf(Warehouse::class)
        ->and($this->organisation->inventoryStats->number_warehouses)->toBe(1)
        ->and($this->organisation->inventoryStats->number_warehouses_state_in_process)->toBe(0)
        ->and($this->organisation->inventoryStats->number_warehouses_state_open)->toBe(1)
        ->and($this->organisation->inventoryStats->number_warehouses_state_closing_down)->toBe(0)
        ->and($this->organisation->inventoryStats->number_warehouses_state_closed)->toBe(0)
        ->and($this->organisation->group->inventoryStats->number_warehouses)->toBe(1)
        ->and($this->organisation->group->inventoryStats->number_warehouses_state_in_process)->toBe(0)
        ->and($this->organisation->group->inventoryStats->number_warehouses_state_open)->toBe(1)
        ->and($user->authorisedWarehouses()->where('organisation_id', $this->organisation->id)->count())->toBe(1)
        ->and($user->number_authorised_warehouses)->toBe(1);


    return $warehouse;
});

test('warehouse cannot be created with same code', function () {
    StoreWarehouse::make()->action(
        $this->organisation,
        [
            'code' => 'ts12',
            'name' => 'testName',
        ]
    );
})->depends('create warehouse')->throws(ValidationException::class);

test('warehouse cannot be created with same code case is sensitive', function () {
    StoreWarehouse::make()->action(
        $this->organisation,
        [
            'code' => 'TS12',
            'name' => 'testName',
        ]
    );
})->depends('create warehouse')->throws(ValidationException::class);

test('update warehouse', function ($warehouse) {
    $warehouse = UpdateWarehouse::make()->action($warehouse, ['name' => 'Pika Ltd']);
    expect($warehouse->name)->toBe('Pika Ltd');
})->depends('create warehouse');

test('create warehouse by command', function () {
    $this->artisan('warehouse:create', [
        'organisation' => $this->organisation->slug,
        'code'         => 'AA',
        'name'         => 'testName A',
    ])->assertExitCode(0);

    $warehouse = Warehouse::where('code', 'AA')->first();

    $organisation = $this->organisation;
    $organisation->refresh();


    expect($organisation->inventoryStats->number_warehouses)->toBe(2)
        ->and($organisation->group->inventoryStats->number_warehouses)->toBe(2)
        ->and($warehouse->roles()->count())->toBe(8);
});

test('seed warehouse permissions', function () {
    setPermissionsTeamId($this->group->id);
    $this->artisan('warehouse:seed-permissions')->assertExitCode(0);
    $warehouse = Warehouse::where('code', 'AA')->first();
    expect($warehouse->roles()->count())->toBe(8);
});


test('create warehouse area', function ($warehouse) {
    $warehouseArea = StoreWarehouseArea::make()->action($warehouse, WarehouseArea::factory()->definition());
    expect($warehouseArea)->toBeInstanceOf($warehouseArea::class)
        ->and($this->organisation->inventoryStats->number_warehouse_areas)->toBe(1)
        ->and($this->organisation->group->inventoryStats->number_warehouse_areas)->toBe(1);

    return $warehouseArea;
})->depends('create warehouse');

test('update warehouse area', function ($warehouseArea) {
    $warehouseArea = UpdateWarehouseArea::make()->action($warehouseArea, ['name' => 'Area 01']);
    expect($warehouseArea->name)->toBe('Area 01');
})->depends('create warehouse area');

test('create location in warehouse', function ($warehouse) {
    $location = StoreLocation::make()->action($warehouse, Location::factory()->definition());
    $warehouse->refresh();
    expect($location)->toBeInstanceOf(Location::class)
        ->and($this->organisation->inventoryStats->number_locations)->toBe(1)
        ->and($this->organisation->inventoryStats->number_locations_status_operational)->toBe(1)
        ->and($this->organisation->inventoryStats->number_locations_status_broken)->toBe(0)
        ->and($this->organisation->group->inventoryStats->number_locations)->toBe(1)
        ->and($this->organisation->group->inventoryStats->number_locations_status_operational)->toBe(1)
        ->and($this->organisation->group->inventoryStats->number_locations_status_broken)->toBe(0)
        ->and($warehouse->stats->number_locations)->toBe(1)
        ->and($warehouse->stats->number_locations_status_operational)->toBe(1)
        ->and($warehouse->stats->number_locations_status_broken)->toBe(0);

    return $location;
})->depends('create warehouse');

test('create other location in warehouse', function ($warehouse) {

    StoreLocation::make()->action(
        $warehouse,
        [
            'code'       => 'AA',
            'max_weight' => 1000,
        ]
    );

    $warehouse->refresh();
    expect($warehouse->stats->number_locations)->toBe(2)
        ->and($warehouse->stats->number_locations_status_operational)->toBe(2)
        ->and($warehouse->stats->number_locations_status_broken)->toBe(0);
})->depends('create warehouse');

test('create location in warehouse area', function ($warehouseArea) {
    $location = StoreLocation::make()->action($warehouseArea, Location::factory()->definition());
    $warehouseArea->refresh();
    $warehouse = $warehouseArea->warehouse;

    expect($location)->toBeInstanceOf(Location::class)
        ->and($this->organisation->inventoryStats->number_locations)->toBe(3)
        ->and($this->organisation->inventoryStats->number_locations_status_operational)->toBe(3)
        ->and($this->organisation->inventoryStats->number_locations_status_broken)->toBe(0)
        ->and($warehouse->stats->number_locations)->toBe(3)
        ->and($warehouse->stats->number_locations_status_operational)->toBe(3)
        ->and($warehouse->stats->number_locations_status_broken)->toBe(0)
        ->and($warehouseArea->stats->number_locations)->toBe(1)
        ->and($warehouseArea->stats->number_locations_status_operational)->toBe(1)
        ->and($warehouseArea->stats->number_locations_status_broken)->toBe(0);

    return $location;
})->depends('create warehouse area');

test('delete location', function (Location $location) {
    $location = DeleteLocation::make()->action($location);

    expect(Location::find($location->id))->toBeNull();

    return $location;
})->depends('create location in warehouse');






test('create org stock', function () {

    $stock = StoreStock::make()->action(
        $this->group,
        array_merge(Stock::factory()->definition(), [
           'state' => StockStateEnum::ACTIVE
       ])
    );

    $orgStock = StoreOrgStock::make()->action(
        $this->organisation,
        $stock
    );

    expect($orgStock)->toBeInstanceOf($orgStock::class)
        ->and($orgStock->code)->toBe($stock->code)
        ->and($orgStock->name)->toBe($stock->name)
        ->and($orgStock->unit_value)->toBe($stock->unit_value)
        ->and($this->organisation->inventoryStats->number_org_stocks)->toBe(1)
        ->and($this->organisation->inventoryStats->number_current_org_stocks)->toBe(1);

    return $orgStock;
});




test('update org stock', function (OrgStock $orgStock) {

    $orgStock = UpdateOrgStock::make()->action(
        orgStock: $orgStock,
        modelData: [
            'last_fetched_at' => now()->addDay()
        ],
        strict: false
    );

    expect($orgStock)->toBeInstanceOf(OrgStock::class);

    return $orgStock;
})->depends('create org stock');

test('create org stock family', function () {

    $stockFamily = StoreStockFamily::make()->action(
        $this->group,
        StockFamily::factory()->definition()
    );
    $stock = StoreStock::make()->action(
        $stockFamily,
        array_merge(Stock::factory()->definition(), [
            'state' => StockStateEnum::ACTIVE
        ])
    );

    /** @var StockFamily $stockFamily */
    $stockFamily = $stock->stockFamily;
    expect($stockFamily)->toBeInstanceOf(StockFamily::class);
    $orgStockFamily = StoreOrgStockFamily::make()->action($this->organisation, $stockFamily, []);
    expect($orgStockFamily)->toBeInstanceOf(OrgStockFamily::class)
        ->and($orgStockFamily->state)->toBe(OrgStockFamilyStateEnum::ACTIVE)
        ->and($this->organisation->inventoryStats->number_org_stock_families)->toBe(1)
        ->and($this->organisation->inventoryStats->number_org_stocks)->toBe(1)
        ->and($this->organisation->inventoryStats->number_current_org_stocks)->toBe(1);
    return $orgStockFamily;
});

test('create org stock from 2nd stock (within stock family)', function () {


    /** @var Stock $stock */
    $stock = Stock::find(2);
    expect($stock)->toBeInstanceOf(Stock::class);

    /** @var StockFamily $stockFamily */
    $stockFamily = $stock->stockFamily;
    expect($stockFamily)->toBeInstanceOf(StockFamily::class);


    $orgStockFamily = $stockFamily->orgStockFamilies()->where('organisation_id', $this->organisation->id)->first();
    expect($orgStockFamily)->toBeInstanceOf(OrgStockFamily::class);
    $orgStock = StoreOrgStock::make()->action(
        $orgStockFamily,
        $stock
    );
    $this->organisation->refresh();
    expect($orgStock)->toBeInstanceOf($orgStock::class)
        ->and($orgStock->orgStockFamily)->toBeInstanceOf(OrgStockFamily::class)
        ->and($orgStock->orgStockFamily->state)->toBe(OrgStockFamilyStateEnum::ACTIVE)
        ->and($this->organisation->inventoryStats->number_org_stock_families)->toBe(1)
        ->and($this->organisation->inventoryStats->number_org_stocks)->toBe(2)
        ->and($this->organisation->inventoryStats->number_current_org_stocks)->toBe(2);

    return $orgStock;
});




test('attach org-stock to location', function (Location $location) {
    $orgStocks = OrgStock::all();
    expect($orgStocks->count())->toBe(2);
    $locationOrgStocks = [];
    foreach ($orgStocks as $orgStock) {
        $locationOrgStocks[] = StoreLocationOrgStock::make()->action($orgStock, $location, [
            'type' => LocationStockTypeEnum::PICKING
        ]);
    }

    expect($location->stats->number_org_stock_slots)->toBe(2)
        ->and($locationOrgStocks[0])->toBeInstanceOf(LocationOrgStock::class);

    return $locationOrgStocks[0];
})->depends('create location in warehouse area');

test('update location org stock', function (LocationOrgStock $locationOrgStock) {
    $locationOrgStock = UpdateLocationOrgStock::make()->action($locationOrgStock, [
        'type' => LocationStockTypeEnum::STORING
    ]);

    expect($locationOrgStock)->toBeInstanceOf(LocationOrgStock::class)
        ->and($locationOrgStock->type)->toBe(LocationStockTypeEnum::STORING);
    return $locationOrgStock;
})->depends('attach org-stock to location');


test('detach stock from location', function (LocationOrgStock $locationOrgStock) {
    $location = $locationOrgStock->location;
    DeleteLocationOrgStock::make()->action($locationOrgStock);
    $location->refresh();
    expect($location->stats->number_org_stock_slots)->toBe(1);
})->depends('attach org-stock to location');


test('audit stock in location', function () {
    $locationOrgStock = LocationOrgStock::first();
    $locationOrgStock = AuditLocationOrgStock::run($locationOrgStock, [
        'quantity' => 2
    ]);
    expect($locationOrgStock->quantity)->toBe(2);
});

test('move stock location', function () {
    /** @var LocationOrgStock $currentLocation */
    $currentLocation = LocationOrgStock::first();
    $targetLocation  = LocationOrgStock::latest()->first();

    expect($currentLocation->quantity)->toBeNumeric(2)
        ->and($targetLocation->quantity)->toBeNumeric(0);

    $currentLocation = MoveOrgStockToOtherLocation::make()->action($currentLocation, $targetLocation, [
        'quantity' => 1
    ]);
    $targetLocation->refresh();
    expect($currentLocation->quantity)->toBeNumeric(1)
        ->and($targetLocation->quantity)->toBeNumeric(1);
})->depends('detach stock from location');

test('update location', function ($location) {
    $location = UpdateLocation::make()->action($location, ['code' => 'AE-3']);
    expect($location->code)->toBe('AE-3');
})->depends('create location in warehouse area');


test('add found stock', function ($location) {
    $lostAndFound = AddLostAndFoundOrgStock::make()->action(
        $location,
        array_merge(LostAndFoundStock::factory()->definition(), [
            'type' => LostAndFoundOrgStockStateEnum::FOUND->value
        ])
    );

    expect($lostAndFound->type)->toBe(LostAndFoundOrgStockStateEnum::FOUND->value);

    return $lostAndFound;
})->depends('create location in warehouse area');

test('add lost stock', function ($location) {
    $lostAndFound = AddLostAndFoundOrgStock::make()->action(
        $location,
        array_merge(LostAndFoundStock::factory()->definition(), [
            'type' => LostAndFoundOrgStockStateEnum::LOST->value
        ])
    );

    expect($lostAndFound->type)->toBe(LostAndFoundOrgStockStateEnum::LOST->value);

    return $lostAndFound;
})->depends('create location in warehouse area');

test('add tag to location', function ($location) {
    $tag = StoreTag::make()->action([
        'name' => 'TAG',
        'type' => 'inventory'
    ]);

    expect($tag)->toBeInstanceOf(Tag::class);

    $location = SyncTagsLocation::make()->action($location, [
        'tags' => [$tag->tag_slug],
    ]);


    expect($location)->toBeInstanceOf(Location::class)
        ->and($location->tags)->not->toBeNull()
        ->and($location->tags()->count())->toBe(1);

    return $location;

})->depends('create location in warehouse area');

test('remove lost stock', function ($lostAndFoundStock) {
    $lostAndFound = RemoveLostAndFoundStock::make()->action($lostAndFoundStock, 2);
    expect($lostAndFound->quantity)->toBe(2.0);
})->depends('add lost stock');

test('remove found stock', function ($lostAndFoundStock) {
    $lostAndFound = RemoveLostAndFoundStock::make()->action($lostAndFoundStock, 2);
    expect($lostAndFound->quantity)->toBe(2.0);
})->depends('add found stock');



test('hydrate warehouses', function (Warehouse $warehouse) {
    HydrateWarehouse::run($warehouse);
    $this->artisan('hydrate:warehouses')->assertExitCode(0);
})->depends('create warehouse');


test('hydrate warehouse areas', function () {
    HydrateWarehouseArea::run(WarehouseArea::first());
    $this->artisan('hydrate:warehouse_areas')->assertExitCode(0);
});

test('hydrate locations', function () {
    HydrateLocation::run(Location::first());
    $this->artisan('hydrate:locations')->assertExitCode(0);
});

test("UI Index locations", function () {

    $warehouse = Warehouse::first();
    $this->withoutExceptionHandling();
    $response = get(
        route("grp.org.warehouses.show.infrastructure.locations.index", [
            $this->organisation->slug,
            $warehouse->slug,
        ])
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component("Org/Warehouse/Locations")
            ->has("title")
            ->has("breadcrumbs", 3)
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page->where("title", "locations")->etc()
            )
            ->has("tagRoute")
            ->has("tagsList")
            ->has("data");
    });
});

test("UI Create location", function () {

    $warehouse = Warehouse::first();
    $response = get(
        route("grp.org.warehouses.show.infrastructure.locations.create", [
            $this->organisation->slug,
            $warehouse->slug,
        ])
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component("CreateModel")
            ->has("title")
            ->has("breadcrumbs", 4)
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page->where("title", "New location")->etc()
            )
            ->has("formData");
    });
});

test("UI Show location", function () {

    $warehouse = Warehouse::first();
    $location = Location::first();
    $response = get(
        route("grp.org.warehouses.show.infrastructure.locations.show", [
            $this->organisation->slug,
            $warehouse->slug,
            $location->slug,
        ])
    );
    $response->assertInertia(function (AssertableInertia $page) use ($location) {
        $page
            ->component("Org/Warehouse/Location")
            ->has("title")
            ->has("breadcrumbs", 3)
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page->where("title", $location->slug)->etc()
            )
            ->has("navigation")
            ->has("tabs");
    });
});

test("UI Show location (showcase tab)", function () {
    $warehouse = Warehouse::first();
    $location = Location::first();
    $response = get(
        route("grp.org.warehouses.show.infrastructure.locations.show", [
            $this->organisation->slug,
            $warehouse->slug,
            $location->slug,
            "tabs" => LocationTabsEnum::SHOWCASE->value,
        ])
    );
    $response->assertInertia(function (AssertableInertia $page) use ($location) {
        $page
            ->component("Org/Warehouse/Location")
            ->has("title")
            ->has("breadcrumbs", 3)
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page->where("title", $location->slug)->etc()
            )
            ->has("navigation")
            ->has("tabs")
            ->has(LocationTabsEnum::SHOWCASE->value);
    });
});

test("UI Edit location", function () {
    $warehouse = Warehouse::first();
    $location = Location::first();
    $response = get(
        route("grp.org.warehouses.show.infrastructure.locations.edit", [
            $this->organisation->slug,
            $warehouse->slug,
            $location->slug,
        ])
    );
    $response->assertInertia(function (AssertableInertia $page) use ($location) {
        $page
            ->component("EditModel")
            ->has("title")
            ->has("breadcrumbs", 3)
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page->where("title", $location->code)->etc()
            )
            ->has("navigation")
            ->has("formData");
    });
});


test("UI Index fulfilment locations", function () {
    $warehouse = Warehouse::first();
    $this->withoutExceptionHandling();
    $user = $this->user;
    $user->givePermissionTo("fulfilment.$warehouse->id.view");
    $response = get(
        route("grp.org.warehouses.show.fulfilment.locations.index", [
            $this->organisation->slug,
            $warehouse->slug,
        ])
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component("Org/Warehouse/Fulfilment/Locations")
            ->has("title")
            ->has("breadcrumbs", 4)
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page->where("title", "locations")->etc()
            )
            ->has("data");
    });
});

test("UI Show fulfilment location", function () {
    $warehouse = Warehouse::first();
    $location = Location::first();
    $this->withoutExceptionHandling();
    $response = get(
        route("grp.org.warehouses.show.fulfilment.locations.show", [
            $this->organisation->slug,
            $warehouse->slug,
            $location->slug,
        ])
    );
    $response->assertInertia(function (AssertableInertia $page) use ($location) {
        $page
            ->component("Org/Warehouse/Location")
            ->has("title")
            ->has("breadcrumbs", 3)
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page->where("title", $location->slug)->etc()
            )
            ->has("navigation")
            ->has("tabs");
    });
})->depends("UI Index fulfilment locations");

test("UI Index warehouses", function () {
    $warehouse = Warehouse::first();
    $this->withoutExceptionHandling();
    $response = get(
        route("grp.org.warehouses.show.infrastructure.dashboard", [
            $this->organisation->slug,
            $warehouse->slug
        ])
    );
    $response->assertInertia(function (AssertableInertia $page) use ($warehouse) {
        $page
            ->component("Org/Warehouse/Warehouse")
            ->has("title")
            ->has("breadcrumbs", 2)
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page->where("title", $warehouse->name)->etc()
            )
            ->has("tabs")
            ->has("tagsList");
    });
});

test("UI show org stock", function (OrgStock $orgStock) {
    $warehouse = $this->organisation->warehouses->first();
    $this->withoutExceptionHandling();

    $response = get(
        route("grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.show", [
            $this->organisation->slug,
            $warehouse->slug,
            $orgStock->slug
        ])
    );
    $response->assertInertia(function (AssertableInertia $page) use ($orgStock) {
        $page
            ->component("Org/Inventory/OrgStock")
            ->has("title")
            ->has("breadcrumbs", 3)
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page->where("title", $orgStock->code)->etc()
            )
            ->has("tabs");
    });
})->depends('create org stock');

test("UI index org stocks all", function () {
    $warehouse = Warehouse::first();
    $this->withoutExceptionHandling();
    $response = get(
        route("grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.index", [
            $this->organisation->slug,
            $warehouse->slug
        ])
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component("Org/Inventory/OrgStocks")
            ->has("title")
            ->has("breadcrumbs", 3)
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page->where("title", 'SKUs')->etc()
            )
            ->has("data");
    });
});

test("UI index org stocks discontinued", function () {
    $warehouse = Warehouse::first();
    $this->withoutExceptionHandling();
    $response = get(
        route("grp.org.warehouses.show.inventory.org_stocks.discontinued_org_stocks.index", [
            $this->organisation->slug,
            $warehouse->slug
        ])
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component("Org/Inventory/OrgStocks")
            ->has("title")
            ->has("breadcrumbs", 3)
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page->where("title", 'SKUs')->etc()
            )
            ->has("data");
    });
});

test("UI index org stocks abnormally", function () {
    $warehouse = Warehouse::first();
    $this->withoutExceptionHandling();
    $response = get(
        route("grp.org.warehouses.show.inventory.org_stocks.abnormality_org_stocks.index", [
            $this->organisation->slug,
            $warehouse->slug
        ])
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component("Org/Inventory/OrgStocks")
            ->has("title")
            ->has("breadcrumbs")
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page->where("title", 'SKUs')->etc()
            )
            ->has("data");
    });
});

test("UI Index warehouse areas", function () {
    $warehouse = Warehouse::first();
    $this->withoutExceptionHandling();
    $response = get(
        route("grp.org.warehouses.show.infrastructure.warehouse_areas.index", [
            $this->organisation->slug,
            $warehouse->slug
        ])
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component("Org/Warehouse/WarehouseAreas")
            ->has("title")
            ->has("breadcrumbs", 3)
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page->where("title", 'warehouse areas')->etc()
            );
    });
});

test("UI Show warehouse area", function () {
    $warehouse = Warehouse::first();
    $warehouseArea = $warehouse->warehouseAreas->first();
    $this->withoutExceptionHandling();
    $response = get(
        route("grp.org.warehouses.show.infrastructure.warehouse_areas.show", [
            $this->organisation->slug,
            $warehouse->slug,
            $warehouseArea->slug

        ])
    );
    $response->assertInertia(function (AssertableInertia $page) use ($warehouseArea) {
        $page
            ->component("Org/Warehouse/WarehouseArea")
            ->has("title")
            ->has("breadcrumbs", 3)
            ->has('navigation')
            ->has('tabs')
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page->where("title", $warehouseArea->name)->etc()
            );
    });
});

test("UI Index Org Stocks", function () {
    $warehouse = Warehouse::first();
    $this->withoutExceptionHandling();
    $response = get(
        route("grp.org.warehouses.show.inventory.org_stocks.current_org_stocks.index", [
            $this->organisation->slug,
            $warehouse->slug
        ])
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component("Org/Inventory/OrgStocks")
            ->has("title")
            ->has("breadcrumbs", 3)
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page->where("title", 'Current SKUs')->etc()
            )
            ->has("data");
    });
});

test("UI Index Org Stock Families", function () {
    $warehouse = Warehouse::first();
    $this->withoutExceptionHandling();
    $response = get(
        route("grp.org.warehouses.show.inventory.org_stock_families.index", [
            $this->organisation->slug,
            $warehouse->slug
        ])
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component("Org/Inventory/OrgStockFamilies")
            ->has("title")
            ->has("breadcrumbs", 3)
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page->where("title", 'SKU Families')->etc()
            )
            ->has("data");
    });
});

test("UI Show Org Stock Family", function (OrgStockFamily $orgStockFamily) {
    $warehouse = Warehouse::first();
    $this->withoutExceptionHandling();
    $response = get(
        route("grp.org.warehouses.show.inventory.org_stock_families.show", [
            $this->organisation->slug,
            $warehouse->slug,
            $orgStockFamily->slug
        ])
    );
    $response->assertInertia(function (AssertableInertia $page) use ($orgStockFamily) {
        $page
            ->component("Org/Inventory/OrgStockFamily")
            ->has("title")
            ->has("breadcrumbs", 3)
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page->where("title", $orgStockFamily->name)->etc()
            )
            ->has("tabs");
    });
})->depends('create org stock family');

test("UI Show Org Stock Family (tab org stocks)", function (OrgStockFamily $orgStockFamily) {
    $warehouse = Warehouse::first();
    $this->withoutExceptionHandling();
    $response = get(
        route("grp.org.warehouses.show.inventory.org_stock_families.show", [
            $this->organisation->slug,
            $warehouse->slug,
            $orgStockFamily->slug,
            'tab' => OrgStockFamilyTabsEnum::ORG_STOCKS->value
        ])
    );
    $response->assertInertia(function (AssertableInertia $page) use ($orgStockFamily) {
        $page
            ->component("Org/Inventory/OrgStockFamily")
            ->has("title")
            ->has("breadcrumbs", 3)
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page->where("title", $orgStockFamily->name)->etc()
            )
            ->has("tabs");
    });
})->depends('create org stock family');

test("UI Index Stock Families", function () {
    $this->withoutExceptionHandling();
    $response = get(
        route("grp.goods.stock-families.index")
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component("Goods/StockFamilies")
            ->has("title")
            ->has("breadcrumbs", 3)
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page->where("title", 'SKU Families')->etc()
            )
            ->has("data");
    });
});

test("UI Create stock family", function () {
    $response = get(
        route("grp.goods.stock-families.create")
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component("CreateModel")
            ->has("title")
            ->has("breadcrumbs", 4)
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page->where("title", "new SKU family")->etc()
            )
            ->has("formData");
    });
});

test("UI index inventory stored item", function () {
    $response = get(
        route("grp.org.warehouses.show.inventory.stored_items.current.index", [
            $this->organisation->slug,
            Warehouse::first()->slug
        ])
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component("Org/Fulfilment/StoredItems")
            ->has("title")
            ->has("breadcrumbs", 4)
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page->where("title", "Customer's SKUs")->etc()
            )
            ->has("tabs");
    });
});

test('UI get section route inventory dashboard', function () {
    $warehouse = Warehouse::first();
    $sectionScope = GetSectionRoute::make()->handle('grp.org.warehouses.show.inventory.dashboard', [
        'organisation' => $this->organisation->slug,
        'warehouse'    => $warehouse->slug
    ]);
    expect($sectionScope)->toBeInstanceOf(AikuScopedSection::class)
        ->and($sectionScope->code)->toBe(AikuSectionEnum::INVENTORY->value)
        ->and($sectionScope->model_slug)->toBe($warehouse->slug);
});

test('UI get section route infrastructure index', function () {
    $warehouse = Warehouse::first();
    $sectionScope = GetSectionRoute::make()->handle("grp.org.warehouses.show.infrastructure.locations.index", [
        'organisation' => $this->organisation->slug,
        'warehouse'    => $warehouse->slug,
    ]);

    expect($sectionScope)->toBeInstanceOf(AikuScopedSection::class)
        ->and($sectionScope->code)->toBe(AikuSectionEnum::INVENTORY_INFRASTRUCTURE->value)
        ->and($sectionScope->model_slug)->toBe($warehouse->slug);
});

test('UI get section route incoming backlog', function () {
    $warehouse = Warehouse::first();
    $sectionScope = GetSectionRoute::make()->handle("grp.org.warehouses.show.incoming.backlog", [
        'organisation' => $this->organisation->slug,
        'warehouse'    => $warehouse->slug,
    ]);

    expect($sectionScope)->toBeInstanceOf(AikuScopedSection::class)
        ->and($sectionScope->code)->toBe(AikuSectionEnum::INVENTORY_INCOMING->value)
        ->and($sectionScope->model_slug)->toBe($warehouse->slug);
});

test('UI get section route org warehouses index', function () {
    $sectionScope = GetSectionRoute::make()->handle('grp.org.warehouses.index', [
        'organisation' => $this->organisation->slug,
    ]);

    expect($sectionScope)->toBeInstanceOf(AikuScopedSection::class)
        ->and($sectionScope->code)->toBe(AikuSectionEnum::ORG_WAREHOUSE->value)
        ->and($sectionScope->model_slug)->toBe($this->organisation->slug);
});

test('warehouse search', function () {
    $this->artisan('search:warehouses')->assertExitCode(0);

    $warehouse = Warehouse::first();
    ReindexWarehouseSearch::run($warehouse);
    expect($warehouse->universalSearch()->count())->toBe(1);
});

test('warehouse area search', function () {
    $this->artisan('search:warehouse_areas')->assertExitCode(0);

    $warehouseArea = WarehouseArea::first();
    ReindexWarehouseAreaSearch::run($warehouseArea);
    expect($warehouseArea->universalSearch()->count())->toBe(1);
});

test('location search', function () {
    $this->artisan('search:locations')->assertExitCode(0);

    $location = Location::first();
    ReindexLocationSearch::run($location);
    expect($location->universalSearch()->count())->toBe(1);
});

test('org stocks search', function () {
    $this->artisan('search:org_stocks')->assertExitCode(0);

    $orgStock = OrgStock::first();
    ReindexOrgStockSearch::run($orgStock);
    expect($orgStock->universalSearch()->count())->toBe(1);
});

test('org stock families search', function () {
    $this->artisan('search:org_stock_families')->assertExitCode(0);

    $orgStockFamily = OrgStockFamily::first();
    ReindexOrgStockFamilySearch::run($orgStockFamily);
    expect($orgStockFamily->universalSearch()->count())->toBe(1);
});

test('org stock hydrator', function () {
    $this->artisan('hydrate:org_stocks')->assertExitCode(0);

    $orgStock = OrgStock::first();
    HydrateOrgStock::run($orgStock);

});

test('org stock families  hydrator', function () {
    $this->artisan('hydrate:org_stock_families')->assertExitCode(0);

    $orgStockFamily = OrgStockFamily::first();
    HydrateOrgStockFamily::run($orgStockFamily);

});

test('inventory  hydrator', function () {
    $this->artisan('hydrate -s inv')->assertExitCode(0);
});
