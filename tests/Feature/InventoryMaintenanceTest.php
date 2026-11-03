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
use App\Actions\Inventory\Location\Hydrators\LocationHydratePallets;
use App\Actions\Inventory\Location\Hydrators\LocationHydrateSortCode;
use App\Actions\Inventory\Location\Hydrators\LocationHydrateOrgStocks;
use App\Actions\Inventory\Location\Hydrators\LocationHydrateStockValue;
use App\Actions\Inventory\Location\Hydrators\LocationHydrateTotalWeight;
use App\Actions\Helpers\CreateSortCode;
use App\Actions\Inventory\Location\StoreLocation;
use App\Actions\Inventory\OrgStock\UI\IndexOrgStocksInLocation;
use Illuminate\Routing\Route;
use App\Actions\Inventory\Location\UpdateLocation;
use App\Actions\Inventory\LocationOrgStock\AuditLocationOrgStock;
use App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock;
use App\Actions\Inventory\LocationOrgStock\UpdateLocationOrgStock;
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
use App\Actions\Inventory\OrgStock\StoreAbnormalOrgStock;
use App\Actions\Inventory\OrgStock\StoreOrgStock;
use App\Actions\Inventory\OrgStock\SyncOrgStockLocations;
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
use App\Actions\Maintenance\Inventory\OrgStockMovement\RepairPickedOrgStockMovementCost;
use App\Actions\Maintenance\Inventory\OrgStockMovement\RecostAuroraStockCountMovements;
use App\Actions\Maintenance\Inventory\OrgStock\EraseOrgStockHistory;
use App\Actions\Maintenance\Inventory\OrgStock\ZeroOrgStockValue;
use App\Actions\Maintenance\Inventory\OrgStock\RepriceOrgStockCounts;
use App\Actions\Inventory\OrgStockMovement\UpdateOrgStockMovement;
use App\Actions\Inventory\Warehouse\DeleteWarehouse;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydrateLocations;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydrateStocks;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydrateWarehouseAreas;
use App\Actions\Inventory\Warehouse\StoreWarehouse;
use App\Actions\Inventory\WarehouseArea\DeleteWarehouseArea;
use App\Actions\Inventory\WarehouseArea\HydrateWarehouseAreaLocationsSortLocations;
use App\Actions\Inventory\WarehouseArea\Hydrators\WarehouseAreaHydrateLocations;
use App\Actions\Inventory\WarehouseArea\Hydrators\WarehouseAreaHydrateStocks;
use App\Actions\Inventory\WarehouseArea\StoreWarehouseArea;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Enums\Inventory\LocationStock\LocationStockTypeEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\Goods\Stock;
use App\Models\Goods\StockFamily;
use App\Models\Inventory\Location;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockAuditDelta;
use App\Models\Inventory\OrgStockFamily;
use App\Models\Inventory\OrgStockMovement;
use Illuminate\Support\Str;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseArea;
use Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function Pest\Laravel\actingAs;

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
        ensureWarehouseLayoutAndOrgStockFamily($this->organisation);
    }
);

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
    $orgStock->updateQuietly(['packed_in' => 4321]);
    HydrateOrgStock::run($orgStock);

    expect($orgStock->fresh()->packed_in)->toBe(4321);

    $this->artisan('repair:org-stocks-packed-in');
    expect($orgStock->fresh()->packed_in)->toBe(4321);

    $orgStock->updateQuietly(['packed_in' => null]);
    $this->artisan('repair:org-stocks-packed-in')->assertExitCode(0);
    expect($orgStock->fresh()->packed_in)->toBe(1);
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

test('delete warehouse area orphans its locations', function () {
    $warehouse = StoreWarehouse::make()->action($this->organisation, [
        'code' => 'WA-DEL',
        'name' => 'Warehouse for area delete test',
    ]);

    $area = StoreWarehouseArea::make()->action($warehouse, [
        'code' => 'AR-D',
        'name' => 'Area for delete test',
    ]);

    $location = StoreLocation::make()->action($area, array_merge(Location::factory()->definition(), ['code' => 'LO-D']));

    $areaId = $area->id;
    DeleteWarehouseArea::make()->handle($area);

    expect(WarehouseArea::find($areaId))->toBeNull()
        ->and(Location::find($location->id)->warehouse_area_id)->toBeNull();
});

test('move location between warehouse areas', function () {
    $warehouse = StoreWarehouse::make()->action($this->organisation, [
        'code' => 'WA-MOV',
        'name' => 'Warehouse for area move test',
    ]);

    $areaA = StoreWarehouseArea::make()->action($warehouse, ['code' => 'AR-MA', 'name' => 'Area A']);
    $areaB = StoreWarehouseArea::make()->action($warehouse, ['code' => 'AR-MB', 'name' => 'Area B']);

    $location = StoreLocation::make()->action($warehouse, array_merge(Location::factory()->definition(), [
        'code'              => 'LO-MOV',
        'warehouse_area_id' => $areaA->id,
    ]));

    expect($location->warehouse_area_id)->toBe($areaA->id);

    $location = UpdateLocation::make()->action($location, ['warehouse_area_id' => $areaB->id]);
    expect($location->warehouse_area_id)->toBe($areaB->id)
        ->and($location->sort_code)->toContain('-'.CreateSortCode::run('AR-MB').'-')
        ->and($areaA->refresh()->stats->number_locations)->toBe(0)
        ->and($areaB->refresh()->stats->number_locations)->toBe(1);

    $location = UpdateLocation::make()->action($location, ['warehouse_area_id' => null]);
    expect($location->warehouse_area_id)->toBeNull()
        ->and($location->sort_code)->toBe(CreateSortCode::run('LO-MOV'))
        ->and($areaB->refresh()->stats->number_locations)->toBe(0);
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

    $orgStock->update(['quantity_in_locations' => 9999, 'quantity_available' => 9999, 'sku_value' => 7, 'value_in_locations' => 0]);

    OrgStockHydrateQuantityInLocations::run($orgStock->id);

    $orgStock->refresh();
    expect((float) $orgStock->quantity_in_locations)->toBe($expectedQuantity)
        ->and((float) $orgStock->quantity_available)->toBe($expectedQuantity)
        ->and((float) $orgStock->value_in_locations)->toBe($expectedQuantity * 7);

    // Guard clauses: null id and missing id return early without throwing
    OrgStockHydrateQuantityInLocations::run(null);
    OrgStockHydrateQuantityInLocations::run(999999999);
    expect((float) $orgStock->fresh()->quantity_in_locations)->toBe($expectedQuantity);
});

test('stock parked in a goods out location stops being available', function () {
    $warehouse = createWarehouse();
    $location  = StoreLocation::make()->action($warehouse, Location::factory()->definition());
    $orgStock  = createOrgStocks($this->organisation, [createStocks($this->group)[0]])[0];
    $slot      = StoreLocationOrgStock::make()->action($orgStock, $location, ['type' => LocationStockTypeEnum::PICKING]);
    UpdateLocationOrgStock::make()->action($slot, ['quantity' => 10]);
    $slot->refresh();

    OrgStockHydrateQuantityInLocations::run($orgStock->id);
    $inLocations = (float) $orgStock->fresh()->quantity_in_locations;
    expect((float) $orgStock->fresh()->quantity_available)->toBe($inLocations);

    UpdateLocation::make()->action($slot->location, ['is_goods_out' => true]);

    $orgStock->refresh();
    expect((float) $orgStock->quantity_in_locations)->toBe($inLocations)
        ->and((float) $orgStock->quantity_available)->toBe($inLocations - 10);

    UpdateLocation::make()->action($slot->location->refresh(), ['is_goods_out' => false]);
    expect((float) $orgStock->fresh()->quantity_available)->toBe($inLocations);
});

test('stock location integrity monitor reports a location that no longer matches its movements', function () {
    \Illuminate\Support\Facades\Http::fake();
    config(['services.discord.webhook_url' => 'https://discord.test/webhook']);
    $wasAikuStockControl = $this->organisation->is_aiku_stock_control;
    $this->organisation->update(['is_aiku_stock_control' => true]);

    $warehouse = createWarehouse();
    $location  = StoreLocation::make()->action($warehouse, Location::factory()->definition());
    $orgStock  = createOrgStocks($this->organisation, [createStocks($this->group)[0]])[0];
    $slot      = StoreLocationOrgStock::make()->action($orgStock, $location, ['type' => LocationStockTypeEnum::PICKING]);

    AuditLocationOrgStock::run($slot, ['quantity' => 10]);
    StoreOrgStockMovement::make()->action($orgStock, $location, ['quantity' => -3, 'type' => OrgStockMovementTypeEnum::PICKED]);
    expect((float)$slot->refresh()->quantity)->toBe(7.0);

    $slot->timestamps = false;
    $slot->forceFill(['updated_at' => now()->subHour()])->save();

    $label = fn () => collect(\App\Actions\DevOps\MonitorStockLocationIntegrity::run(2))
        ->first(fn (string $issue) => str_contains($issue, "$orgStock->code @ $location->code"));

    expect($label())->toBeNull();

    DB::table('location_org_stocks')->where('id', $slot->id)->update(['quantity' => 5]);

    expect($label())->toContain('location 5, movements 7');
    \Illuminate\Support\Facades\Http::assertSent(fn ($request) => str_contains($request['content'], 'Stock leaking from locations'));

    $repair         = \App\Actions\Maintenance\Inventory\LocationOrgStock\RepairLocationOrgStockQuantityFromMovements::make();
    $movementsCount = $orgStock->orgStockMovements()->count();
    expect($repair->handle($slot->id, false))->toBeNull();

    DB::table('org_stock_movements')->where('org_stock_id', $orgStock->id)->where('location_id', $location->id)->update(['created_at' => now()->subHour()]);

    expect($repair->handle($slot->id, false))->toBe([5.0, 7.0])
        ->and((float)$slot->refresh()->quantity)->toBe(5.0)
        ->and($repair->handle($slot->id, true))->toBe([5.0, 7.0])
        ->and((float)$slot->refresh()->quantity)->toBe(7.0)
        ->and($repair->handle($slot->id, true))->toBeNull()
        ->and($orgStock->orgStockMovements()->count())->toBe($movementsCount);

    $slot->timestamps = false;
    $slot->forceFill(['updated_at' => now()->subHour()])->save();
    expect($label())->toBeNull();

    $this->organisation->update(['is_aiku_stock_control' => $wasAikuStockControl]);
});

test('an aurora receipt brings the location to its movements without losing a pick or its own open transaction', function () {
    $warehouse = createWarehouse();
    $location  = StoreLocation::make()->action($warehouse, Location::factory()->definition());
    $orgStock  = createOrgStocks($this->organisation, [createStocks($this->group)[0]])[0];
    $slot      = StoreLocationOrgStock::make()->action($orgStock, $location, ['type' => LocationStockTypeEnum::PICKING]);

    AuditLocationOrgStock::run($slot, ['quantity' => 11]);
    StoreOrgStockMovement::make()->action($orgStock, $location, ['quantity' => -1, 'type' => OrgStockMovementTypeEnum::PICKED]);

    DB::transaction(function () use ($orgStock, $location) {
        StoreOrgStockMovement::make()->action($orgStock, $location, ['quantity' => 80, 'type' => OrgStockMovementTypeEnum::PURCHASE], strict: false);
    });
    expect((float)$slot->refresh()->quantity)->toBe(90.0);

    StoreOrgStockMovement::make()->action($orgStock, $location, [
        'quantity' => 160,
        'type'     => OrgStockMovementTypeEnum::PURCHASE,
        'date'     => now()->subDays(3),
    ], strict: false);
    expect((float)$slot->refresh()->quantity)->toBe(90.0);

    DB::table('location_org_stocks')->where('id', $slot->id)->update(['quantity' => 85]);
    expect(\App\Actions\Inventory\LocationOrgStock\SyncLocationOrgStockQuantityFromMovements::run($slot))->toBe(90.0)
        ->and((float)$slot->refresh()->quantity)->toBe(90.0);
});

test('an emptied slot stays listed on a shelf but not in a goods out bay', function () {
    $warehouse = createWarehouse();
    $location  = StoreLocation::make()->action($warehouse, Location::factory()->definition());
    $orgStock  = createOrgStocks($this->organisation, [createStocks($this->group)[0]])[0];
    $slot      = StoreLocationOrgStock::make()->action($orgStock, $location, ['type' => LocationStockTypeEnum::PICKING]);
    UpdateLocationOrgStock::make()->action($slot, ['quantity' => 0]);

    request()->setRouteResolver(fn () => new Route('GET', 'test', []));

    expect(IndexOrgStocksInLocation::make()->handle($location->refresh())->total())->toBe(1);

    UpdateLocation::make()->action($location->refresh(), ['is_goods_out' => true]);
    expect(IndexOrgStocksInLocation::make()->handle($location->refresh())->total())->toBe(0);

    UpdateLocationOrgStock::make()->action($slot->refresh(), ['quantity' => 3]);
    expect(IndexOrgStocksInLocation::make()->handle($location->refresh())->total())->toBe(1);
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

test('wac per sku calculation', function () {
    $stock    = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]));
    $orgStock = StoreOrgStock::make()->action($this->organisation, $stock);

    $warehouse = StoreWarehouse::make()->action($this->organisation, ['code' => 'WAC-WH', 'name' => 'Wac WH']);
    $area      = StoreWarehouseArea::make()->action($warehouse, ['code' => 'WAC-AR', 'name' => 'Wac Area']);
    $location  = StoreLocation::make()->action($area, array_merge(Location::factory()->definition(), ['code' => 'WAC-LOC']));

    StoreLocationOrgStock::make()->action($orgStock, $location, ['type' => LocationStockTypeEnum::STORING]);

    $calculator = \App\Actions\Inventory\OrgStock\Stock\CalculateOrgStockCurrentStockHistories::make();

    expect($calculator->getWacPerSku($orgStock, now()))->toBeNull();

    $this->organisation->update(['wac_calculations_start_date' => now()->subYear()->toDateString()]);
    $orgStock->unsetRelation('organisation');

    $firstPurchase = StoreOrgStockMovement::make()->action($orgStock, $location, [
        'type'     => OrgStockMovementTypeEnum::PURCHASE->value,
        'quantity' => 10,
    ]);
    $firstPurchase->update(['cost_per_sku' => 2, 'date' => now()->subDays(3)]);

    expect((float) $calculator->getWacPerSku($orgStock, now()))->toBe(2.0);

    $picked = StoreOrgStockMovement::make()->action($orgStock, $location, [
        'type'     => OrgStockMovementTypeEnum::PICKED->value,
        'quantity' => -5,
    ]);
    $picked->update(['date' => now()->subDays(2)]);

    $secondPurchase = StoreOrgStockMovement::make()->action($orgStock, $location, [
        'type'     => OrgStockMovementTypeEnum::PURCHASE->value,
        'quantity' => 5,
    ]);
    $secondPurchase->update(['cost_per_sku' => 4, 'date' => now()->subDay()]);

    expect((float) $calculator->getWacPerSku($orgStock, now()))->toBe(3.0)
        ->and($calculator->getWacPerSku($orgStock, now()->subYears(2)))->toBeNull();

    expect((float) $calculator->getFifoPerSku($orgStock, now()))->toBe(3.0)
        ->and($calculator->getFifoPerSku($orgStock, now()->subYears(2)))->toBeNull();

    $thirdPicked = StoreOrgStockMovement::make()->action($orgStock, $location, [
        'type'     => OrgStockMovementTypeEnum::PICKED->value,
        'quantity' => -7,
    ]);
    $thirdPicked->update(['date' => now()->subHours(2)]);

    expect((float) $calculator->getFifoPerSku($orgStock, now()))->toBe(4.0)
        ->and((float) $calculator->getWacPerSku($orgStock, now()))->toBe(3.0);

    expect($calculator->getValuationPerSku($orgStock, now()))->toBe(['wac' => 3.0, 'fifo' => 4.0]);
});

test('lpp per sku is stored on the org stock and holds the replacement cost while sku value blends the old layers', function () {
    $stock    = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]));
    $orgStock = StoreOrgStock::make()->action($this->organisation, $stock);

    $warehouse = StoreWarehouse::make()->action($this->organisation, ['code' => 'LPP-WH', 'name' => 'Lpp WH']);
    $area      = StoreWarehouseArea::make()->action($warehouse, ['code' => 'LPP-AR', 'name' => 'Lpp Area']);
    $location  = StoreLocation::make()->action($area, array_merge(Location::factory()->definition(), ['code' => 'LPP-LOC']));

    StoreLocationOrgStock::make()->action($orgStock, $location, ['type' => LocationStockTypeEnum::STORING]);

    $this->organisation->update(['wac_calculations_start_date' => now()->subYear()->toDateString()]);
    $orgStock->unsetRelation('organisation');

    $oldPurchase = StoreOrgStockMovement::make()->action($orgStock, $location, [
        'type'     => OrgStockMovementTypeEnum::PURCHASE->value,
        'quantity' => 10,
    ]);
    $oldPurchase->update(['cost_per_sku' => 7, 'date' => now()->subDays(3)]);

    $newPurchase = StoreOrgStockMovement::make()->action($orgStock, $location, [
        'type'     => OrgStockMovementTypeEnum::PURCHASE->value,
        'quantity' => 10,
    ]);
    $newPurchase->update(['cost_per_sku' => 5.472667, 'date' => now()->subDay()]);

    \App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateSkuValue::run($orgStock);

    $orgStock->refresh();

    expect((float) $orgStock->lpp_per_sku)->toBe(5.472667)
        ->and(round((float) $orgStock->sku_value, 2))->toBe(6.24)
        ->and((float) $orgStock->lpp_per_sku)->toBeLessThan((float) $orgStock->sku_value);
});

test('movement org amount uses the per sku cost, not the total stock value', function () {
    $stock    = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]));
    $orgStock = StoreOrgStock::make()->action($this->organisation, $stock);

    $warehouse = StoreWarehouse::make()->action($this->organisation, ['code' => 'COGS-WH', 'name' => 'Cogs WH']);
    $area      = StoreWarehouseArea::make()->action($warehouse, ['code' => 'COGS-AR', 'name' => 'Cogs Area']);
    $location  = StoreLocation::make()->action($area, array_merge(Location::factory()->definition(), ['code' => 'COGS-LOC']));

    StoreLocationOrgStock::make()->action($orgStock, $location, ['type' => LocationStockTypeEnum::STORING]);

    $this->organisation->update(['wac_calculations_start_date' => now()->subYear()->toDateString()]);
    $orgStock->unsetRelation('organisation');

    $purchase = StoreOrgStockMovement::make()->action($orgStock, $location, [
        'type'     => OrgStockMovementTypeEnum::PURCHASE->value,
        'quantity' => 100,
    ]);
    $purchase->update(['cost_per_sku' => 2, 'date' => now()->subDays(3)]);

    $orgStock->update(['sku_value' => 2, 'quantity_in_locations' => 100, 'value_in_locations' => 200]);
    $orgStock->refresh();

    $picked = StoreOrgStockMovement::make()->action($orgStock, $location, [
        'type'     => OrgStockMovementTypeEnum::PICKED->value,
        'quantity' => -5,
    ]);

    expect((float) $picked->org_amount)->toBe(-10.0);
});

test('repair picked org stock movement cost zeroes corrupt values', function () {
    $stock    = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]));
    $orgStock = StoreOrgStock::make()->action($this->organisation, $stock);

    $warehouse = StoreWarehouse::make()->action($this->organisation, ['code' => 'RPC-WH', 'name' => 'Rpc WH']);
    $area      = StoreWarehouseArea::make()->action($warehouse, ['code' => 'RPC-AR', 'name' => 'Rpc Area']);
    $location  = StoreLocation::make()->action($area, array_merge(Location::factory()->definition(), ['code' => 'RPC-LOC']));

    StoreLocationOrgStock::make()->action($orgStock, $location, ['type' => LocationStockTypeEnum::STORING]);

    $corrupt = StoreOrgStockMovement::make()->action($orgStock, $location, [
        'type'     => OrgStockMovementTypeEnum::PICKED->value,
        'quantity' => -5,
    ]);
    DB::table('org_stock_movements')->where('id', $corrupt->id)->update([
        'date'       => '2026-07-15',
        'org_amount' => -1000,
        'grp_amount' => -2000,
    ]);

    $untouched = StoreOrgStockMovement::make()->action($orgStock, $location, [
        'type'     => OrgStockMovementTypeEnum::PURCHASE->value,
        'quantity' => 5,
    ]);
    DB::table('org_stock_movements')->where('id', $untouched->id)->update([
        'date'       => '2026-07-15',
        'org_amount' => 100,
    ]);

    $result = RepairPickedOrgStockMovementCost::make()->handle('2026-07-01', 2000, false);

    expect($result['zeroed'])->toBeGreaterThanOrEqual(1)
        ->and((float) $corrupt->refresh()->org_amount)->toBe(0.0)
        ->and((float) $corrupt->grp_amount)->toBe(0.0)
        ->and((float) $untouched->refresh()->org_amount)->toBe(100.0);
});

test('backfill picked org stock movement cost from fifo walk', function () {
    $stock    = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]));
    $orgStock = StoreOrgStock::make()->action($this->organisation, $stock);

    $warehouse = StoreWarehouse::make()->action($this->organisation, ['code' => 'BF-WH', 'name' => 'Bf WH']);
    $area      = StoreWarehouseArea::make()->action($warehouse, ['code' => 'BF-AR', 'name' => 'Bf Area']);
    $location  = StoreLocation::make()->action($area, array_merge(Location::factory()->definition(), ['code' => 'BF-LOC']));

    StoreLocationOrgStock::make()->action($orgStock, $location, ['type' => LocationStockTypeEnum::STORING]);

    $this->organisation->update(['wac_calculations_start_date' => '2025-01-01']);
    $orgStock->unsetRelation('organisation');

    $purchase = StoreOrgStockMovement::make()->action($orgStock, $location, [
        'type'     => OrgStockMovementTypeEnum::PURCHASE->value,
        'quantity' => 10,
    ]);
    DB::table('org_stock_movements')->where('id', $purchase->id)->update(['cost_per_sku' => 3, 'date' => '2025-02-01']);

    $historicPick = StoreOrgStockMovement::make()->action($orgStock, $location, [
        'type'     => OrgStockMovementTypeEnum::PICKED->value,
        'quantity' => -4,
    ]);
    DB::table('org_stock_movements')->where('id', $historicPick->id)->update(['org_amount' => 0, 'grp_amount' => 0, 'date' => '2025-03-01']);

    $result = \App\Actions\Maintenance\Inventory\OrgStockMovement\BackfillPickedOrgStockMovementCost::make()
        ->handle($orgStock, '2026-07-01', false);

    expect($result['filled'])->toBe(1)
        ->and($result['skipped_no_cost'])->toBe(0)
        ->and((float) $historicPick->refresh()->org_amount)->toBe(-12.0);
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

test('erase org stock history archives the movements before deleting them and leaves other stock alone', function () {
    [$orgStock, $location] = costFixStockInLocation($this->group, $this->organisation, 'ERASE-'.Str::random(4));
    [$otherOrgStock]       = costFixStockInLocation($this->group, $this->organisation, 'KEEP-'.Str::random(4));

    $found = StoreOrgStockMovement::make()->action($orgStock, $location, [
        'type'       => OrgStockMovementTypeEnum::FOUND,
        'quantity'   => 1000,
        'org_amount' => 50000,
    ], strict: false);
    StoreOrgStockMovement::make()->action($orgStock, $location, ['type' => OrgStockMovementTypeEnum::PICKED, 'quantity' => -3, 'org_amount' => 0], strict: false);
    $kept = StoreOrgStockMovement::make()->action($otherOrgStock, $location, ['type' => OrgStockMovementTypeEnum::FOUND, 'quantity' => 5, 'org_amount' => 10], strict: false);

    $erase    = EraseOrgStockHistory::make();
    $existing = OrgStockMovement::where('org_stock_id', $orgStock->id)->count();

    expect($existing)->toBeGreaterThanOrEqual(2)
        ->and($erase->handle($orgStock, true)['org_stock_movements'])->toBe($existing)
        ->and(OrgStockMovement::where('org_stock_id', $orgStock->id)->count())->toBe($existing);

    $erase->handle($orgStock, false);

    expect(OrgStockMovement::where('org_stock_id', $orgStock->id)->count())->toBe(0)
        ->and(DB::table('help4274_erased_org_stock_movements')->where('org_stock_id', $orgStock->id)->count())->toBe($existing)
        ->and((float)DB::table('help4274_erased_org_stock_movements')->where('id', $found->id)->value('org_amount'))->toBe(50000.0)
        ->and(OrgStockMovement::whereKey($kept->id)->exists())->toBeTrue()
        ->and((float)LocationOrgStock::where('org_stock_id', $orgStock->id)->value('quantity'))->toBe(0.0);
});

test('zero org stock value keeps quantities, backs up and clears amounts and leaves other stock alone', function () {
    [$orgStock, $location] = costFixStockInLocation($this->group, $this->organisation, 'ZERO-'.Str::random(4));
    [$otherOrgStock]       = costFixStockInLocation($this->group, $this->organisation, 'ZKEEP-'.Str::random(4));

    $found = StoreOrgStockMovement::make()->action($orgStock, $location, ['type' => OrgStockMovementTypeEnum::FOUND, 'quantity' => 100, 'org_amount' => 328], strict: false);
    $kept  = StoreOrgStockMovement::make()->action($otherOrgStock, $location, ['type' => OrgStockMovementTypeEnum::FOUND, 'quantity' => 5, 'org_amount' => 10], strict: false);

    $zero = ZeroOrgStockValue::make();

    expect($zero->handle($orgStock, true)['total_org_amount'])->toBe(328.0)
        ->and((float)$found->refresh()->org_amount)->toBe(328.0);

    $zero->handle($orgStock, false);

    expect((float)$found->refresh()->org_amount)->toBe(0.0)
        ->and((float)$found->quantity)->toBe(100.0)
        ->and((float)DB::table('help4274_zeroed_org_stock_movements')->where('id', $found->id)->value('org_amount'))->toBe(328.0)
        ->and((float)$kept->refresh()->org_amount)->toBe(10.0)
        ->and($zero->handle($orgStock, false)['movements_with_value'])->toBe(0)
        ->and(DB::table('help4274_zeroed_org_stock_movements')->where('id', $found->id)->count())->toBe(1);
});

test('recost aurora stock counts prices only far-off aurora counts at the last purchase cost, backing them up first', function () {
    [$orgStock, $location] = costFixStockInLocation($this->group, $this->organisation, 'RCST-'.Str::random(4));

    $movement = fn (string $type, float $quantity, float $amount, string $date, ?string $sourceId, ?float $costPerSku = null) => tap(
        StoreOrgStockMovement::make()->action($orgStock, $location, ['type' => $type, 'quantity' => $quantity, 'org_amount' => $amount], strict: false),
        fn ($stored) => DB::table('org_stock_movements')->where('id', $stored->id)->update(['date' => $date, 'source_id' => $sourceId, 'cost_per_sku' => $costPerSku])
    );

    $movement(OrgStockMovementTypeEnum::PURCHASE->value, 100, 50, '2025-01-01', null, 0.5);
    $movement(OrgStockMovementTypeEnum::PURCHASE->value, 100, 60, '2025-01-05', null, 0.5);
    $crazy      = $movement(OrgStockMovementTypeEnum::WRITE_OFF->value, -10, -2940, '2025-02-01', '1:rcst1');
    $fine       = $movement(OrgStockMovementTypeEnum::FOUND->value, 10, 6, '2025-02-02', '1:rcst2');
    $madeInAiku = $movement(OrgStockMovementTypeEnum::FOUND->value, 10, 9000, '2025-02-03', null);

    [$boxPricedOrgStock, $boxLocation] = costFixStockInLocation($this->group, $this->organisation, 'RCSB-'.Str::random(4));
    foreach ([[0.57, '2025-01-01'], [220.25, '2025-01-05']] as [$cost, $date]) {
        $purchase = StoreOrgStockMovement::make()->action($boxPricedOrgStock, $boxLocation, ['type' => OrgStockMovementTypeEnum::PURCHASE->value, 'quantity' => 10, 'org_amount' => $cost * 10], strict: false);
        DB::table('org_stock_movements')->where('id', $purchase->id)->update(['date' => $date, 'cost_per_sku' => $cost]);
    }
    $untrusted = StoreOrgStockMovement::make()->action($boxPricedOrgStock, $boxLocation, ['type' => OrgStockMovementTypeEnum::WRITE_OFF->value, 'quantity' => -5, 'org_amount' => -9000], strict: false);
    DB::table('org_stock_movements')->where('id', $untrusted->id)->update(['date' => '2025-02-01', 'source_id' => '1:rcst3']);

    $recost = RecostAuroraStockCountMovements::make();
    $ours   = fn () => $recost->mispricedCounts($this->organisation, '2025-01-01')->where('org_stock_id', $orgStock->id);

    expect($ours()->pluck('id')->all())->toBe([$crazy->id]);

    $recost->handle($this->organisation, '2025-01-01', true);
    expect((float)$crazy->refresh()->org_amount)->toBe(-2940.0);

    $recost->handle($this->organisation, '2025-01-01', false);

    expect((float)$crazy->refresh()->org_amount)->toBe(-5.0)
        ->and((float)$crazy->quantity)->toBe(-10.0)
        ->and((float)$crazy->cost_per_sku)->toBe(0.5)
        ->and((float)DB::table('help4274_recosted_org_stock_movements')->where('id', $crazy->id)->value('org_amount'))->toBe(-2940.0)
        ->and((float)$fine->refresh()->org_amount)->toBe(6.0)
        ->and((float)$madeInAiku->refresh()->org_amount)->toBe(9000.0)
        ->and((float)$untrusted->refresh()->org_amount)->toBe(-9000.0)
        ->and($ours())->toBeEmpty();
});

test('reprice org stock counts prices counts from a date at the given cost and leaves purchases, picks and earlier counts alone', function () {
    [$orgStock, $location] = costFixStockInLocation($this->group, $this->organisation, 'RPRC-'.Str::random(4));

    $movement = fn (string $type, float $quantity, float $amount, string $date) => tap(
        StoreOrgStockMovement::make()->action($orgStock, $location, ['type' => $type, 'quantity' => $quantity, 'org_amount' => $amount], strict: false),
        fn ($stored) => DB::table('org_stock_movements')->where('id', $stored->id)->update(['date' => $date])
    );

    $purchase = $movement(OrgStockMovementTypeEnum::PURCHASE->value, 10, 125.8, '2025-07-01');
    $earlier  = $movement(OrgStockMovementTypeEnum::FOUND->value, 5, 62.9, '2025-07-10');
    $found    = $movement(OrgStockMovementTypeEnum::FOUND->value, 2500, 31450, '2025-07-17');
    $pick     = $movement(OrgStockMovementTypeEnum::PICKED->value, -1, -12.58, '2025-08-01');
    $marker   = $movement(OrgStockMovementTypeEnum::AUDIT->value, 2500, 0, '2025-07-17');
    $recount  = $movement(OrgStockMovementTypeEnum::AUDIT->value, -2450, -30821, '2026-10-30');

    $reprice = RepriceOrgStockCounts::make();

    expect($reprice->handle($orgStock, '2025-07-17', 0.2516, true)['movements'])->toBe(2)
        ->and((float)$found->refresh()->org_amount)->toBe(31450.0);

    $reprice->handle($orgStock, '2025-07-17', 0.2516, false);

    expect((float)$found->refresh()->org_amount)->toBe(629.0)
        ->and((float)$found->quantity)->toBe(2500.0)
        ->and((float)$recount->refresh()->org_amount)->toBe(-616.42)
        ->and((float)DB::table('help4274_repriced_org_stock_movements')->where('id', $found->id)->value('org_amount'))->toBe(31450.0)
        ->and((float)$purchase->refresh()->org_amount)->toBe(125.8)
        ->and((float)$pick->refresh()->org_amount)->toBe(-12.58)
        ->and((float)$earlier->refresh()->org_amount)->toBe(62.9)
        ->and((float)$marker->refresh()->org_amount)->toBe(0.0);
});

test('recost aurora counts with picks prices only aurora picks priced too high and leaves counts alone', function () {
    [$orgStock, $location] = costFixStockInLocation($this->group, $this->organisation, 'RCSP-'.Str::random(4));

    $movement = fn (string $type, float $quantity, float $amount, string $date, ?string $sourceId, ?float $costPerSku = null) => tap(
        StoreOrgStockMovement::make()->action($orgStock, $location, ['type' => $type, 'quantity' => $quantity, 'org_amount' => $amount], strict: false),
        fn ($stored) => DB::table('org_stock_movements')->where('id', $stored->id)->update(['date' => $date, 'source_id' => $sourceId, 'cost_per_sku' => $costPerSku])
    );

    $movement(OrgStockMovementTypeEnum::PURCHASE->value, 1000, 50, '2025-01-01', null, 0.05);
    $spiked    = $movement(OrgStockMovementTypeEnum::PICKED->value, -140, -79758, '2026-07-31', '1:rcsp1');
    $cheap     = $movement(OrgStockMovementTypeEnum::PICKED->value, -10, -0.1, '2026-08-01', '1:rcsp2');
    $wildCount = $movement(OrgStockMovementTypeEnum::WRITE_OFF->value, -5, -900, '2026-08-02', '1:rcsp3');

    $recost = RecostAuroraStockCountMovements::make();

    expect($recost->mispricedCounts($this->organisation, '2025-01-01')->where('org_stock_id', $orgStock->id)->pluck('id')->all())->toBe([$wildCount->id])
        ->and($recost->mispricedCounts($this->organisation, '2025-01-01', true)->where('org_stock_id', $orgStock->id)->pluck('id')->all())->toBe([$spiked->id])
        ->and($recost->mispricedCounts($this->organisation, '2025-01-01', true, ['NOT-'.$orgStock->code])->where('org_stock_id', $orgStock->id))->toBeEmpty()
        ->and($recost->mispricedCounts($this->organisation, '2025-01-01', true, [$orgStock->code])->pluck('id')->all())->toBe([$spiked->id]);

    $recost->handle($this->organisation, '2025-01-01', false, true, [$orgStock->code]);

    expect((float)$spiked->refresh()->org_amount)->toBe(-7.0)
        ->and((float)$spiked->quantity)->toBe(-140.0)
        ->and((float)DB::table('help4274_recosted_org_stock_movements')->where('id', $spiked->id)->value('org_amount'))->toBe(-79758.0)
        ->and((float)$cheap->refresh()->org_amount)->toBe(-0.1)
        ->and((float)$wildCount->refresh()->org_amount)->toBe(-900.0);
});

test('erase org stock history with retire unlinks the charge from its products and discontinues it', function () {
    [, , $shop]   = createShop();
    [, $product]  = createProduct($shop);
    [$orgStock, $location] = costFixStockInLocation($shop->group, $shop->organisation, 'RTR-'.Str::random(4));

    $product->orgStocks()->attach($orgStock->id, ['quantity' => 1]);
    StoreOrgStockMovement::make()->action($orgStock, $location, ['type' => OrgStockMovementTypeEnum::FOUND, 'quantity' => 3, 'org_amount' => 300], strict: false);

    $erase = EraseOrgStockHistory::make();

    $dryRun = $erase->handle($orgStock, true, true);
    expect($dryRun['products_unlinked'])->toBe(1)
        ->and($dryRun['discontinued'])->toBe(1)
        ->and($orgStock->products()->count())->toBe(1);

    $erase->handle($orgStock, false, true);
    $orgStock->refresh();

    expect($orgStock->products()->count())->toBe(0)
        ->and($orgStock->state)->toBe(OrgStockStateEnum::DISCONTINUED)
        ->and(OrgStock::whereKey($orgStock->id)->exists())->toBeTrue()
        ->and(OrgStockMovement::where('org_stock_id', $orgStock->id)->count())->toBe(0)
        ->and($product->refresh()->exists)->toBeTrue();
});
