<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Dec 2024 15:01:28 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Feature;

use App\Actions\Goods\Stock\StoreStock;
use App\Actions\Inventory\Location\HydrateLocation;
use App\Actions\Inventory\Location\StoreLocation;
use App\Actions\Inventory\LocationOrgStock\AuditLocationOrgStock;
use App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock;
use App\Actions\Inventory\LocationOrgStock\UpdateLocationOrgStock;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateQuantityInLocations;
use App\Actions\Inventory\OrgStock\StoreOrgStock;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydrateOrgStocksBelowMinimumStock;
use App\Actions\Inventory\WarehouseArea\HydrateWarehouseArea;
use App\Actions\SysAdmin\GetSectionRoute;
use App\Enums\Analytics\AikuSection\AikuSectionEnum;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Enums\Inventory\LocationStock\LocationStockTypeEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\UI\Inventory\LocationTabsEnum;
use App\Models\Analytics\AikuScopedSection;
use App\Models\Goods\Stock;
use App\Models\Inventory\Location;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseArea;
use App\Notifications\OrgStocksBelowMinimumStockNotification;
use Config;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;

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

test("UI show org stock navigation follows the bucket and sort", function () {
    $warehouse = $this->organisation->warehouses->first() ?? createWarehouse();
    $this->withoutExceptionHandling();

    $makeOrgStock = function (string $code) {
        $stock = StoreStock::make()->action(
            $this->group,
            array_merge(Stock::factory()->definition(), ['code' => $code, 'state' => StockStateEnum::ACTIVE])
        );

        $orgStock = StoreOrgStock::make()->action($this->organisation, $stock);
        $orgStock->update(['state' => OrgStockStateEnum::ACTIVE]);

        return $orgStock->refresh();
    };

    $first  = $makeOrgStock('NAVSTOCKA');
    $middle = $makeOrgStock('NAVSTOCKB');
    $last   = $makeOrgStock('NAVSTOCKC');

    $showRoute = fn ($orgStock) => route("grp.org.warehouses.show.inventory.org_stocks.active_org_stocks.show", [
        $this->organisation->slug,
        $warehouse->slug,
        $orgStock->slug
    ]);

    get($showRoute($middle))->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('navigation.previous.label', $first->name)
            ->where('navigation.next.label', $last->name)
            ->etc()
    );

    get($showRoute($middle).'?bucket_sort=-code')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('navigation.previous.label', $last->name)
            ->where('navigation.next.label', $first->name)
            ->etc()
    );
});

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
                fn (AssertableInertia $page) => $page->where("title", 'SKOs')->etc()
            );
    });
});

test("export org stocks lists only this organisation's skos", function () {
    $export = new \App\Exports\Inventory\OrgStocksExport($this->organisation);
    $rows   = $export->query()->get();

    expect($rows)->not->toBeEmpty()
        ->and($rows->pluck('organisation_id')->unique()->all())->toBe([$this->organisation->id])
        ->and(count($export->map($rows->first())))->toBe(count($export->headings()));
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
                fn (AssertableInertia $page) => $page->where("title", 'SKOs')->etc()
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
                fn (AssertableInertia $page) => $page->where("title", 'SKOs')->etc()
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

test("UI Index warehouse areas only lets location editors change the picking order", function () {
    $warehouse = Warehouse::first();
    $route     = route("grp.org.warehouses.show.infrastructure.warehouse_areas.index", [
        $this->organisation->slug,
        $warehouse->slug
    ]);

    $user = \App\Actions\SysAdmin\Guest\StoreGuest::make()->action(
        $this->group,
        array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => []])
    )->getUser();
    $user->givePermissionTo("locations.{$warehouse->id}.view");
    actingAs($user->fresh());

    get($route)->assertInertia(fn (AssertableInertia $page) => $page->where("canEditPickingPosition", false));

    $user->givePermissionTo("locations.{$warehouse->id}.edit");
    actingAs($user->fresh());

    get($route)->assertInertia(fn (AssertableInertia $page) => $page->where("canEditPickingPosition", true));
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
                fn (AssertableInertia $page) => $page->where("title", 'Current SKOs')->etc()
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
                fn (AssertableInertia $page) => $page->where("title", 'SKO Families')->etc()
            );
    });
});

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
                fn (AssertableInertia $page) => $page->where("title", 'Master SKO Families')->etc()
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
                fn (AssertableInertia $page) => $page->where("title", "New SKO family")->etc()
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
                fn (AssertableInertia $page) => $page->where("title", "Customer's SKOs")->etc()
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

test('UI show incoming hub', function () {
    $warehouse = Warehouse::first();
    $this->withoutExceptionHandling();
    $response = get(
        route('grp.org.warehouses.show.incoming.backlog', [
            $this->organisation->slug,
            $warehouse->slug
        ])
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Incoming/IncomingHub')
            ->has('dashboard.dimension.items', 5)
            ->where('dashboard.dimension.items.1.key', 'partner_stock_deliveries')
            ->where('dashboard.dimension.items.2.key', 'agent_stock_deliveries')
            ->where('dashboard.metrics.0.key', 'arriving')
            ->has('dashboard.data.agent_stock_deliveries.booked_in');
    });
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

test('UI Create warehouse', function () {
    $this->withoutExceptionHandling();
    get(route('grp.org.warehouses.create', [$this->organisation->slug]))
        ->assertInertia(function (AssertableInertia $page) {
            $page->component('CreateModel')->has('formData');
        });
});

test('UI low stock audits sorts locations ascending and descending', function () {
    $warehouse = createWarehouse();
    $stock     = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]));
    $orgStock  = StoreOrgStock::make()->action($this->organisation, $stock);

    foreach (['LSA-A1', 'LSA-Z9'] as $locationCode) {
        $location         = StoreLocation::make()->action($warehouse, array_merge(Location::factory()->definition(), ['code' => $locationCode]));
        $locationOrgStock = StoreLocationOrgStock::make()->action($orgStock, $location, []);
        UpdateLocationOrgStock::make()->action($locationOrgStock, ['quantity' => 1]);
    }
    OrgStockHydrateQuantityInLocations::run($orgStock->id);

    $this->withoutExceptionHandling();

    $locationCodesFor = function (string $sort) use ($warehouse, $orgStock) {
        $response = get(route('grp.org.warehouses.show.inventory.org_stocks.low_stock_audits.index', [
            $this->organisation->slug,
            $warehouse->slug,
            'low_stock_audits_sort' => $sort,
        ]));

        $row = collect($response->viewData('page')['props']['lowStockAudits']['data'])->firstWhere('id', $orgStock->id);

        return collect($row['locations'])->pluck('code')->all();
    };

    expect($locationCodesFor('locations'))->toBe(['LSA-A1', 'LSA-Z9'])
        ->and($locationCodesFor('-locations'))->toBe(['LSA-Z9', 'LSA-A1']);
});

test('UI low stock audits keeps a SKO listed until its empty location is counted too', function () {
    $warehouse = createWarehouse();
    $stock     = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]));
    $orgStock  = StoreOrgStock::make()->action($this->organisation, $stock);

    $withStock = StoreLocationOrgStock::make()->action($orgStock, StoreLocation::make()->action($warehouse, array_merge(Location::factory()->definition(), ['code' => 'LSA-FULL'])), []);
    UpdateLocationOrgStock::make()->action($withStock, ['quantity' => 2]);
    $empty = StoreLocationOrgStock::make()->action($orgStock, StoreLocation::make()->action($warehouse, array_merge(Location::factory()->definition(), ['code' => 'LSA-EMPTY'])), []);
    OrgStockHydrateQuantityInLocations::run($orgStock->id);

    $this->withoutExceptionHandling();

    $listedRow = function () use ($warehouse, $orgStock) {
        $response = get(route('grp.org.warehouses.show.inventory.org_stocks.low_stock_audits.index', [$this->organisation->slug, $warehouse->slug]));

        return collect($response->viewData('page')['props']['lowStockAudits']['data'])->firstWhere('id', $orgStock->id);
    };

    AuditLocationOrgStock::make()->action($withStock->refresh(), []);

    expect(collect($listedRow()['locations'])->pluck('is_low_stock_checked', 'code')->all())->toBe(['LSA-EMPTY' => false, 'LSA-FULL' => true]);

    AuditLocationOrgStock::make()->action($empty->refresh(), ['quantity' => 0]);

    expect($listedRow())->toBeNull();
});

test('the skos tab of a location filters by its search box and sorts by reference', function () {
    $warehouse = createWarehouse();
    $location  = StoreLocation::make()->action($warehouse, Location::factory()->definition());
    $orgStocks = createOrgStocks($this->organisation, array_slice(createStocks($this->group), 0, 2));

    foreach ($orgStocks as $orgStock) {
        StoreLocationOrgStock::make()->action($orgStock, $location, ['type' => LocationStockTypeEnum::PICKING]);
    }

    [$wanted, $other] = $orgStocks;
    expect($wanted->code)->not->toBe($other->code);

    $codesShown = fn (array $query): array => collect(
        $this->withoutVite()->get(route('grp.org.warehouses.show.infrastructure.locations.show', array_merge(
            [$this->organisation->slug, $warehouse->slug, $location->slug, 'tab' => 'org_stocks'],
            $query
        )))->assertOk()->viewData('page')['props']['org_stocks']['data']
    )->pluck('code')->all();

    expect($codesShown([]))->toContain($wanted->code, $other->code)
        ->and($codesShown(['org_stocks_filter' => ['global' => $wanted->code]]))->toBe([$wanted->code]);

    $ascending = $codesShown(['org_stocks_sort' => 'code']);
    expect($codesShown(['org_stocks_sort' => '-code']))->toBe(array_reverse($ascending));
});

test('a SKO whose stock falls below its minimum shows on the below minimum stock list and dashboard until restocked', function () {
    $warehouse        = createWarehouse();
    $stock            = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]));
    $orgStock         = StoreOrgStock::make()->action($this->organisation, $stock);
    $location         = StoreLocation::make()->action($warehouse, array_merge(Location::factory()->definition(), ['code' => 'BMS-A1']));
    $locationOrgStock = StoreLocationOrgStock::make()->action($orgStock, $location, []);
    UpdateLocationOrgStock::make()->action($locationOrgStock, ['quantity' => 3]);
    OrgStockHydrateQuantityInLocations::run($orgStock->id);

    $this->withoutExceptionHandling();

    $listedRow = function (string $routeName) use ($warehouse, $orgStock) {
        $response = get(route($routeName, [$this->organisation->slug, $warehouse->slug, 'minimum_stocks_filter' => ['global' => $orgStock->code]]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Org/Inventory/OrgStocksBelowMinimumStock'));

        return collect($response->viewData('page')['props']['orgStocks']['data'])->firstWhere('id', $orgStock->id);
    };
    $belowMinimumCount = function () use ($warehouse) {
        WarehouseHydrateOrgStocksBelowMinimumStock::run($warehouse);

        return $warehouse->stats->refresh()->number_org_stocks_below_minimum_stock;
    };

    $baseline = $belowMinimumCount();
    expect($listedRow('grp.org.warehouses.show.inventory.org_stocks.below_minimum_stock.index'))->toBeNull()
        ->and($listedRow('grp.org.warehouses.show.inventory.org_stocks.below_minimum_stock.levels'))->toMatchArray(['minimum_stock' => null, 'stock' => 3.0]);

    patch(route('grp.org.warehouses.show.inventory.org_stocks.update', [$this->organisation->slug, $warehouse->slug, $orgStock->slug]), ['minimum_stock' => 10])
        ->assertSessionHasNoErrors();

    expect((float) $orgStock->refresh()->minimum_stock)->toBe(10.0)
        ->and($belowMinimumCount())->toBe($baseline + 1)
        ->and($listedRow('grp.org.warehouses.show.inventory.org_stocks.below_minimum_stock.index'))->toMatchArray(['minimum_stock' => 10.0, 'stock' => 3.0, 'shortfall' => 7.0]);

    $card = collect(get(route('grp.org.warehouses.show.inventory.dashboard', [$this->organisation->slug, $warehouse->slug]))
        ->assertOk()
        ->inertiaProps()['statsBox'])->firstWhere('key', 'below_minimum_stock');
    expect($card['value'])->toBe($baseline + 1);

    UpdateLocationOrgStock::make()->action($locationOrgStock->refresh(), ['quantity' => 12]);
    OrgStockHydrateQuantityInLocations::run($orgStock->id);

    expect($belowMinimumCount())->toBe($baseline)
        ->and($listedRow('grp.org.warehouses.show.inventory.org_stocks.below_minimum_stock.index'))->toBeNull();
});

test('the stock supervisors are notified once when a SKO falls below its minimum, and again only after it was restocked', function () {
    Notification::fake();

    $warehouse        = createWarehouse();
    $stock            = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]));
    $orgStock         = StoreOrgStock::make()->action($this->organisation, $stock);
    $location         = StoreLocation::make()->action($warehouse, array_merge(Location::factory()->definition(), ['code' => 'BMS-N1']));
    $locationOrgStock = StoreLocationOrgStock::make()->action($orgStock, $location, []);
    UpdateLocationOrgStock::make()->action($locationOrgStock, ['quantity' => 3]);
    OrgStockHydrateQuantityInLocations::run($orgStock->id);

    $this->user->givePermissionTo("supervisor-stocks.$warehouse->id");

    $alertsAboutOrgStock = function () use ($orgStock): int {
        return Notification::sent($this->user, OrgStocksBelowMinimumStockNotification::class)
            ->filter(fn (OrgStocksBelowMinimumStockNotification $notification) => in_array($orgStock->code, $notification->codes))
            ->count();
    };

    $orgStock->update(['minimum_stock' => 10]);
    WarehouseHydrateOrgStocksBelowMinimumStock::run($warehouse);

    expect($alertsAboutOrgStock())->toBe(1)
        ->and($orgStock->refresh()->minimum_stock_alerted_at)->not->toBeNull();

    $notification = Notification::sent($this->user, OrgStocksBelowMinimumStockNotification::class)->last();
    expect($notification->toArray($this->user)['route'])->toBe([
        'name'       => 'grp.org.warehouses.show.inventory.org_stocks.below_minimum_stock.index',
        'parameters' => [$this->organisation->slug, $warehouse->slug],
    ]);

    WarehouseHydrateOrgStocksBelowMinimumStock::run($warehouse);
    expect($alertsAboutOrgStock())->toBe(1);

    UpdateLocationOrgStock::make()->action($locationOrgStock->refresh(), ['quantity' => 12]);
    OrgStockHydrateQuantityInLocations::run($orgStock->id);
    WarehouseHydrateOrgStocksBelowMinimumStock::run($warehouse);

    expect($alertsAboutOrgStock())->toBe(1)
        ->and($orgStock->refresh()->minimum_stock_alerted_at)->toBeNull();

    UpdateLocationOrgStock::make()->action($locationOrgStock->refresh(), ['quantity' => 4]);
    OrgStockHydrateQuantityInLocations::run($orgStock->id);
    WarehouseHydrateOrgStocksBelowMinimumStock::run($warehouse);

    expect($alertsAboutOrgStock())->toBe(2);

    $orgStock->update(['minimum_stock' => null]);
    WarehouseHydrateOrgStocksBelowMinimumStock::run($warehouse);

    expect($orgStock->refresh()->minimum_stock_alerted_at)->toBeNull();
});

test('a SKO minimum stock can be cleared but never negative', function () {
    $warehouse = createWarehouse();
    $stock     = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]));
    $orgStock  = StoreOrgStock::make()->action($this->organisation, $stock);
    $updateUrl = route('grp.org.warehouses.show.inventory.org_stocks.update', [$this->organisation->slug, $warehouse->slug, $orgStock->slug]);

    patch($updateUrl, ['minimum_stock' => -1])->assertSessionHasErrors('minimum_stock');
    expect($orgStock->refresh()->minimum_stock)->toBeNull();

    patch($updateUrl, ['minimum_stock' => 5])->assertSessionHasNoErrors();
    patch($updateUrl, ['minimum_stock' => null])->assertSessionHasNoErrors();
    expect($orgStock->refresh()->minimum_stock)->toBeNull();
});
