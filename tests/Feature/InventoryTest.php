<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Dec 2024 15:01:28 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Feature;

use App\Actions\Goods\Stock\StoreStock;
use App\Actions\Goods\StockFamily\StoreStockFamily;
use App\Actions\Inventory\Location\DeleteLocation;
use App\Actions\Inventory\Location\HydrateLocation;
use App\Actions\Inventory\Location\Hydrators\LocationHydratePallets;
use App\Actions\Inventory\Location\Hydrators\LocationHydrateSortCode;
use App\Actions\Inventory\Location\Hydrators\LocationHydrateOrgStocks;
use App\Actions\Inventory\Location\Hydrators\LocationHydrateStockValue;
use App\Actions\Inventory\Location\Hydrators\LocationHydrateTotalWeight;
use App\Actions\Inventory\Location\StoreLocation;
use App\Actions\Inventory\Location\UpdateLocation;
use App\Actions\Inventory\LocationOrgStock\AuditLocationOrgStock;
use App\Actions\Inventory\LocationOrgStock\CalculateValueLocationOrgStock;
use App\Actions\Inventory\LocationOrgStock\DeleteLocationOrgStock;
use App\Actions\Inventory\LocationOrgStock\MoveOrgStockToOtherLocation;
use App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock;
use App\Actions\Inventory\LocationOrgStock\UpdateLocationOrgStock;
use App\Actions\Inventory\OrgStock\AddLostAndFoundOrgStock;
use App\Actions\Inventory\OrgStock\AssociateOrgStockToOrgStockFamily;
use App\Actions\Inventory\OrgStock\DeleteOrgStock;
use App\Actions\Inventory\OrgStock\HydrateOrgStock;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateCurrentBatchCodes;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateCurrentSupplierSkuCost;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateLocations;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateMovements;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydratePackedIn;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateProducts;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateProductsAvailableQuantity;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydratePurchaseOrders;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateQuantityInLocations;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateSkuValue;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateStockValue;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateValueInLocations;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateWeekOfCover;
use App\Actions\Inventory\OrgStock\RemoveLostAndFoundStock;
use App\Actions\Inventory\OrgStock\StoreAbnormalOrgStock;
use App\Actions\Inventory\OrgStock\StoreOrgStock;
use App\Actions\Inventory\OrgStock\SyncOrgStockLocations;
use App\Actions\Inventory\OrgStock\UpdateOrgStock;
use App\Actions\Inventory\OrgStockAuditDelta\StoreOrgStockAuditDelta;
use App\Actions\Inventory\OrgStockFamily\DeleteOrgStockFamily;
use App\Actions\Inventory\OrgStockFamily\HydrateOrgStockFamily;
use App\Actions\Inventory\OrgStockFamily\Hydrators\OrgStockFamilyHydrateOrgStocks;
use App\Actions\Inventory\OrgStockFamily\Hydrators\OrgStockFamilyHydratePurchaseOrders;
use App\Actions\Inventory\OrgStockFamily\Hydrators\OrgStockFamilyHydrateStockValue;
use App\Actions\Inventory\OrgStockFamily\Hydrators\OrgStockFamilyHydrateWeekOfCover;
use App\Actions\Inventory\OrgStockFamily\StoreOrgStockFamily;
use App\Actions\Inventory\OrgStockFamily\UpdateOrgStockFamily;
use App\Actions\Inventory\OrgStockMovement\CalculateRunningQuantityOrgStockMovement;
use App\Actions\Inventory\OrgStockMovement\DeleteOrgStockMovement;
use App\Actions\Inventory\OrgStockMovement\StoreOrgStockMovement;
use App\Actions\Inventory\OrgStockMovement\UpdateOrgStockMovement;
use App\Actions\Inventory\Warehouse\DeleteWarehouse;
use App\Actions\Inventory\Warehouse\HydrateWarehouse;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydrateLocations;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydrateStocks;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydrateWarehouseAreas;
use App\Actions\Inventory\Warehouse\StoreWarehouse;
use App\Actions\Inventory\Warehouse\UpdateWarehouse;
use App\Actions\Inventory\WarehouseArea\DeleteWarehouseArea;
use App\Actions\Inventory\WarehouseArea\HydrateWarehouseArea;
use App\Actions\Inventory\WarehouseArea\HydrateWarehouseAreaLocationsSortLocations;
use App\Actions\Inventory\WarehouseArea\Hydrators\WarehouseAreaHydrateLocations;
use App\Actions\Inventory\WarehouseArea\Hydrators\WarehouseAreaHydrateStocks;
use App\Actions\Inventory\WarehouseArea\StoreWarehouseArea;
use App\Actions\Inventory\WarehouseArea\UpdateWarehouseArea;
use App\Actions\SysAdmin\GetSectionRoute;
use App\Enums\Analytics\AikuSection\AikuSectionEnum;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Enums\Inventory\LocationStock\LocationStockTypeEnum;
use App\Enums\Inventory\OrgStock\LostAndFoundOrgStockStateEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\Inventory\OrgStockFamily\OrgStockFamilyStateEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Enums\UI\Inventory\LocationTabsEnum;
use App\Models\Analytics\AikuScopedSection;
use App\Models\Goods\Stock;
use App\Models\Goods\StockFamily;
use App\Models\Inventory\Location;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\LostAndFoundStock;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockAuditDelta;
use App\Models\Inventory\OrgStockFamily;
use App\Models\Inventory\OrgStockMovement;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseArea;
use Config;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeAll(function () {
    loadDB();
});

beforeEach(
    function () {
        $this->organisation = createOrganisation();
        $this->group        = group();
        setPermissionsTeamId($this->group->id);
        $this->guest = createAdminGuest($this->group);

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
        ->and($warehouse->roles()->count())->toBe(10);
});

test('seed warehouse permissions', function () {
    setPermissionsTeamId($this->group->id);
    $this->artisan('warehouse:seed-permissions')->assertExitCode(0);
    $warehouse = Warehouse::where('code', 'AA')->first();
    expect($warehouse->roles()->count())->toBe(10);
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
    $stock       = StoreStock::make()->action(
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
        ->and($orgStockFamily->state)->toBe(OrgStockFamilyStateEnum::IN_PROCESS)
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

    /** @var OrgStockFamily $orgStockFamily */
    $orgStockFamily = $stockFamily->orgStockFamilies()->where('organisation_id', $this->organisation->id)->first();
    expect($orgStockFamily)->toBeInstanceOf(OrgStockFamily::class);
    $orgStock = StoreOrgStock::make()->action(
        $orgStockFamily,
        $stock
    );
    $this->organisation->refresh();
    expect($orgStock)->toBeInstanceOf($orgStock::class)
        ->and($orgStock->orgStockFamily)->toBeInstanceOf(OrgStockFamily::class)
        ->and($orgStock->orgStockFamily->state)->toBe(OrgStockFamilyStateEnum::IN_PROCESS)
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

    expect($locationOrgStock->quantity)->toEqual(2);
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
                fn (AssertableInertia $page) => $page->where("title", "Locations")->etc()
            )
            ->has("data");
    });
});

test("UI Create location", function () {
    $warehouse = Warehouse::first();
    $response  = get(
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
    $location  = Location::first();
    $response  = get(
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
    $location  = Location::first();
    $response  = get(
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
    $location  = Location::first();
    $response  = get(
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
                fn (AssertableInertia $page) => $page->where("title", "Locations")->etc()
            )
            ->has("data");
    });
});

test("UI Show fulfilment location", function () {
    $warehouse = Warehouse::first();
    $location  = Location::first();
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
            ->has("tabs");
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
            );
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
            );
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
            );
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
    $warehouse     = Warehouse::first();
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
            );
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
            );
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
                fn (AssertableInertia $page) => $page->where("title", 'Master SKU Families')->etc()
            );
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
                fn (AssertableInertia $page) => $page->where("title", "New SKU family")->etc()
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
    $warehouse    = Warehouse::first();
    $sectionScope = GetSectionRoute::make()->handle('grp.org.warehouses.show.inventory.dashboard', [
        'organisation' => $this->organisation->slug,
        'warehouse'    => $warehouse->slug
    ]);
    expect($sectionScope)->toBeInstanceOf(AikuScopedSection::class)
        ->and($sectionScope->code)->toBe(AikuSectionEnum::INVENTORY->value)
        ->and($sectionScope->model_slug)->toBe($warehouse->slug);
});

test('UI get section route infrastructure index', function () {
    $warehouse    = Warehouse::first();
    $sectionScope = GetSectionRoute::make()->handle("grp.org.warehouses.show.infrastructure.locations.index", [
        'organisation' => $this->organisation->slug,
        'warehouse'    => $warehouse->slug,
    ]);

    expect($sectionScope)->toBeInstanceOf(AikuScopedSection::class)
        ->and($sectionScope->code)->toBe(AikuSectionEnum::INVENTORY_INFRASTRUCTURE->value)
        ->and($sectionScope->model_slug)->toBe($warehouse->slug);
});

test('UI get section route incoming backlog', function () {
    $warehouse    = Warehouse::first();
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


test('delete org stock succeeds when unused', function () {
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

    $id = $orgStock->id;

    DeleteOrgStock::make()->action($orgStock);

    expect(OrgStock::query()->whereKey($id)->exists())->toBeFalse();
});

test('delete org stock is blocked when linked to a location', function () {
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

    // Create a warehouse and a location, then attach the org stock to that location
    $warehouse = StoreWarehouse::make()->action($this->organisation, [
        'code' => 'WH-DEL',
        'name' => 'Warehouse for delete test',
    ]);

    $area = StoreWarehouseArea::make()->action($warehouse, [
        'code' => 'A1',
        'name' => 'Area 1',
    ]);

    $location = StoreLocation::make()->action(
        $area,
        [
            'code' => 'L1',
            'name' => 'Loc 1',
        ] + Location::factory()->definition()
    );

    StoreLocationOrgStock::make()->action(
        $orgStock,
        $location,
        [
            'quantity' => 0,
        ]
    );

    expect(function () use ($orgStock) {
        DeleteOrgStock::make()->action($orgStock);
    })->toThrow(HttpException::class);
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

test('inventory hydrator', function () {
    $this->artisan('hydrate -s inv')->assertExitCode(0);
});

test('associate org stock to org stock family', function () {
    $stock = StoreStock::make()->action(
        $this->group,
        array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE])
    );

    $orgStock       = StoreOrgStock::make()->action($this->organisation, $stock);
    $orgStockFamily = OrgStockFamily::first();

    $orgStock = AssociateOrgStockToOrgStockFamily::run($orgStock, $orgStockFamily);

    expect($orgStock->org_stock_family_id)->toBe($orgStockFamily->id);
});

test('store org stock audit delta from location org stock', function () {
    $locationOrgStock = LocationOrgStock::first();

    $delta = StoreOrgStockAuditDelta::make()->action($locationOrgStock, [
        'original_quantity' => 5.0,
        'audited_quantity'  => 7.0,
    ]);

    expect($delta)->toBeInstanceOf(OrgStockAuditDelta::class)
        ->and((float) $delta->original_quantity)->toBe(5.0)
        ->and((float) $delta->audited_quantity)->toBe(7.0)
        ->and($delta->org_stock_id)->toBe($locationOrgStock->org_stock_id)
        ->and($delta->location_id)->toBe($locationOrgStock->location_id);
});

test('delete warehouse area with its locations', function () {
    $warehouse = StoreWarehouse::make()->action($this->organisation, [
        'code' => 'WA-DEL',
        'name' => 'Warehouse for area delete test',
    ]);

    $area = StoreWarehouseArea::make()->action($warehouse, [
        'code' => 'AR-D',
        'name' => 'Area for delete test',
    ]);

    StoreLocation::make()->action($area, array_merge(Location::factory()->definition(), ['code' => 'LO-D']));

    $areaId = $area->id;
    DeleteWarehouseArea::make()->handle($area);

    expect(WarehouseArea::find($areaId))->toBeNull();
});

test('delete org stock family dissociates its org stocks', function () {
    $orgStockFamily = OrgStockFamily::first();
    $orgStockIds    = $orgStockFamily->orgStocks->pluck('id');

    DeleteOrgStockFamily::make()->action($orgStockFamily);

    expect(OrgStockFamily::find($orgStockFamily->id))->toBeNull();

    foreach ($orgStockIds as $id) {
        $orgStock = OrgStock::withTrashed()->find($id);
        if ($orgStock) {
            expect($orgStock->org_stock_family_id)->toBeNull();
        }
    }
});

test('OrgStockHydrateLocations recomputes number_locations from truth', function () {
    $orgStock          = OrgStock::first();
    $expectedLocations = $orgStock->locationOrgStocks()->count();

    $orgStock->stats->update(['number_locations' => 9999]);

    OrgStockHydrateLocations::run($orgStock);

    expect($orgStock->stats->fresh()->number_locations)->toBe($expectedLocations);
});

test('OrgStockHydrateMovements recomputes number_org_stock_movements', function () {
    $orgStock          = OrgStock::first();
    $expectedMovements = $orgStock->orgStockMovements()->count();

    $orgStock->stats->update(['number_org_stock_movements' => 9999]);

    OrgStockHydrateMovements::run($orgStock);

    expect($orgStock->stats->fresh()->number_org_stock_movements)->toBe($expectedMovements);
});

test('OrgStockHydrateQuantityInLocations recomputes quantities and short-circuits on missing id', function () {
    $orgStock         = OrgStock::first();
    $expectedQuantity = (float) $orgStock->locationOrgStocks()->sum('quantity');

    $orgStock->update(['quantity_in_locations' => 9999, 'quantity_available' => 9999]);

    OrgStockHydrateQuantityInLocations::run($orgStock->id);

    $orgStock->refresh();
    expect((float) $orgStock->quantity_in_locations)->toBe($expectedQuantity)
        ->and((float) $orgStock->quantity_available)->toBe($expectedQuantity);

    // Guard clauses: null id and missing id return early without throwing
    OrgStockHydrateQuantityInLocations::run(null);
    OrgStockHydrateQuantityInLocations::run(999999999);
    expect((float) $orgStock->fresh()->quantity_in_locations)->toBe($expectedQuantity);
});

test('OrgStockHydrate simple field hydrators recompute their target fields', function () {
    $orgStock = OrgStock::first();

    // Dirty every target field to a sentinel and confirm each hydrator overwrites it
    $orgStock->update([
        'value_in_locations'        => 9999,
        'packed_in'                 => 9999,
        'sku_value'                 => 9999,
        'current_batch_codes'       => 9999,
        'current_supplier_sku_cost' => 9999,
        'has_been_in_warehouse'     => true,
    ]);
    $orgStock->stats->update([
        'number_products'    => 9999,
        'stock_value'        => 9999,
        'week_of_cover'      => 9999,
        'on_the_way_po_count' => 9999,
        'on_the_way_po_value' => 9999,
    ]);

    OrgStockHydrateValueInLocations::run($orgStock);
    OrgStockHydrateProducts::run($orgStock);
    OrgStockHydratePackedIn::run($orgStock);
    OrgStockHydrateStockValue::run($orgStock);
    OrgStockHydrateSkuValue::run($orgStock);
    OrgStockHydrateWeekOfCover::run($orgStock);
    OrgStockHydrateCurrentBatchCodes::run($orgStock);
    OrgStockHydrateCurrentSupplierSkuCost::run($orgStock);
    OrgStockHydratePurchaseOrders::run($orgStock);
    OrgStockHydrateProductsAvailableQuantity::run($orgStock);

    $orgStock->refresh();
    $stats = $orgStock->stats->fresh();

    expect((int) $orgStock->value_in_locations)->not->toBe(9999)
        ->and((int) $orgStock->packed_in)->not->toBe(9999)
        ->and($orgStock->current_batch_codes)->toBe(0)
        ->and((int) $stats->number_products)->toBe($orgStock->products()->count())
        ->and((int) $stats->on_the_way_po_count)->toBe(0)
        ->and((float) $stats->on_the_way_po_value)->toBe(0.0);
});

test('LocationHydrateOrgStocks recomputes slot counts and flags', function () {
    $location            = Location::first();
    $expectedSlots       = $location->locationOrgStocks()->count();
    $expectedHasStock    = $location->locationOrgStocks()->where('dropshipping_pipe', false)->count() > 0;
    $expectedHasDrop     = $location->locationOrgStocks()->where('dropshipping_pipe', true)->count() > 0;

    $location->update(['has_stock_slots' => !$expectedHasStock, 'has_dropshipping_slots' => !$expectedHasDrop]);
    $location->stats->update(['number_org_stock_slots' => 9999]);

    LocationHydrateOrgStocks::run($location);

    $location->refresh();
    expect((int) $location->stats->fresh()->number_org_stock_slots)->toBe($expectedSlots)
        ->and((bool) $location->has_stock_slots)->toBe($expectedHasStock)
        ->and((bool) $location->has_dropshipping_slots)->toBe($expectedHasDrop);
});

test('LocationHydrateStockValue, TotalWeight, Pallets, SortCode recompute their targets', function () {
    $location = Location::first();

    $location->update(['stock_value' => 9999, 'stock_commercial_value' => 9999, 'sort_code' => 'BAD-CODE']);
    $location->stats->update(['total_weight' => 9999, 'number_pallets' => 9999]);

    LocationHydrateStockValue::run($location);
    LocationHydrateTotalWeight::run($location);
    LocationHydratePallets::run($location);
    LocationHydrateSortCode::run($location);

    $location->refresh();
    $stats = $location->stats->fresh();

    expect((float) $location->stock_value)->not->toBe(9999.0)
        ->and((int) $stats->total_weight)->not->toBe(9999)
        ->and((int) $stats->number_pallets)->toBe($location->pallets()->count())
        ->and($location->sort_code)->not->toBe('BAD-CODE');
});

test('WarehouseHydrateLocations, WarehouseAreas, Stocks recompute stats from truth', function () {
    $warehouse         = Warehouse::first();
    $expectedLocations = $warehouse->locations()->count();
    $expectedAreas     = $warehouse->warehouseAreas()->count();

    $warehouse->stats->update([
        'number_locations'       => 9999,
        'number_warehouse_areas' => 9999,
        'stock_value'            => 9999,
    ]);

    WarehouseHydrateLocations::run($warehouse);
    WarehouseHydrateWarehouseAreas::run($warehouse);
    WarehouseHydrateStocks::run($warehouse);

    $stats = $warehouse->stats->fresh();
    expect((int) $stats->number_locations)->toBe($expectedLocations)
        ->and((int) $stats->number_warehouse_areas)->toBe($expectedAreas)
        ->and((int) $stats->stock_value)->not->toBe(9999);
});

test('WarehouseAreaHydrateLocations & Stocks & SortLocations recompute correctly', function () {
    $warehouseArea     = WarehouseArea::first();
    $expectedLocations = $warehouseArea->locations()->count();

    $warehouseArea->stats->update([
        'number_locations' => 9999,
        'stock_value'      => 9999,
    ]);

    WarehouseAreaHydrateLocations::run($warehouseArea);
    WarehouseAreaHydrateStocks::run($warehouseArea);
    HydrateWarehouseAreaLocationsSortLocations::run($warehouseArea);

    $stats = $warehouseArea->stats->fresh();
    expect((int) $stats->number_locations)->toBe($expectedLocations)
        ->and((int) $stats->stock_value)->not->toBe(9999);
});

test('OrgStockFamily hydrators recompute stats from truth', function () {
    $stockFamily = StoreStockFamily::make()->action($this->group, StockFamily::factory()->definition());
    $stock       = StoreStock::make()->action($stockFamily, array_merge(Stock::factory()->definition(), [
        'state' => StockStateEnum::ACTIVE,
    ]));
    $orgStockFamily = StoreOrgStockFamily::make()->action($this->organisation, $stockFamily, []);
    StoreOrgStock::make()->action($orgStockFamily, $stock);

    $expectedOrgStocks = $orgStockFamily->orgStocks()->count();

    $orgStockFamily->stats->update([
        'number_org_stocks'   => 9999,
        'stock_value'         => 9999,
        'on_the_way_po_count' => 9999,
        'on_the_way_po_value' => 9999,
        'week_of_cover'       => 9999,
    ]);

    OrgStockFamilyHydrateOrgStocks::run($orgStockFamily);
    OrgStockFamilyHydrateStockValue::run($orgStockFamily);
    OrgStockFamilyHydratePurchaseOrders::run($orgStockFamily);
    OrgStockFamilyHydrateWeekOfCover::run($orgStockFamily);

    $stats = $orgStockFamily->stats->fresh();
    expect((int) $stats->number_org_stocks)->toBe($expectedOrgStocks)
        ->and((int) $stats->on_the_way_po_count)->toBe(0)
        ->and((float) $stats->on_the_way_po_value)->toBe(0.0);
});

test('update org stock family', function () {
    $stockFamily    = StoreStockFamily::make()->action($this->group, StockFamily::factory()->definition());
    $orgStockFamily = StoreOrgStockFamily::make()->action($this->organisation, $stockFamily, []);

    $orgStockFamily = UpdateOrgStockFamily::make()->action($orgStockFamily, ['name' => 'Family Renamed']);

    expect($orgStockFamily->name)->toBe('Family Renamed');
});

test('store abnormal org stock', function () {
    $orgStock = StoreAbnormalOrgStock::make()->action(
        $this->organisation,
        [
            'code'  => 'ABN-01',
            'name'  => 'Abnormal SKU',
            'state' => OrgStockStateEnum::ABNORMALITY,
        ],
        strict: false,
        audit: false,
    );

    expect($orgStock)->toBeInstanceOf(OrgStock::class)
        ->and($orgStock->state)->toBe(OrgStockStateEnum::ABNORMALITY)
        ->and($orgStock->code)->toBe('ABN-01');
});

test('store, update, and delete org stock movement', function () {
    $stock    = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]));
    $orgStock = StoreOrgStock::make()->action($this->organisation, $stock);

    $warehouse = StoreWarehouse::make()->action($this->organisation, ['code' => 'MV-WH', 'name' => 'Movement WH']);
    $area      = StoreWarehouseArea::make()->action($warehouse, ['code' => 'MV-AR', 'name' => 'Movement Area']);
    $location  = StoreLocation::make()->action($area, array_merge(Location::factory()->definition(), ['code' => 'MV-LOC']));

    StoreLocationOrgStock::make()->action($orgStock, $location, ['type' => LocationStockTypeEnum::STORING]);

    $movement = StoreOrgStockMovement::make()->action(
        $orgStock,
        $location,
        [
            'type'     => OrgStockMovementTypeEnum::PURCHASE->value,
            'quantity' => 10,
        ]
    );

    expect($movement)->toBeInstanceOf(OrgStockMovement::class)
        ->and((float) $movement->quantity)->toBe(10.0);

    $movement = UpdateOrgStockMovement::make()->action($movement, ['note' => 'updated'], strict: false);
    expect($movement->note)->toBe('updated');

    CalculateRunningQuantityOrgStockMovement::run($movement->id);
    CalculateRunningQuantityOrgStockMovement::run(null);

    $deleted = DeleteOrgStockMovement::make()->action($movement);
    expect(OrgStockMovement::find($deleted->id))->toBeNull();
});

test('sync org stock locations creates, updates and removes links', function () {
    $stock    = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]));
    $orgStock = StoreOrgStock::make()->action($this->organisation, $stock);

    $warehouse = StoreWarehouse::make()->action($this->organisation, ['code' => 'SY-WH', 'name' => 'Sync WH']);
    $area      = StoreWarehouseArea::make()->action($warehouse, ['code' => 'SY-AR', 'name' => 'Sync Area']);
    $locA      = StoreLocation::make()->action($area, array_merge(Location::factory()->definition(), ['code' => 'SY-L1']));
    $locB      = StoreLocation::make()->action($area, array_merge(Location::factory()->definition(), ['code' => 'SY-L2']));

    // Start with location A
    SyncOrgStockLocations::make()->action($orgStock, [
        'locationsData' => [
            $locA->id => ['type' => LocationStockTypeEnum::STORING->value],
        ],
    ]);
    expect(LocationOrgStock::where('org_stock_id', $orgStock->id)->pluck('location_id')->toArray())
        ->toEqualCanonicalizing([$locA->id]);

    // Swap to location B (update existing removed, new added)
    SyncOrgStockLocations::make()->action($orgStock, [
        'locationsData' => [
            $locB->id => ['type' => LocationStockTypeEnum::STORING->value],
        ],
    ]);
    expect(LocationOrgStock::where('org_stock_id', $orgStock->id)->pluck('location_id')->toArray())
        ->toEqualCanonicalizing([$locB->id]);
});

test('calculate value location org stock sets value = quantity * cost', function () {
    $locationOrgStock = LocationOrgStock::first();
    $locationOrgStock->update(['value' => 9999]);

    CalculateValueLocationOrgStock::run($locationOrgStock->id);

    $locationOrgStock->refresh();
    // Value should be recomputed = quantity * cost_per_sku (0 quantity or 0 cost gives 0)
    $expected = (float) $locationOrgStock->quantity * (float) $locationOrgStock->orgStock->sku_value;
    expect((float) $locationOrgStock->value)->toBe($expected);

    // Guard clauses: null/missing ids return early without throwing
    CalculateValueLocationOrgStock::run(null);
    CalculateValueLocationOrgStock::run(999999999);
    expect((float) $locationOrgStock->fresh()->value)->toBe($expected);
});

test('delete warehouse deletes areas and locations', function () {
    $warehouse = StoreWarehouse::make()->action($this->organisation, ['code' => 'DEL-WH', 'name' => 'To be deleted']);
    $area      = StoreWarehouseArea::make()->action($warehouse, ['code' => 'DEL-AR', 'name' => 'Area']);
    $location  = StoreLocation::make()->action($area, array_merge(Location::factory()->definition(), ['code' => 'DEL-L1']));

    $warehouseId = $warehouse->id;
    $areaId      = $area->id;
    $locationId  = $location->id;

    DeleteWarehouse::make()->handle($warehouse);

    expect(Warehouse::find($warehouseId))->toBeNull()
        ->and(WarehouseArea::find($areaId))->toBeNull()
        ->and(Location::find($locationId))->toBeNull();
});

test('UI Index warehouses (org level)', function () {
    $this->withoutExceptionHandling();
    get(route('grp.org.warehouses.index', [$this->organisation->slug]))
        ->assertInertia(function (AssertableInertia $page) {
            $page->component('Org/Warehouse/Warehouses')->has('tabs');
        });
})->depends('create warehouse');

test('UI Create warehouse', function () {
    $this->withoutExceptionHandling();
    get(route('grp.org.warehouses.create', [$this->organisation->slug]))
        ->assertInertia(function (AssertableInertia $page) {
            $page->component('CreateModel')->has('formData');
        });
});

test('UI Edit warehouse', function () {
    $warehouse = Warehouse::first();
    $this->withoutExceptionHandling();
    get(route('grp.org.warehouses.show.infrastructure.edit', [$this->organisation->slug, $warehouse->slug]))
        ->assertInertia(function (AssertableInertia $page) {
            $page->component('EditModel')->has('formData');
        });
})->depends('create warehouse');

test('UI Create warehouse area', function () {
    $warehouse = Warehouse::first();
    $this->withoutExceptionHandling();
    get(route('grp.org.warehouses.show.infrastructure.warehouse_areas.create', [$this->organisation->slug, $warehouse->slug]))
        ->assertInertia(function (AssertableInertia $page) {
            $page->component('CreateModel')->has('formData');
        });
})->depends('create warehouse');

test('UI Edit warehouse area', function () {
    $warehouse     = Warehouse::first();
    $warehouseArea = $warehouse->warehouseAreas->first();
    $this->withoutExceptionHandling();
    get(route('grp.org.warehouses.show.infrastructure.warehouse_areas.edit', [
        $this->organisation->slug, $warehouse->slug, $warehouseArea->slug,
    ]))->assertInertia(function (AssertableInertia $page) {
        $page->component('EditModel')->has('formData');
    });
})->depends('create warehouse area');

test('UI Edit org stock', function () {
    $warehouse = Warehouse::first();
    $orgStock  = OrgStock::first();
    $this->withoutExceptionHandling();
    get(route('grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.edit', [
        $this->organisation->slug, $warehouse->slug, $orgStock->slug,
    ]))->assertInertia(function (AssertableInertia $page) {
        $page->component('EditModel')->has('formData');
    });
})->depends('create warehouse', 'create org stock');

test('UI Show org stock procurement tab', function () {
    $warehouse = Warehouse::first();
    $orgStock  = OrgStock::first();
    $this->withoutExceptionHandling();
    get(route('grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.show.procurement', [
        $this->organisation->slug, $warehouse->slug, $orgStock->slug,
    ]))->assertInertia(function (AssertableInertia $page) {
        $page->component('Org/Inventory/OrgStock');
    });
})->depends('create warehouse', 'create org stock');

test('UI Show org stock products tab', function () {
    $warehouse = Warehouse::first();
    $orgStock  = OrgStock::first();
    $this->withoutExceptionHandling();
    get(route('grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.show.products', [
        $this->organisation->slug, $warehouse->slug, $orgStock->slug,
    ]))->assertInertia(function (AssertableInertia $page) {
        $page->component('Org/Inventory/OrgStock');
    });
})->depends('create warehouse', 'create org stock');

test('UI Show org stock stock_history tab', function () {
    $warehouse = Warehouse::first();
    $orgStock  = OrgStock::first();
    $this->withoutExceptionHandling();
    get(route('grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.show.stock_history', [
        $this->organisation->slug, $warehouse->slug, $orgStock->slug,
    ]))->assertInertia(function (AssertableInertia $page) {
        $page->component('Org/Inventory/OrgStock');
    });
})->depends('create warehouse', 'create org stock');

test('UI Index org stocks with no products (orphan)', function () {
    $warehouse = Warehouse::first();
    $this->withoutExceptionHandling();
    get(route('grp.org.warehouses.show.inventory.org_stocks.orphan-product.index', [
        $this->organisation->slug, $warehouse->slug,
    ]))->assertInertia(function (AssertableInertia $page) {
        $page->component('Org/Inventory/OrgStocks');
    });
})->depends('create warehouse');

test('UI Index invoices in org stock family', function () {
    $warehouse   = Warehouse::first();
    $stockFamily = StoreStockFamily::make()->action($this->group, StockFamily::factory()->definition());
    $family      = StoreOrgStockFamily::make()->action($this->organisation, $stockFamily, []);

    $this->withoutExceptionHandling();
    get(route('grp.org.warehouses.show.inventory.org_stock_families.show.invoices.index', [
        $this->organisation->slug, $warehouse->slug, $family->slug,
    ]))->assertStatus(200);
})->depends('create warehouse');

test('UI Show inventory dashboard', function () {
    $warehouse = Warehouse::first();
    get(route('grp.org.warehouses.show.inventory.dashboard', [$this->organisation->slug, $warehouse->slug]))
        ->assertStatus(200);
})->depends('create warehouse');

test('UI Index org stock movements (overview)', function () {
    get(route('grp.overview.inventory.org-stock-movements.index'))
        ->assertStatus(200);
});

test('UI Index and Show OrganisationStockHistory', function () {
    $warehouse = Warehouse::first();

    // OrganisationStockHistory has no warehouse_id column; keyed by organisation_id + date
    $history = \App\Models\Inventory\OrganisationStockHistory::firstOrCreate(
        [
            'organisation_id' => $this->organisation->id,
            'date'            => now()->format('Y-m-d'),
        ],
        [
            'group_id'                       => $this->group->id,
            'number_org_stocks'              => 0,
            'number_out_of_stock_org_stocks' => 0,
            'number_location_org_stocks'     => 0,
        ]
    );

    get(route('grp.org.warehouses.show.inventory.org_stock_histories.index', [
        $this->organisation->slug, $warehouse->slug,
    ]))->assertStatus(200);

    get(route('grp.org.warehouses.show.inventory.org_stock_histories.show', [
        $this->organisation->slug, $warehouse->slug, $history->id,
    ]))->assertStatus(200);

    return $history;
})->depends('create warehouse');
