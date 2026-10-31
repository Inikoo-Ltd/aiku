<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Dec 2024 15:01:28 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Feature;

use App\Actions\Catalogue\SalesAnalysis\GetSalesAnalysis;
use App\Actions\Catalogue\SalesAnalysis\SalesAnalysisScope;
use App\Enums\UI\Inventory\OrgStockFamilyTabsEnum;
use App\Enums\UI\Inventory\OrgStocksTabsEnum;
use App\Enums\UI\Procurement\OrgStockTabsEnum;
use App\Actions\Goods\Stock\StoreStock;
use App\Actions\Goods\StockFamily\StoreStockFamily;
use App\Actions\Inventory\Location\DeleteLocation;
use App\Actions\Inventory\Location\StoreLocation;
use App\Actions\Inventory\OrgStock\UI\GetOrgStockLabelInformation;
use App\Actions\Inventory\Location\UpdateLocation;
use App\Actions\Inventory\LocationOrgStock\AuditLocationOrgStock;
use App\Actions\Inventory\LocationOrgStock\CalculateValueLocationOrgStock;
use App\Actions\Inventory\LocationOrgStock\DeleteLocationOrgStock;
use App\Actions\Inventory\LocationOrgStock\MoveOrgStockToOtherLocation;
use App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock;
use App\Actions\Inventory\LocationOrgStock\UpdateLocationOrgStock;
use App\Actions\Inventory\OrgStock\AddLostAndFoundOrgStock;
use App\Actions\Inventory\OrgStock\AssignSkoBarcodeToOrgStock;
use App\Actions\Inventory\OrgStock\Json\ScanSkoBarcode;
use App\Actions\Inventory\OrgStock\RemoveLostAndFoundStock;
use App\Actions\Inventory\OrgStock\StoreOrgStock;
use App\Actions\Inventory\OrgStock\UpdateOrgStock;
use App\Actions\Inventory\OrgStockFamily\StoreOrgStockFamily;
use App\Actions\Dispatching\BatchCode\DeleteBatchCode;
use App\Actions\Dispatching\BatchCode\StoreBatchCode;
use App\Actions\Inventory\OrgStockMovement\DeleteOrgStockMovement;
use App\Actions\Inventory\OrgStockMovement\StoreOrgStockMovement;
use App\Actions\Inventory\OrgStockMovement\UpdateOrgStockMovement;
use App\Actions\Inventory\Warehouse\HydrateWarehouse;
use App\Actions\Inventory\Warehouse\StoreWarehouse;
use App\Actions\Inventory\Warehouse\UpdateWarehouse;
use App\Actions\Inventory\WarehouseArea\StoreWarehouseArea;
use App\Actions\Inventory\WarehouseArea\UpdateWarehouseArea;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Enums\Inventory\LocationStock\LocationStockTypeEnum;
use App\Enums\Inventory\OrgStock\LostAndFoundOrgStockStateEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\Inventory\OrgStockFamily\OrgStockFamilyStateEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\Goods\Stock;
use App\Models\Goods\StockFamily;
use App\Models\Inventory\Location;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\LostAndFoundStock;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockFamily;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseArea;
use Config;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Enums\SysAdmin\Authorisation\WarehousePermissionsEnum;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

require_once __DIR__.'/../Support/InventoryHelpers.php';

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

        Config::set("inertia.pages.paths", [resource_path("js/Pages/Grp")]);
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
        ->and($warehouse->roles()->count())->toBe(13);
});

test('seed warehouse permissions', function () {
    setPermissionsTeamId($this->group->id);
    $this->artisan('warehouse:seed-permissions')->assertExitCode(0);
    $warehouse = Warehouse::where('code', 'AA')->first();
    expect($warehouse->roles()->count())->toBe(13);
});

test('stock controllers pass the stock edit authorisation', function () {
    $warehouse = Warehouse::first();
    $user      = $this->guest->getUser();

    setPermissionsTeamId($user->group_id);
    $originalRoles = $user->roles->pluck('name')->toArray();

    $user->syncRoles([RolesEnum::getRoleName('stock-controller', $warehouse)]);
    Cache::tags('auth-user:'.$user->id)->flush();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($user->refresh()->authTo(WarehousePermissionsEnum::getStockEditPermissionNames($this->organisation)))->toBeTrue();

    setPermissionsTeamId($user->group_id);
    $user->syncRoles($originalRoles);
    Cache::tags('auth-user:'.$user->id)->flush();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
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

test('update org stock route needs stock edit permission', function (OrgStock $orgStock) {
    $warehouse = $this->organisation->warehouses()->first() ?? createWarehouse();
    $user      = $this->guest->getUser();

    setPermissionsTeamId($user->group_id);
    $originalRoles = $user->roles->pluck('name')->toArray();
    $reset         = function (array $roles) use ($user) {
        setPermissionsTeamId($user->group_id);
        $user->syncRoles($roles);
        Cache::tags('auth-user:'.$user->id)->flush();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        actingAs($user->refresh());
    };
    $url     = route('grp.org.warehouses.show.inventory.org_stocks.update', [$this->organisation->slug, $warehouse->slug, $orgStock->slug]);
    $showUrl = route('grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.show', [$this->organisation->slug, $warehouse->slug, $orgStock->slug]);

    $reset([RolesEnum::getRoleName('warehouse-viewer', $warehouse)]);
    expect($this->get($showUrl)->assertOk()->viewData('page')['props']['showcase']['barcode_update_route'])->toBeNull();
    $this->patchJson($url, ['name' => 'Renamed by viewer', 'state' => OrgStockStateEnum::DISCONTINUED->value])->assertForbidden();
    expect($orgStock->refresh()->name)->not->toBe('Renamed by viewer')
        ->and($orgStock->state)->toBe(OrgStockStateEnum::ACTIVE);

    $reset([RolesEnum::getRoleName('stock-controller', $warehouse)]);
    expect($this->get($showUrl)->assertOk()->viewData('page')['props']['showcase']['barcode_update_route']['name'])->toBe('grp.org.warehouses.show.inventory.org_stocks.update');
    $this->patchJson($url, ['name' => 'Renamed by stock controller'])->assertOk();
    expect($orgStock->refresh()->name)->toBe('Renamed by stock controller');

    $reset($originalRoles);
})->depends('update org stock');

test('changing an org stock state through update needs the status permission', function (OrgStock $orgStock) {
    $warehouse = $this->organisation->warehouses()->first() ?? createWarehouse();
    $user      = $this->guest->getUser();

    setPermissionsTeamId($user->group_id);
    $originalRoles = $user->roles->pluck('name')->toArray();
    $reset         = function (array $roles) use ($user) {
        setPermissionsTeamId($user->group_id);
        $user->syncRoles($roles);
        Cache::tags('auth-user:'.$user->id)->flush();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        actingAs($user->refresh());
    };
    $url = route('grp.org.warehouses.show.inventory.org_stocks.update', [$this->organisation->slug, $warehouse->slug, $orgStock->slug]);

    $reset([RolesEnum::getRoleName('stock-controller', $warehouse)]);
    $this->patchJson($url, ['state' => OrgStockStateEnum::DISCONTINUING->value])->assertForbidden();
    expect($orgStock->refresh()->state)->toBe(OrgStockStateEnum::ACTIVE);

    $reset([RolesEnum::getRoleName('stock-controller', $warehouse), RolesEnum::getRoleName('supply-chain', $this->group)]);
    $this->patchJson($url, ['state' => OrgStockStateEnum::DISCONTINUING->value])->assertOk();
    expect($orgStock->refresh()->state)->toBe(OrgStockStateEnum::DISCONTINUING);

    UpdateOrgStock::make()->action($orgStock, ['state' => OrgStockStateEnum::ACTIVE->value]);
    $reset($originalRoles);
})->depends('update org stock');

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

test('move stock location', function ($warehouseArea) {
    /** @var LocationOrgStock $sourceSlot */
    $sourceSlot = LocationOrgStock::first();

    $targetSlot = StoreLocationOrgStock::make()->action(
        $sourceSlot->orgStock,
        StoreLocation::make()->action($warehouseArea, Location::factory()->definition()),
        ['type' => LocationStockTypeEnum::PICKING]
    );

    expect($sourceSlot->quantity)->toBeNumeric(2)
        ->and($targetSlot->quantity)->toBeNumeric(0);

    $sourceSlot = MoveOrgStockToOtherLocation::make()->action($sourceSlot, $targetSlot, [
        'quantity' => 1
    ]);
    $targetSlot->refresh();
    expect($sourceSlot->quantity)->toBeNumeric(1)
        ->and($targetSlot->quantity)->toBeNumeric(1);
})->depends('create warehouse area');

test('bulk delete locations needs the typed confirmation and refuses any location with stock', function (Warehouse $warehouse) {
    $stock    = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]));
    $orgStock = StoreOrgStock::make()->action($this->organisation, $stock);

    $empty    = StoreLocation::make()->action($warehouse, Location::factory()->definition());
    $assigned = StoreLocation::make()->action($warehouse, Location::factory()->definition());
    $stocked  = StoreLocation::make()->action($warehouse, Location::factory()->definition());

    $zeroSlot = StoreLocationOrgStock::make()->action($orgStock, $assigned, ['type' => LocationStockTypeEnum::PICKING]);
    StoreLocationOrgStock::make()->action($orgStock, $stocked, ['type' => LocationStockTypeEnum::PICKING])->update(['quantity' => 5]);

    $url = route('grp.models.warehouse.locations.bulk_delete', ['warehouse' => $warehouse->id]);

    delete($url, ['locations' => [$empty->id], 'confirmation' => 'yes'])->assertSessionHasErrors('confirmation');
    delete($url, ['locations' => [$empty->id, $assigned->id], 'confirmation' => 'DELETE 1 LOCATION'])->assertSessionHasErrors('confirmation');
    expect(Location::find($empty->id))->not->toBeNull();

    delete($url, ['locations' => [$empty->id, $assigned->id, $stocked->id], 'confirmation' => 'DELETE 3 LOCATIONS'])
        ->assertSessionHasErrors(['locations' => __('These locations still have stock or pallets, move it before deleting them: :codes', ['codes' => $stocked->code])]);
    expect(Location::whereIn('id', [$empty->id, $assigned->id, $stocked->id])->count())->toBe(3);

    delete($url, ['locations' => [$empty->id, $assigned->id], 'confirmation' => 'DELETE 2 LOCATIONS'])
        ->assertSessionHasNoErrors();

    expect(Location::whereIn('id', [$empty->id, $assigned->id])->count())->toBe(0)
        ->and(LocationOrgStock::find($zeroSlot->id))->toBeNull()
        ->and(Location::find($stocked->id))->not->toBeNull();

    expect(fn () => DeleteLocation::make()->action($stocked))->toThrow(ValidationException::class);

    $stocked->locationOrgStocks()->update(['quantity' => 0]);
    DeleteLocation::make()->action($stocked);
    expect(Location::find($stocked->id))->toBeNull()
        ->and(LocationOrgStock::where('location_id', $stocked->id)->exists())->toBeFalse();
})->depends('create warehouse');

test('remove selected org stocks from a location writes off the stock left in it', function (Warehouse $warehouse) {
    $location      = StoreLocation::make()->action($warehouse, Location::factory()->definition());
    $otherLocation = StoreLocation::make()->action($warehouse, Location::factory()->definition());

    $orgStocks = collect(range(1, 3))->map(function () {
        $stock = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]));

        return StoreOrgStock::make()->action($this->organisation, $stock);
    });

    $emptySlot   = StoreLocationOrgStock::make()->action($orgStocks[0], $location, ['type' => LocationStockTypeEnum::PICKING]);
    $stockedSlot = StoreLocationOrgStock::make()->action($orgStocks[1], $location, ['type' => LocationStockTypeEnum::PICKING]);
    $stockedSlot->update(['quantity' => 5]);
    $keptSlot      = StoreLocationOrgStock::make()->action($orgStocks[2], $location, ['type' => LocationStockTypeEnum::PICKING]);
    $elsewhereSlot = StoreLocationOrgStock::make()->action($orgStocks[2], $otherLocation, ['type' => LocationStockTypeEnum::PICKING]);

    $url = route('grp.models.location.remove_org_stocks', ['location' => $location->id]);

    post($url, ['org_stock_ids' => []])->assertSessionHasErrors('org_stock_ids');

    $notInLocation = StoreOrgStock::make()->action(
        $this->organisation,
        StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]))
    );
    post($url, ['org_stock_ids' => [$notInLocation->id]])->assertSessionHasErrors('org_stock_ids.0');

    post($url, ['org_stock_ids' => [$orgStocks[0]->id, $orgStocks[1]->id]])->assertSessionHasNoErrors();

    expect(LocationOrgStock::find($emptySlot->id))->toBeNull()
        ->and(LocationOrgStock::find($stockedSlot->id))->toBeNull()
        ->and(LocationOrgStock::find($keptSlot->id))->not->toBeNull()
        ->and(LocationOrgStock::find($elsewhereSlot->id))->not->toBeNull();

    $writeOff = DB::table('org_stock_movements')
        ->where('org_stock_id', $orgStocks[1]->id)
        ->where('location_id', $location->id)
        ->where('type', OrgStockMovementTypeEnum::DISASSOCIATE->value)
        ->first();

    expect($writeOff)->not->toBeNull()
        ->and((float) $writeOff->quantity)->toBe(-5.0)
        ->and($writeOff->user_id)->toBe($this->user->id);
})->depends('create warehouse');

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

test("UI show org stock", function (OrgStock $orgStock) {
    $warehouse = $this->organisation->warehouses->first();
    $this->withoutExceptionHandling();

    $orgStock->update(['is_on_demand' => true]);

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
                fn (AssertableInertia $page) => $page->where("title", $orgStock->code)
                    ->where("afterTitle.label", "On Demand")->etc()
            )
            ->has("showcase.latest_movements")
            ->has("tabs");
    });

    $orgStock->update(['is_on_demand' => false]);
})->depends('create org stock');

test("UI show org stock sales analysis tab", function (OrgStock $orgStock) {
    $warehouse = $this->organisation->warehouses->first();

    $response = get(
        route("grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.show", [
            $this->organisation->slug,
            $warehouse->slug,
            $orgStock->slug,
            'tab'         => OrgStockTabsEnum::SALES_ANALYSIS->value,
            'from'        => '2026-01-01',
            'to'          => '2026-03-31',
            'compareFrom' => '2025-01-01',
            'compareTo'   => '2025-03-31',
        ])
    );

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component("Org/Inventory/OrgStock")
            ->missing('sales_analysis')
            ->loadDeferredProps(
                'sales_analysis',
                fn (AssertableInertia $reload) => $reload
                ->where('sales_analysis.period', ['from' => '2026-01-01', 'to' => '2026-03-31'])
                ->where('sales_analysis.frequency', 'daily')
                ->has('sales_analysis.breakdown')
                ->has('sales_analysis.stock_outs')
                ->has('sales_analysis.events')
            );
    });

    $teaser = GetSalesAnalysis::make()->teaser(SalesAnalysisScope::forOrgStock($orgStock));

    expect($teaser)->toHaveKeys(['period', 'compare_period', 'sales', 'compare_sales', 'totals', 'shops', 'breakdown']);
})->depends('create org stock');

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

test("UI Org Stock Family stocks sortable by cover", function (OrgStockFamily $orgStockFamily) {
    $warehouse = Warehouse::first();
    $this->withoutExceptionHandling();

    get(route("grp.org.warehouses.show.inventory.org_stock_families.show.org_stocks.index", [
        $this->organisation->slug,
        $warehouse->slug,
        $orgStockFamily->slug
    ]).'?index_sort=-stock_cover')->assertOk();
})->depends('create org stock family');

test("UI Org Stock Family stocks parts location and next deliveries tabs", function (OrgStockFamily $orgStockFamily) {
    $warehouse = Warehouse::first();
    $this->withoutExceptionHandling();

    foreach ([OrgStocksTabsEnum::LOCATIONS->value => 'locations_list', OrgStocksTabsEnum::NEXT_DELIVERIES->value => 'next_deliveries'] as $tab => $key) {
        get(route("grp.org.warehouses.show.inventory.org_stock_families.show.org_stocks.index", [
            $this->organisation->slug,
            $warehouse->slug,
            $orgStockFamily->slug,
            'tab' => $tab,
        ]))->assertInertia(function (AssertableInertia $page) use ($tab, $key) {
            $page->component('Org/Inventory/OrgStocks')
                ->where('tabs.current', $tab)
                ->has("tabs.navigation.$tab")
                ->has("$tab.data")
                ->where("$tab.data", fn ($rows) => collect($rows)->every(fn ($row) => is_array($row[$key] ?? null)));
        });
    }
})->depends('create org stock family');

test("UI Show Org Stock Family sales analysis tab", function (OrgStockFamily $orgStockFamily) {
    $warehouse = Warehouse::first();

    $response = get(
        route("grp.org.warehouses.show.inventory.org_stock_families.show", [
            $this->organisation->slug,
            $warehouse->slug,
            $orgStockFamily->slug,
            'tab'         => OrgStockFamilyTabsEnum::SALES_ANALYSIS->value,
            'from'        => '2026-01-01',
            'to'          => '2026-03-31',
            'compareFrom' => '2025-01-01',
            'compareTo'   => '2025-03-31',
        ])
    );

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component("Org/Inventory/OrgStockFamily")
            ->missing('sales_analysis')
            ->loadDeferredProps(
                'sales_analysis',
                fn (AssertableInertia $reload) => $reload
                ->where('sales_analysis.period', ['from' => '2026-01-01', 'to' => '2026-03-31'])
                ->where('sales_analysis.frequency', 'daily')
                ->has('sales_analysis.breakdown')
                ->has('sales_analysis.stock_outs')
                ->has('sales_analysis.events')
            );
    });

    $teaser = GetSalesAnalysis::make()->teaser(SalesAnalysisScope::forOrgStockFamily($orgStockFamily));

    expect($teaser)->toHaveKeys(['period', 'compare_period', 'sales', 'compare_sales', 'totals', 'shops', 'breakdown']);
})->depends('create org stock family');

test('calculate value location org stock sets value = quantity * cost', function (Warehouse $warehouse) {
    $stock            = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]));
    $orgStock         = StoreOrgStock::make()->action($this->organisation, $stock);
    $location         = StoreLocation::make()->action($warehouse, Location::factory()->definition());
    $locationOrgStock = StoreLocationOrgStock::make()->action($orgStock, $location, ['type' => LocationStockTypeEnum::PICKING]);
    $locationOrgStock->update(['value' => 9999]);

    CalculateValueLocationOrgStock::run($locationOrgStock->id);

    $locationOrgStock->refresh();
    $expected = (float) $locationOrgStock->quantity * CalculateValueLocationOrgStock::make()->getLppPerSku($locationOrgStock->orgStock, now());
    expect($expected)->toBe(0.0)
        ->and((float) $locationOrgStock->value)->toBe($expected);

    // Guard clauses: null/missing ids return early without throwing
    CalculateValueLocationOrgStock::run(null);
    CalculateValueLocationOrgStock::run(999999999);
    expect((float) $locationOrgStock->fresh()->value)->toBe($expected);

    DeleteLocation::make()->action($location);
})->depends('create warehouse');

test('UI Index warehouses (org level)', function () {
    $this->withoutExceptionHandling();
    get(route('grp.org.warehouses.index', [$this->organisation->slug]))
        ->assertInertia(function (AssertableInertia $page) {
            $page->component('Org/Warehouse/Warehouses')->has('tabs');
        });
})->depends('create warehouse');

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

test('UI Edit org stock warehouse packing', function () {
    $warehouse = Warehouse::first();
    $orgStock  = OrgStock::first();
    $this->withoutExceptionHandling();
    get(route('grp.org.warehouses.show.inventory.org_stocks.current_org_stocks.composition', [
        $this->organisation->slug, $warehouse->slug, $orgStock->slug,
    ]))->assertInertia(function (AssertableInertia $page) {
        $page->component('Goods/ProductComposition')
            ->has('formData.blueprint.0.fields.trade_units.productsContext')
            ->where('formData.args.updateRoute.name', 'grp.models.org_stock.trade_units.update');
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

test('UI Show org stock in family has valid sub navigation routes', function () {
    $warehouse = Warehouse::first();
    $orgStock  = OrgStock::whereNotNull('org_stock_family_id')->first();
    $this->withoutExceptionHandling();
    $routeParameters = [$this->organisation->slug, $warehouse->slug, $orgStock->orgStockFamily->slug, $orgStock->slug];

    get(route('grp.org.warehouses.show.inventory.org_stock_families.show.org_stocks.show', $routeParameters))
        ->assertInertia(function (AssertableInertia $page) {
            $page->component('Org/Inventory/OrgStock')
                ->where('pageHead.subNavigation', function ($subNavigation) {
                    foreach ($subNavigation as $item) {
                        expect(app('router')->has($item['route']['name']))->toBeTrue($item['route']['name']);
                    }

                    return true;
                });
        });

    get(route('grp.org.warehouses.show.inventory.org_stock_families.show.org_stocks.show.delivery_notes', $routeParameters))
        ->assertInertia(function (AssertableInertia $page) {
            $page->component('Org/Inventory/DeliveryNotesInOrgStock');
        });
})->depends('create warehouse', 'create org stock');

test('UI Show org stock labels and compliance tabs', function () {
    $warehouse = Warehouse::where('organisation_id', $this->organisation->id)->orderBy('id')->firstOrFail();
    $orgStock  = OrgStock::where('organisation_id', $this->organisation->id)->whereHas('stock')->orderBy('id')->firstOrFail();
    $this->withoutExceptionHandling();
    $route = fn (string $tab) => route('grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.show.labels', [
        $this->organisation->slug, $warehouse->slug, $orgStock->slug, 'tab' => $tab,
    ]);

    get($route('labels'))->assertInertia(function (AssertableInertia $page) use ($orgStock) {
        $page->component('Org/Inventory/OrgStockLabels')
            ->where('labels.store_route.name', 'grp.models.org_stock.labels.store')
            ->where('labels.mandatory_route.name', 'grp.models.stock.label_mandatory_information.update')
            ->where('labels.abilities.edit', false)
            ->where('pageHead.actions.0.route.name', 'grp.goods.stocks.show.labels')
            ->where('pageHead.actions.0.route.parameters.stock', $orgStock->stock->slug)
            ->has('labels.information_options', count(GetOrgStockLabelInformation::run($orgStock)))
            ->has('labels.labels');
    });
    get($route('compliance'))->assertInertia(function (AssertableInertia $page) {
        $page->component('Org/Inventory/OrgStockLabels')
            ->where('compliance.routes.store.name', 'grp.models.org_stock.compliance-item.store');
    });

    get(route('grp.goods.stocks.show.labels', [$orgStock->stock->slug, 'tab' => 'labels']))->assertInertia(function (AssertableInertia $page) use ($orgStock) {
        $page->component('Org/Inventory/OrgStockLabels')
            ->where('pageHead.model', 'Master SKO')
            ->where('labels.store_route.name', 'grp.models.org_stock.labels.store')
            ->where('labels.abilities.edit', true)
            ->has('labels.information_options', count(GetOrgStockLabelInformation::run($orgStock)));
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
    get(route('grp.org.warehouses.show.inventory.org_stock_families.invoices', [
        $this->organisation->slug, $warehouse->slug, $family->slug,
    ]))->assertStatus(200);
})->depends('create warehouse');

test('UI Show inventory dashboard', function () {
    $warehouse = Warehouse::first();
    $warehouse->stats->update([
        'number_org_stocks_low_stock_audits'            => 4,
        'number_org_stocks_replenishments_wholesale'    => 3,
        'number_org_stocks_replenishments_dropshipping' => 2,
    ]);

    $gauges = collect(get(route('grp.org.warehouses.show.inventory.dashboard', [$this->organisation->slug, $warehouse->slug]))
        ->assertStatus(200)
        ->inertiaProps()['actionGauges'])->keyBy('key');

    expect($gauges->keys()->all())->toBe(['urgent_audit', 'to_replenish'])
        ->and($gauges['urgent_audit']['value'])->toBe(4)
        ->and($gauges['urgent_audit']['total'])->toBe($this->organisation->inventoryStats->number_current_org_stocks)
        ->and($gauges['to_replenish']['value'])->toBe(3)
        ->and($gauges['to_replenish']['secondary']['value'])->toBe(2);
})->depends('create warehouse');

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

test('sko barcode scanner finds an org stock by outer or unit barcode and moves a barcode between org stocks', function ($warehouse) {
    $makeOrgStock = fn (string $code) => StoreOrgStock::make()->action(
        $this->organisation,
        StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['code' => $code, 'state' => StockStateEnum::ACTIVE]))
    );
    $first  = $makeOrgStock('SCANA');
    $second = $makeOrgStock('SCANB');
    UpdateOrgStock::make()->action($first, ['barcode' => 'SKO-SCAN-1', 'unit_barcode' => '5050000000999']);

    expect(ScanSkoBarcode::make()->handle($this->organisation, ' SKO-SCAN-1 ')?->id)->toBe($first->id)
        ->and(ScanSkoBarcode::make()->handle($this->organisation, '5050000000999')?->id)->toBe($first->id)
        ->and(ScanSkoBarcode::make()->handle($this->organisation, 'NOPE'))->toBeNull();

    $scanUrl = fn (string $barcode) => route('grp.json.warehouse.scan_sko_barcode', ['warehouse' => $warehouse->slug, 'barcode' => $barcode]);
    get($scanUrl('SKO-SCAN-1'))->assertOk()->assertJsonPath('found', true)->assertJsonPath('matched_on', 'sko')->assertJsonPath('org_stock.code', $first->code);
    get($scanUrl('5050000000999'))->assertOk()->assertJsonPath('matched_on', 'unit');
    get($scanUrl('NOPE'))->assertOk()->assertJsonPath('found', false)->assertJsonPath('org_stock', null);

    $second = AssignSkoBarcodeToOrgStock::make()->handle($second, ' SKO-SCAN-1 ');
    expect($second->barcode)->toBe('SKO-SCAN-1')
        ->and($second->stock->refresh()->barcode)->toBe('SKO-SCAN-1')
        ->and($first->refresh()->barcode)->toBeNull()
        ->and($first->stock->refresh()->barcode)->toBeNull();

    get(route('grp.org.warehouses.show.inventory.org_stocks.barcode_scanner', [$this->organisation->slug, $warehouse->slug]))
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Org/Inventory/SkoBarcodeScanner')
                ->where('scan_route.name', 'grp.json.warehouse.scan_sko_barcode')
                ->where('assign_route.name', 'grp.org.warehouses.show.inventory.org_stocks.assign_sko_barcode')
                ->etc()
        );
})->depends('create warehouse');

test('move stock to other locations from the SKO page', function ($warehouseArea) {
    $orgStock   = LocationOrgStock::first()->orgStock;
    $newSlot    = fn () => StoreLocationOrgStock::make()->action(
        $orgStock,
        StoreLocation::make()->action($warehouseArea, Location::factory()->definition()),
        ['type' => LocationStockTypeEnum::PICKING]
    );
    $sourceSlot = $newSlot();
    $targetSlot = $newSlot();
    DB::table('location_org_stocks')->where('id', $sourceSlot->id)->update(['quantity' => 64]);
    DB::table('location_org_stocks')->where('id', $targetSlot->id)->update(['quantity' => 30.996666]);

    $this->withoutVite()->patch(route('grp.models.location_org_stock.multi_move', $sourceSlot->id), [
        'targets' => [['location_org_stock_id' => $targetSlot->id, 'quantity' => 64]],
    ])->assertSessionHasNoErrors();

    expect((float) $sourceSlot->refresh()->quantity)->toBe(0.0)
        ->and((float) $targetSlot->refresh()->quantity)->toBe(94.996666);

    $warehouse = $warehouseArea->warehouse;
    $user      = $this->guest->getUser();
    setPermissionsTeamId($user->group_id);
    $originalRoles = $user->roles->pluck('name')->toArray();
    $skoUrl        = route('grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.show', [$this->organisation->slug, $warehouse->slug, $sourceSlot->orgStock->slug]);
    $locationUrl   = route('grp.org.warehouses.show.infrastructure.locations.show', [$this->organisation->slug, $warehouse->slug, $targetSlot->location->slug]);

    $controlsShown = function () use ($skoUrl, $locationUrl) {
        $skoPage = $this->get($skoUrl)->assertOk()->viewData('page')['props'];

        return [
            $skoPage['showcase']['stocks_management']['can_edit'],
            $skoPage['can_move_stock'],
            $skoPage['can_link_supplier_products'],
            $skoPage['can_create_supplier_products'],
            $this->get($locationUrl)->assertOk()->viewData('page')['props']['can_move_location_stock'],
        ];
    };

    expect($controlsShown())->toBe([true, true, true, true, true]);

    actingAsUserWithRoles($user, [RolesEnum::getRoleName('warehouse-viewer', $warehouse)]);
    expect($controlsShown())->toBe([false, false, false, false, false]);
    $this->patch(route('grp.models.location_org_stock.multi_move', $targetSlot->id), [
        'targets' => [['location_org_stock_id' => $sourceSlot->id, 'quantity' => 1]],
    ])->assertForbidden();

    actingAsUserWithRoles($user, $originalRoles);
})->depends('create warehouse area');

test('stock movements keep track of the batches on each location', function ($warehouseArea) {
    $orgStock  = LocationOrgStock::first()->orgStock;
    $warehouse = $warehouseArea->warehouse;
    $newSlot   = fn () => StoreLocationOrgStock::make()->action(
        $orgStock,
        StoreLocation::make()->action($warehouseArea, Location::factory()->definition()),
        ['type' => LocationStockTypeEnum::PICKING]
    );
    $batch = fn (string $code, ?string $bestBefore) => StoreBatchCode::make()->action($warehouse, [
        'code'         => $code.'-'.uniqid(),
        'expiry_date'  => $bestBefore,
        'org_stock_id' => $orgStock->id,
    ]);
    $move = fn (LocationOrgStock $slot, float $quantity, OrgStockMovementTypeEnum $type, array $batches = []) => StoreOrgStockMovement::make()->action(
        $orgStock,
        $slot->location,
        ['type' => $type, 'quantity' => $quantity, 'batches' => $batches]
    );
    $onLocation = fn (LocationOrgStock $slot) => DB::table('org_stock_movement_batches')
        ->where('location_id', $slot->location_id)->where('org_stock_id', $orgStock->id)
        ->groupBy('batch_code_id')->havingRaw('sum(quantity) <> 0')
        ->selectRaw('batch_code_id, sum(quantity)::float as quantity')
        ->pluck('quantity', 'batch_code_id')->all();

    $slotA  = $newSlot();
    $slotB  = $newSlot();
    $late   = $batch('LATE', '2027-06-01');
    $early  = $batch('EARLY', '2027-01-01');
    $loose  = $batch('LOOSE', null);

    $move($slotA, 10, OrgStockMovementTypeEnum::PURCHASE, [['batch_code_id' => $late->id, 'quantity' => 10]]);
    $move($slotA, 5, OrgStockMovementTypeEnum::PURCHASE, [['batch_code_id' => $early->id, 'quantity' => 5]]);
    $move($slotA, 3, OrgStockMovementTypeEnum::FOUND);
    expect((float) $slotA->refresh()->quantity)->toBe(18.0)
        ->and($onLocation($slotA))->toEqual([$late->id => 10.0, $early->id => 5.0]);

    $move($slotA, -4, OrgStockMovementTypeEnum::PICKED);
    expect($onLocation($slotA))->toEqual([$late->id => 10.0, $early->id => 4.0]);

    $picked = $move($slotA, -2, OrgStockMovementTypeEnum::PICKED, [['batch_code_id' => $late->id, 'quantity' => 2]]);
    expect($onLocation($slotA))->toEqual([$late->id => 8.0, $early->id => 4.0]);

    UpdateOrgStockMovement::make()->action($picked, ['quantity' => -5]);
    expect($onLocation($slotA))->toEqual([$late->id => 8.0, $early->id => 1.0]);

    UpdateOrgStockMovement::make()->action($picked->refresh(), ['quantity' => -1]);
    expect($onLocation($slotA))->toEqual([$late->id => 9.0, $early->id => 4.0]);

    DeleteOrgStockMovement::make()->action($picked->refresh());
    expect($onLocation($slotA))->toEqual([$late->id => 10.0, $early->id => 4.0])
        ->and((float) $slotA->refresh()->quantity)->toBe(14.0);

    MoveOrgStockToOtherLocation::make()->action($slotA, $slotB, ['quantity' => 6]);
    expect($onLocation($slotA))->toEqual([$late->id => 8.0])
        ->and($onLocation($slotB))->toEqual([$early->id => 4.0, $late->id => 2.0]);

    $move($slotB, 3, OrgStockMovementTypeEnum::FOUND);
    $move($slotB, -2, OrgStockMovementTypeEnum::PICKED, [['batch_code_id' => $loose->id, 'quantity' => 2]]);
    expect($onLocation($slotB))->toEqual([$early->id => 4.0, $late->id => 2.0])
        ->and((float) $slotB->refresh()->quantity)->toBe(7.0);

    AuditLocationOrgStock::run($slotB->refresh(), ['quantity' => 3]);
    expect($onLocation($slotB))->toEqual([$early->id => 1.0, $late->id => 2.0]);

    expect(fn () => DeleteBatchCode::make()->handle($late))->toThrow(ValidationException::class);
})->depends('create warehouse area');

test('a location counted batch by batch and the best-before of what is on the shelves', function ($warehouseArea) {
    $orgStock  = LocationOrgStock::first()->orgStock;
    $warehouse = $warehouseArea->warehouse;
    $slot      = StoreLocationOrgStock::make()->action(
        $orgStock,
        StoreLocation::make()->action($warehouseArea, Location::factory()->definition()),
        ['type' => LocationStockTypeEnum::PICKING]
    );
    $early = StoreBatchCode::make()->action($warehouse, ['code' => 'COUNT-'.uniqid(), 'expiry_date' => '2027-01-01', 'org_stock_id' => $orgStock->id]);
    StoreOrgStockMovement::make()->action($orgStock, $slot->location, ['type' => OrgStockMovementTypeEnum::PURCHASE, 'quantity' => 5, 'batches' => [['batch_code_id' => $early->id, 'quantity' => 5]]]);
    StoreOrgStockMovement::make()->action($orgStock, $slot->location, ['type' => OrgStockMovementTypeEnum::FOUND, 'quantity' => 5]);

    $soon     = now()->addDays(20)->toDateString();
    $newCode  = 'LABEL-'.uniqid();
    AuditLocationOrgStock::make()->action($slot->refresh(), ['quantity' => 10, 'batches' => [
        ['batch_code_id' => $early->id, 'quantity' => 3],
        ['code' => $newCode, 'expiry_date' => $soon, 'quantity' => 4],
    ]], $this->user);

    $labelled = \App\Models\Dispatching\BatchCode::where('org_stock_id', $orgStock->id)->where('code', $newCode)->firstOrFail();
    $onShelf  = DB::table('org_stock_movement_batches')->where('location_id', $slot->location_id)->where('org_stock_id', $orgStock->id)
        ->groupBy('batch_code_id')->havingRaw('sum(quantity) <> 0')->selectRaw('batch_code_id, sum(quantity)::float as quantity')->pluck('quantity', 'batch_code_id')->all();
    expect($onShelf)->toEqual([$early->id => 3.0, $labelled->id => 4.0])
        ->and((float) $slot->refresh()->quantity)->toBe(10.0);

    $page = $this->withoutVite()->get(route('grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.show.batch_codes', [$this->organisation->slug, $warehouse->slug, $orgStock->slug]))
        ->assertOk()->viewData('page')['props'];
    $listed = collect($page['data']['data'])->keyBy('id');
    $counted = collect($page['batch_count']['locations'])->firstWhere('location_org_stock_id', $slot->id);
    expect($listed[$labelled->id]['quantity_on_hand'])->toEqual(4)
        ->and($listed[$labelled->id]['number_locations'])->toBe(1)
        ->and($listed[$labelled->id]['days_left'])->toBe(20)
        ->and(collect($counted['batches'])->pluck('quantity', 'batch_code_id')->all())->toEqual([$labelled->id => 4.0, $early->id => 3.0]);

    $skoPage = $this->get(route('grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.show', [$this->organisation->slug, $warehouse->slug, $orgStock->slug]))
        ->assertOk()->viewData('page')['props'];
    $moveStockLocation = collect($skoPage['showcase']['stocks_management']['locations'])->firstWhere('id', $slot->id);
    expect(collect($moveStockLocation['batches'])->map(fn ($batch) => [$batch['code'], $batch['expiry_date'], $batch['quantity']])->all())
        ->toEqual([[$newCode, $soon, 4.0], [$early->code, '2027-01-01', 3.0]]);

    $export = new \App\Exports\Inventory\OrgStocksExport($this->organisation);
    $row    = $export->query()->where('org_stocks.id', $orgStock->id)->first();
    $mapped = array_combine($export->headings(), $export->map($row));
    expect($mapped['Earliest Best-before'])->toBe($soon)
        ->and($mapped['Expiring within 30 days'])->toBe(4.0);
})->depends('create warehouse area');

test('locations and warehouse areas are uploaded only by inventory or location editors', function (WarehouseArea $warehouseArea) {
    $warehouse = $warehouseArea->warehouse;
    $user      = \App\Models\SysAdmin\User::factory()->create(['group_id' => $warehouse->group_id, 'status' => true]);

    actingAsUserWithOnlyPermissions($user, ["locations.$warehouse->id.view"]);

    $spreadsheet = fn () => ['file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('upload.csv', "code\nLOC1\n")];

    \Pest\Laravel\post(route('grp.models.warehouse.location.upload', $warehouse->id), $spreadsheet())->assertForbidden();
    \Pest\Laravel\post(route('grp.models.warehouse.warehouse-areas.upload', $warehouse->id), $spreadsheet())->assertForbidden();
    \Pest\Laravel\post(route('grp.models.warehouse_area.location.upload', $warehouseArea->id), $spreadsheet())->assertForbidden();
})->depends('create warehouse area');
