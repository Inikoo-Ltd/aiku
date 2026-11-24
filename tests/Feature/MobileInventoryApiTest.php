<?php

/*
 * Author Louis Perez
 * Created on 16-11-2026-09h-42m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Feature;

use App\Actions\Api\Group\Inventory\IndexMobileOrgStocks;
use App\Actions\Goods\StockFamily\StoreStockFamily;
use App\Actions\Goods\Stock\StoreStock;
use App\Actions\Inventory\LocationOrgStock\ConsumeLocationOrgStock;
use App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock;
use App\Actions\Inventory\Location\StoreLocation;
use App\Actions\Inventory\OrgStockFamily\Hydrators\OrgStockFamilyHydrateOrgStocks;
use App\Actions\Inventory\OrgStockFamily\StoreOrgStockFamily;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateQuantityInLocations;
use App\Actions\Inventory\OrgStock\StoreOrgStock;
use App\Actions\Inventory\UI\ShowInventoryDashboard;
use App\Actions\Inventory\Warehouse\StoreWarehouse;
use App\Actions\Procurement\GetOrganisationStockCoverBuckets;
use App\Actions\SysAdmin\Organisation\StoreOrganisation;
use App\Enums\Inventory\LocationStock\LocationStockTypeEnum;
use App\Enums\Inventory\Location\LocationStatusEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementClassEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementReasonEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\Goods\Stock;
use App\Models\Goods\StockFamily;
use App\Models\Inventory\Location;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockMovement;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

require_once __DIR__.'/../Support/MobileApiHelpers.php';
require_once __DIR__.'/../Support/MobileLocationsHelpers.php';

/**
 * @return array<string, string>
 */
function mobileInventoryHeaders(): array
{
    return ['X-Aiku-App-Key' => 'mobile-test-key'];
}

function mobileInventoryLocation(Warehouse $warehouse, string $code): Location
{
    return StoreLocation::make()->action($warehouse, ['code' => $code]);
}

function mobileInventoryLink(OrgStock $orgStock, Location $location, float $quantity, LocationStockTypeEnum $type): LocationOrgStock
{
    return StoreLocationOrgStock::make()->action(
        orgStock: $orgStock,
        location: $location,
        modelData: ['quantity' => $quantity, 'type' => $type, 'fetched_at' => now()],
        strict: false
    )->refresh();
}

function mobileInventoryOrgStock(Organisation $organisation, ?string $code = null): OrgStock
{
    $stock = StoreStock::make()->action($organisation->group, array_merge(Stock::factory()->definition(), ['code' => $code ?? 'MI'.Str::upper(Str::random(8))]));

    return StoreOrgStock::make()->action($organisation, $stock);
}

/**
 * @return array{0: OrgStock, 1: LocationOrgStock, 2: LocationOrgStock}
 */
function mobileInventorySko(Warehouse $warehouse, ?string $code = null): array
{
    $orgStock = mobileInventoryOrgStock($warehouse->organisation, $code);
    $picking  = mobileInventoryLink($orgStock, mobileInventoryLocation($warehouse, $orgStock->code.'-P'), 30, LocationStockTypeEnum::PICKING);
    $storing  = mobileInventoryLink($orgStock, mobileInventoryLocation($warehouse, $orgStock->code.'-S'), 20, LocationStockTypeEnum::STORING);

    return [$orgStock->refresh(), $picking, $storing];
}

function mobileInventoryOtherWarehouse(): Warehouse
{
    return StoreWarehouse::make()->action(createWarehouse()->organisation, Warehouse::factory()->definition());
}

/**
 * @return array{id: int, name: string|null}
 */
function mobileInventoryPerson(User $user): array
{
    return ['id' => $user->id, 'name' => $user->contact_name ?: $user->username];
}

/**
 * @return array{0: User, 1: Warehouse}
 */
function mobileInventoryViewer(Group $group): array
{
    return actingAsMobileWarehouseUser($group, RolesEnum::WAREHOUSE_VIEWER);
}

function mobileInventoryNumber(int|float|string|null $value): int|float
{
    return json_decode(json_encode((float) $value));
}

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    [$this->organisation, , $this->shop] = createShop();
    $this->group = $this->organisation->group;
});

test('mobile inventory dashboard gives the figures of the web inventory dashboard', function () {
    [, $warehouse] = mobileInventoryViewer($this->group);
    $url    = route('grp.api.mobile.warehouses.inventory_dashboard', $warehouse->id);
    $before = getJson($url, mobileInventoryHeaders())->assertOk()->json('data');

    [, $picking] = mobileInventorySko($warehouse);
    $picking->update(['quantity' => -2]);

    $organisation   = $warehouse->organisation->refresh();
    $stats          = $warehouse->refresh()->stats;
    $inventoryStats = $organisation->inventoryStats;

    $response = getJson($url, mobileInventoryHeaders())
        ->assertOk()
        ->assertJsonPath('data.currency_code', $organisation->currency->code)
        ->assertJsonPath('data.stock_value', mobileInventoryNumber($stats->stock_value))
        ->assertJsonPath('data.number_locations', (int) $stats->number_locations)
        ->assertJsonPath('data.number_empty_locations', (int) $stats->number_empty_locations)
        ->assertJsonPath('data.number_current_org_stocks', (int) $inventoryStats->number_current_org_stocks)
        ->assertJsonPath('data.number_abnormalities', (int) $inventoryStats->number_org_stocks_state_abnormality)
        ->assertJsonPath('data.low_stock_audits', (int) $stats->number_org_stocks_low_stock_audits)
        ->assertJsonPath('data.replenishments_wholesale', (int) $stats->number_org_stocks_replenishments_wholesale)
        ->assertJsonPath('data.replenishments_dropshipping', (int) $stats->number_org_stocks_replenishments_dropshipping)
        ->assertJsonPath('data.below_minimum_stock', (int) $stats->number_org_stocks_below_minimum_stock)
        ->assertJsonPath('data.number_with_minimum_stock', OrgStock::where('organisation_id', $organisation->id)->where('state', OrgStockStateEnum::ACTIVE)->whereNotNull('minimum_stock')->count())
        ->assertJsonPath('data.wrong_weights', OrgStock::where('organisation_id', $organisation->id)->where('has_wrong_weight', true)->count())
        ->assertJsonPath('data.without_products', (int) $stats->number_org_stocks_without_products);

    $utilisation = $stats->number_locations ? mobileInventoryNumber(($stats->number_locations - $stats->number_empty_locations) / $stats->number_locations) : null;

    expect($response->json('data.negative_stocks'))->toBe($before['negative_stocks'] + 1)
        ->and($response->json('data.utilisation'))->toBe($utilisation);
});

test('mobile inventory dashboard needs inventory view rights', function () {
    [, $warehouse] = mobileInventoryViewer($this->group);
    Sanctum::actingAs(createMobileAppUser($this->group), ['mobile']);

    getJson(route('grp.api.mobile.warehouses.inventory_dashboard', $warehouse->id), mobileInventoryHeaders())->assertForbidden();
});

test('mobile SKO list follows the web buckets, family, search, sort and counts with one row per SKO', function () {
    mobileInventoryOtherWarehouse();
    [, $warehouse] = mobileInventoryViewer($this->group);
    $organisation  = $warehouse->organisation;
    $prefix        = 'MIL'.Str::upper(Str::random(5));
    $stockFamily   = StoreStockFamily::make()->action($warehouse->group, StockFamily::factory()->definition());
    $family        = StoreOrgStockFamily::make()->action($organisation, $stockFamily, []);

    [$active]       = mobileInventorySko($warehouse, $prefix.'A');
    [$discontinued] = mobileInventorySko($warehouse, $prefix.'B');
    [$abnormal]     = mobileInventorySko($warehouse, $prefix.'C');
    [$suspended]    = mobileInventorySko($warehouse, $prefix.'D');
    $active->update(['state' => OrgStockStateEnum::ACTIVE, 'org_stock_family_id' => $family->id]);
    $discontinued->update(['state' => OrgStockStateEnum::DISCONTINUED, 'discontinued_in_organisation_at' => now()->subDays(3)]);
    $abnormal->update(['state' => OrgStockStateEnum::ABNORMALITY, 'discontinued_in_organisation_at' => now()->subDays(5)]);
    $suspended->update(['state' => OrgStockStateEnum::SUSPENDED]);

    expect($organisation->warehouses()->count())->toBeGreaterThan(1);

    $list  = fn (array $query = []) => getJson(route('grp.api.mobile.warehouses.org_stocks', array_merge(['warehouse' => $warehouse->id, 'q' => $prefix], $query)), mobileInventoryHeaders());
    $codes = fn (array $query = []) => collect($list($query)->assertOk()->json('data'))->pluck('code')->all();
    $all   = [$active->code, $discontinued->code, $abnormal->code, $suspended->code];

    expect($codes())->toBe([$active->code])
        ->and($codes(['bucket' => 'current']))->toBe([$active->code])
        ->and($codes(['bucket' => 'discontinued']))->toBe([$discontinued->code])
        ->and($codes(['bucket' => 'abnormality']))->toBe([$abnormal->code])
        ->and($codes(['bucket' => 'all']))->toBe($all)
        ->and($codes(['bucket' => 'all', 'sort' => '-code']))->toBe(array_reverse($all))
        ->and($codes(['bucket' => 'all', 'family_id' => $family->id]))->toBe([$active->code])
        ->and($codes(['bucket' => 'all', 'q' => $prefix.'B']))->toBe([$discontinued->code]);

    $list(['bucket' => 'all'])->assertJsonPath('meta.total', 4);

    $inventoryStats = $organisation->refresh()->inventoryStats;
    $list()
        ->assertJsonPath('currency_code', $organisation->currency->code)
        ->assertJsonPath('counts', [
            'current'       => (int) $inventoryStats->number_current_org_stocks,
            'discontinued'  => (int) $inventoryStats->number_org_stocks_state_discontinued,
            'abnormality'   => (int) $inventoryStats->number_org_stocks_state_abnormality,
            'below_minimum' => IndexMobileOrgStocks::numberBelowMinimumStock($warehouse),
            'negative'      => IndexMobileOrgStocks::numberNegativeStocks($warehouse),
            'all'           => (int) $inventoryStats->number_org_stocks,
        ])
        ->assertJsonPath('data.0.id', $active->id)
        ->assertJsonPath('data.0.slug', $active->slug)
        ->assertJsonPath('data.0.state', 'active')
        ->assertJsonPath('data.0.state_label', 'Active')
        ->assertJsonPath('data.0.family', ['id' => $family->id, 'code' => $family->code])
        ->assertJsonPath('data.0.image_url', null)
        ->assertJsonPath('data.0.quantity_in_locations', 50)
        ->assertJsonPath('data.0.quantity_in_locations_label', '50')
        ->assertJsonPath('data.0.quantity_available', mobileInventoryNumber($active->refresh()->quantity_available))
        ->assertJsonPath('data.0.quantity_available_label', trimDecimalZeros($active->quantity_available))
        ->assertJsonPath('data.0.number_locations', 2)
        ->assertJsonPath('data.0.last_seen_at', null);

    $list(['family_id' => $family->id])->assertJsonPath('counts.current', (int) $family->refresh()->stats?->number_current_org_stocks);

    $list(['bucket' => 'abnormality'])
        ->assertJsonPath('data.0.state_label', 'Abnormality')
        ->assertJsonPath('data.0.last_seen_at', $abnormal->refresh()->discontinued_in_organisation_at->toIso8601String());

    $list(['bucket' => 'nope', 'family_id' => 999999999, 'sort' => 'sku_value'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['bucket', 'family_id', 'sort']);
});

test('mobile SKO family page gives the web family SKO and quantity status and fraction labels on its SKOs', function () {
    [, $warehouse] = mobileInventoryViewer($this->group);
    $stockFamily   = StoreStockFamily::make()->action($warehouse->group, StockFamily::factory()->definition());
    $family        = StoreOrgStockFamily::make()->action($warehouse->organisation, $stockFamily, []);
    [$sko]         = mobileInventorySko($warehouse, 'MIF'.Str::upper(Str::random(6)));
    $sko->update(['state' => OrgStockStateEnum::ACTIVE, 'org_stock_family_id' => $family->id, 'packed_in' => 4]);
    $sko->locationOrgStocks()->update(['quantity' => 1.375]);
    OrgStockHydrateQuantityInLocations::run($sko->id);
    OrgStockFamilyHydrateOrgStocks::run($family);

    $response = getJson(route('grp.api.mobile.warehouses.org_stock_families.skos', [$warehouse->id, $family->id]), mobileInventoryHeaders())->assertOk();
    $buckets  = GetOrganisationStockCoverBuckets::run($warehouse->organisation, $family->refresh());

    expect($response->json('family.sko_status'))->toBe([
        ['key' => 'active', 'label' => 'Active', 'count' => 1],
        ['key' => 'discontinuing', 'label' => 'Discontinuing', 'count' => 0],
        ['key' => 'discontinued', 'label' => 'Discontinued', 'count' => 0],
        ['key' => 'suspended', 'label' => 'Suspended', 'count' => 0],
    ])
        ->and($response->json('family.quantity_status'))->toBe(collect($buckets)->map(fn (array $bucket) => ['key' => $bucket['bucket'], 'label' => $bucket['label'], 'count' => (int) $bucket['count']])->all())
        ->and(collect($response->json('family.quantity_status'))->pluck('key')->all())->toBe(['out', 'w1', 'w2', 'w3', 'w4', 'ok', 'excess', 'dead'])
        ->and($response->json('data.0.code'))->toBe($sko->code)
        ->and($response->json('data.0.quantity_in_locations_label'))->toBe('2 3/4');
});

test('mobile negative SKO list gives one row per negative location of this warehouse, most negative first, counted as the web counts them', function () {
    $otherWarehouse = mobileInventoryOtherWarehouse();
    [, $warehouse]  = mobileInventoryViewer($this->group);
    $prefix         = 'MNG'.Str::upper(Str::random(5));
    $stockFamily    = StoreStockFamily::make()->action($warehouse->group, StockFamily::factory()->definition());
    $family         = StoreOrgStockFamily::make()->action($warehouse->organisation, $stockFamily, []);
    $list           = fn (array $query = []) => getJson(route('grp.api.mobile.warehouses.org_stocks', array_merge(['warehouse' => $warehouse->id, 'bucket' => 'negative'], $query)), mobileInventoryHeaders())->assertOk();
    $before         = $list()->json('meta.total');

    [$twice, $twicePicking, $twiceStoring] = mobileInventorySko($warehouse, $prefix.'A');
    [$once, $oncePicking]                  = mobileInventorySko($warehouse, $prefix.'B');
    [$positive]                            = mobileInventorySko($warehouse, $prefix.'C');
    $twice->update(['org_stock_family_id' => $family->id]);
    $twicePicking->update(['quantity' => -2]);
    $twiceStoring->update(['quantity' => -5]);
    $oncePicking->update(['quantity' => -5]);
    mobileInventoryLink($once, mobileInventoryLocation($otherWarehouse, $once->code.'-X'), 0, LocationStockTypeEnum::STORING)->update(['quantity' => -7]);

    $response = $list(['q' => $prefix])->assertJsonPath('meta.total', 3);

    expect(collect($response->json('data'))->map(fn (array $row) => [$row['code'], $row['location']['code'], $row['location_quantity']])->all())
        ->toBe([
            [$twice->code, $twice->code.'-S', -5],
            [$once->code, $once->code.'-P', -5],
            [$twice->code, $twice->code.'-P', -2],
        ])
        ->and(array_keys($response->json('data.0')))->toBe([
            'id', 'slug', 'code', 'name', 'state', 'state_label', 'family', 'image_url',
            'quantity_available', 'quantity_available_label', 'quantity_available_fraction', 'quantity_in_locations', 'quantity_in_locations_label',
            'quantity_in_locations_fraction', 'stock_value', 'number_locations', 'last_seen_at', 'location_org_stock_id', 'location', 'location_quantity',
            'location_quantity_label',
        ]);

    $response
        ->assertJsonPath('data.0.id', $twice->id)
        ->assertJsonPath('data.0.slug', $twice->slug)
        ->assertJsonPath('data.0.state', $twice->refresh()->state->value)
        ->assertJsonPath('data.0.family', ['id' => $family->id, 'code' => $family->code])
        ->assertJsonPath('data.0.quantity_in_locations', mobileInventoryNumber($twice->quantity_in_locations))
        ->assertJsonPath('data.0.number_locations', 2)
        ->assertJsonPath('data.0.location_org_stock_id', $twiceStoring->id)
        ->assertJsonPath('data.0.location', ['id' => $twiceStoring->location_id, 'code' => $twice->code.'-S']);

    $all = $list();
    expect($all->json('meta.total'))->toBe($before + 3)
        ->and($all->json('counts.negative'))->toBe($all->json('meta.total'))
        ->and(ShowInventoryDashboard::numberNegativeStocks($warehouse))->toBe($all->json('meta.total'));

    $list(['family_id' => $family->id])
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('counts.negative', 2)
        ->assertJsonPath('data.*.location_org_stock_id', [$twiceStoring->id, $twicePicking->id]);

    $list(['q' => $once->code.'-P'])->assertJsonPath('data.*.location_org_stock_id', [$oncePicking->id]);

    $everySko = getJson(route('grp.api.mobile.warehouses.org_stocks', ['warehouse' => $warehouse->id, 'bucket' => 'all', 'q' => $prefix]), mobileInventoryHeaders())->assertOk();
    expect($everySko->json('data.*.code'))->toBe([$twice->code, $once->code, $positive->code])
        ->and(collect($everySko->json('data'))->map(fn (array $row) => [$row['location_org_stock_id'], $row['location'], $row['location_quantity']])->unique()->values()->all())
        ->toBe([[null, null, null]]);
});

test('mobile SKO detail lists the locations of this warehouse only, picking first', function () {
    $otherWarehouse = mobileInventoryOtherWarehouse();
    [, $warehouse]  = actingAsMobileWarehouseUser($this->group, RolesEnum::STOCK_CONTROLLER);
    [$orgStock, $picking, $storing] = mobileInventorySko($warehouse);
    $early = mobileInventoryLink($orgStock, mobileInventoryLocation($warehouse, $orgStock->code.'-A'), 0, LocationStockTypeEnum::STORING);
    mobileInventoryLink($orgStock, mobileInventoryLocation($otherWarehouse, $orgStock->code.'-X'), 10, LocationStockTypeEnum::STORING);
    $storing->update(['settings' => ['min_stock' => 5, 'max_stock' => 40], 'notes' => 'Top shelf']);
    $orgStock->update(['note_to_pickers' => 'Fragile', 'packed_in' => 6, 'barcode' => 'MIB'.Str::upper(Str::random(8))]);
    $orgStock->refresh();
    $pickingLocation = Location::find($orgStock->picking_location_id);

    $response = getJson(route('grp.api.mobile.warehouses.org_stocks.show', [$warehouse->id, $orgStock->id]), mobileInventoryHeaders())
        ->assertOk()
        ->assertJsonPath('data.id', $orgStock->id)
        ->assertJsonPath('data.code', $orgStock->code)
        ->assertJsonPath('data.state', 'active')
        ->assertJsonPath('data.barcode', $orgStock->barcode)
        ->assertJsonPath('data.packed_in', 6)
        ->assertJsonPath('data.note_to_pickers', 'Fragile')
        ->assertJsonPath('data.currency_code', $warehouse->organisation->currency->code)
        ->assertJsonPath('data.quantity_in_locations', 60)
        ->assertJsonPath('data.quantity_in_locations_label', '60')
        ->assertJsonPath('data.quantity_reserved', mobileInventoryNumber($orgStock->quantity_in_submitted_orders))
        ->assertJsonPath('data.quantity_to_be_picked', mobileInventoryNumber($orgStock->quantity_to_be_picked))
        ->assertJsonPath('data.picking_location', $pickingLocation ? ['id' => $pickingLocation->id, 'code' => $pickingLocation->code] : null)
        ->assertJsonPath('data.can', ['edit_stocks' => true, 'edit_locations' => false])
        ->assertJsonCount(3, 'data.locations')
        ->assertJsonPath('data.locations.0.location_org_stock_id', $picking->id)
        ->assertJsonPath('data.locations.0.type', 'picking')
        ->assertJsonPath('data.locations.0.quantity', 30)
        ->assertJsonPath('data.locations.0.quantity_label', '30')
        ->assertJsonPath('data.locations.1.location_org_stock_id', $early->id)
        ->assertJsonPath('data.locations.2.location_org_stock_id', $storing->id)
        ->assertJsonPath('data.locations.2.min_stock', 5)
        ->assertJsonPath('data.locations.2.max_stock', 40)
        ->assertJsonPath('data.locations.2.replenishment_stock', null)
        ->assertJsonPath('data.locations.2.notes', 'Top shelf')
        ->assertJsonPath('data.locations.2.location.id', $storing->location_id)
        ->assertJsonPath('data.locations.2.location.status', 'operational')
        ->assertJsonPath('data.locations.2.location.allow_stocks', true);

    expect(array_keys($response->json('data.locations.0')))->toBe([
        'location_org_stock_id', 'type', 'quantity', 'quantity_label', 'quantity_fraction', 'picking_priority', 'notes', 'min_stock', 'max_stock',
        'replenishment_stock', 'is_default_wholesale', 'is_default_dropshipping', 'audited_at', 'location', 'version',
    ]);

    mobileInventoryViewer($this->group);
    getJson(route('grp.api.mobile.warehouses.org_stocks.show', [$warehouse->id, $orgStock->id]), mobileInventoryHeaders())
        ->assertOk()
        ->assertJsonPath('data.can.edit_stocks', false);
});

test('mobile SKO pages and writes are not found for an SKO of another organisation', function () {
    $organisationData = Organisation::factory()->definition();
    data_set($organisationData, 'code', 'minv'.random_int(1000, 9999));
    data_set($organisationData, 'type', OrganisationTypeEnum::SHOP);
    $foreign = mobileInventoryOrgStock(StoreOrganisation::make()->action($this->group, $organisationData));
    [, $warehouse] = actingAsMobileWarehouseUser($this->group, RolesEnum::STOCK_CONTROLLER);

    getJson(route('grp.api.mobile.warehouses.org_stocks.show', [$warehouse->id, $foreign->id]), mobileInventoryHeaders())->assertNotFound();
    getJson(route('grp.api.mobile.warehouses.org_stocks.stock_movements', [$warehouse->id, $foreign->id]), mobileInventoryHeaders())->assertNotFound();
    postJson(route('grp.api.mobile.warehouses.org_stocks.audit', [$warehouse->id, $foreign->id]), ['counts' => [['location_org_stock_id' => 1, 'quantity' => 1]]], mobileInventoryHeaders())->assertNotFound();
});

test('mobile SKO stock movements are the web stock history of this warehouse, filtered by flow', function () {
    $otherWarehouse = mobileInventoryOtherWarehouse();
    [$user, $warehouse] = actingAsMobileWarehouseUser($this->group, RolesEnum::STOCK_CONTROLLER);
    [$orgStock, $picking] = mobileInventorySko($warehouse);
    $elsewhere = mobileInventoryLink($orgStock, mobileInventoryLocation($otherWarehouse, $orgStock->code.'-X'), 10, LocationStockTypeEnum::STORING);
    ConsumeLocationOrgStock::make()->action($elsewhere, ['quantity' => 1], $user);

    postJson(route('grp.api.mobile.warehouses.org_stocks.audit', [$warehouse->id, $orgStock->id]), ['counts' => [[
        'location_org_stock_id' => $picking->id,
        'quantity'              => 28,
        'reason'                => OrgStockMovementReasonEnum::COUNT_SHORT->value,
    ]]], mobileInventoryHeaders())->assertOk();
    postJson(route('grp.api.mobile.location_org_stocks.consume', $picking->id), ['quantity' => 2, 'note' => 'Office'], mobileInventoryHeaders())->assertOk();

    $url  = fn (array $query = []) => route('grp.api.mobile.warehouses.org_stocks.stock_movements', array_merge(['warehouse' => $warehouse->id, 'orgStock' => $orgStock->id], $query));
    $rows = collect(getJson($url(), mobileInventoryHeaders())
        ->assertOk()
        ->assertJsonPath('meta.total', OrgStockMovement::where('org_stock_id', $orgStock->id)->where('warehouse_id', $warehouse->id)->whereNot('class', OrgStockMovementClassEnum::GARBAGE)->count())
        ->json('data'));

    expect($rows->pluck('location.id')->filter()->diff(Location::where('warehouse_id', $warehouse->id)->pluck('id'))->all())->toBe([])
        ->and($rows->pluck('location.id'))->not->toContain($elsewhere->location_id)
        ->and($rows->first())->toMatchArray([
            'type'       => OrgStockMovementTypeEnum::CONSUMPTION->value,
            'flow'       => 'out',
            'quantity'       => -2,
            'quantity_label' => '-2',
            'reason'     => OrgStockMovementReasonEnum::INTERNAL_USE->value,
            'note'       => 'Office',
            'location'   => ['id' => $picking->location_id, 'code' => $picking->location->code],
            'user'       => mobileInventoryPerson($user),
        ])
        ->and(array_keys($rows->first()))->toBe([
            'id', 'date', 'type', 'type_label', 'flow', 'quantity', 'quantity_label', 'quantity_fraction', 'running_quantity_org_stock', 'running_quantity_org_stock_label', 'location',
            'reason', 'reason_label', 'note', 'parent_type', 'parent_reference', 'user',
        ]);

    $audits = collect(getJson($url(['flow' => 'audit']), mobileInventoryHeaders())->assertOk()->json('data'));

    expect($audits->pluck('flow')->unique()->values()->all())->toBe(['audit'])
        ->and($audits->first())->toMatchArray([
            'type'     => OrgStockMovementTypeEnum::AUDIT->value,
            'reason'   => OrgStockMovementReasonEnum::COUNT_SHORT->value,
            'quantity' => -2,
            'user'     => mobileInventoryPerson($user),
        ]);

    getJson($url(['flow' => 'sideways']), mobileInventoryHeaders())->assertUnprocessable()->assertJsonValidationErrors('flow');
});

test('mobile SKO audit confirms an unchanged count as a recount and records the user on every count', function () {
    [$user, $warehouse] = actingAsMobileWarehouseUser($this->group, RolesEnum::STOCK_CONTROLLER);
    [$orgStock, $picking, $storing] = mobileInventorySko($warehouse);
    $url = route('grp.api.mobile.warehouses.org_stocks.audit', [$warehouse->id, $orgStock->id]);

    postJson($url, ['counts' => [
        ['location_org_stock_id' => $picking->id, 'quantity' => 30],
        ['location_org_stock_id' => $storing->id, 'quantity' => 23.5, 'reason' => OrgStockMovementReasonEnum::COUNT_GAIN->value, 'note' => 'Found behind'],
    ]], mobileInventoryHeaders())
        ->assertOk()
        ->assertJsonPath('result.status', 'done')
        ->assertJsonPath('data.locations.0.quantity', 30)
        ->assertJsonPath('data.locations.1.quantity', 23.5)
        ->assertJsonPath('data.locations.1.quantity_label', '23 1/2')
        ->assertJsonPath('data.quantity_in_locations', 53.5);

    $audit = fn (LocationOrgStock $link) => OrgStockMovement::where('org_stock_id', $orgStock->id)->where('location_id', $link->location_id)->where('type', OrgStockMovementTypeEnum::AUDIT)->latest('id')->first();

    expect($audit($picking)->reason)->toBe(OrgStockMovementReasonEnum::RECOUNT)
        ->and((float) $audit($picking)->quantity)->toBe(0.0)
        ->and($audit($picking)->user_id)->toBe($user->id)
        ->and($audit($storing)->reason)->toBe(OrgStockMovementReasonEnum::COUNT_GAIN)
        ->and($audit($storing)->note)->toBe('Found behind')
        ->and($audit($storing)->user_id)->toBe($user->id)
        ->and($picking->refresh()->audited_at)->not->toBeNull();

    postJson($url, ['counts' => [['location_org_stock_id' => $picking->id, 'quantity' => 30]]], mobileInventoryHeaders())
        ->assertOk()
        ->assertJsonPath('result.status', 'nothing_changed');
});

test('mobile SKO audit needs a reason of the right direction for a change, and links of this SKO in this warehouse', function () {
    $otherWarehouse = mobileInventoryOtherWarehouse();
    [, $warehouse] = actingAsMobileWarehouseUser($this->group, RolesEnum::STOCK_CONTROLLER);
    [$orgStock, $picking, $storing] = mobileInventorySko($warehouse);
    [, $otherSkoLink] = mobileInventorySko($warehouse);
    $elsewhere = mobileInventoryLink($orgStock, mobileInventoryLocation($otherWarehouse, $orgStock->code.'-X'), 10, LocationStockTypeEnum::STORING);
    $url       = route('grp.api.mobile.warehouses.org_stocks.audit', [$warehouse->id, $orgStock->id]);
    $audits    = fn () => OrgStockMovement::where('org_stock_id', $orgStock->id)->where('type', OrgStockMovementTypeEnum::AUDIT)->count();
    $before    = $audits();

    postJson($url, ['counts' => [['location_org_stock_id' => $picking->id, 'quantity' => 25]]], mobileInventoryHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('counts.0.reason');
    postJson($url, ['counts' => [['location_org_stock_id' => $picking->id, 'quantity' => 25, 'reason' => OrgStockMovementReasonEnum::COUNT_GAIN->value]]], mobileInventoryHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('counts.0.reason');
    postJson($url, ['counts' => [['location_org_stock_id' => $picking->id, 'quantity' => 35, 'reason' => OrgStockMovementReasonEnum::COUNT_SHORT->value]]], mobileInventoryHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('counts.0.reason');
    postJson($url, ['counts' => [
        ['location_org_stock_id' => $picking->id, 'quantity' => 28, 'reason' => OrgStockMovementReasonEnum::COUNT_SHORT->value],
        ['location_org_stock_id' => $storing->id, 'quantity' => 15],
    ]], mobileInventoryHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('counts.1.reason');
    postJson($url, ['counts' => [
        ['location_org_stock_id' => $picking->id, 'quantity' => 30],
        ['location_org_stock_id' => $picking->id, 'quantity' => 30],
    ]], mobileInventoryHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('counts.0.location_org_stock_id');

    postJson($url, ['counts' => [['location_org_stock_id' => $otherSkoLink->id, 'quantity' => 1, 'reason' => 'count_short']]], mobileInventoryHeaders())->assertNotFound();
    postJson($url, ['counts' => [['location_org_stock_id' => $elsewhere->id, 'quantity' => 10]]], mobileInventoryHeaders())->assertNotFound();

    expect($audits())->toBe($before)
        ->and((float) $picking->refresh()->quantity)->toBe(30.0);
});

test('mobile SKO audit saves every count or none of them', function () {
    [, $warehouse] = actingAsMobileWarehouseUser($this->group, RolesEnum::STOCK_CONTROLLER);
    [$orgStock, $picking, $storing] = mobileInventorySko($warehouse);
    $before = OrgStockMovement::where('org_stock_id', $orgStock->id)->count();

    postJson(route('grp.api.mobile.warehouses.org_stocks.audit', [$warehouse->id, $orgStock->id]), ['counts' => [
        ['location_org_stock_id' => $picking->id, 'quantity' => 28, 'reason' => OrgStockMovementReasonEnum::COUNT_SHORT->value],
        ['location_org_stock_id' => $storing->id, 'quantity' => 100000000000, 'reason' => OrgStockMovementReasonEnum::COUNT_GAIN->value],
    ]], mobileInventoryHeaders())->assertServerError();

    expect((float) $picking->refresh()->quantity)->toBe(30.0)
        ->and((float) $storing->refresh()->quantity)->toBe(20.0)
        ->and($picking->audited_at)->toBeNull()
        ->and(OrgStockMovement::where('org_stock_id', $orgStock->id)->count())->toBe($before);
});

test('mobile SKO move sends stock to other locations of the same SKO and records the user', function () {
    $otherWarehouse = mobileInventoryOtherWarehouse();
    [$user, $warehouse] = actingAsMobileWarehouseUser($this->group, RolesEnum::STOCK_CONTROLLER);
    [$orgStock, $picking, $storing] = mobileInventorySko($warehouse);
    [, $otherSkoLink] = mobileInventorySko($warehouse);
    $elsewhere = mobileInventoryLink($orgStock, mobileInventoryLocation($otherWarehouse, $orgStock->code.'-X'), 10, LocationStockTypeEnum::STORING);
    $url       = route('grp.api.mobile.location_org_stocks.move', $picking->id);
    $target    = fn (LocationOrgStock $link, float $quantity) => ['targets' => [['location_org_stock_id' => $link->id, 'quantity' => $quantity]]];

    postJson($url, $target($storing, 10.5), mobileInventoryHeaders())
        ->assertOk()
        ->assertJsonPath('result.status', 'done')
        ->assertJsonPath('data.id', $orgStock->id)
        ->assertJsonPath('data.locations.0.quantity', 19.5)
        ->assertJsonPath('data.locations.1.quantity', 30.5);

    $transfers = OrgStockMovement::where('org_stock_id', $orgStock->id)->where('type', OrgStockMovementTypeEnum::LOCATION_TRANSFER)->get();

    expect($transfers)->toHaveCount(2)
        ->and($transfers->pluck('user_id')->unique()->all())->toBe([$user->id]);

    postJson($url, $target($storing, 100), mobileInventoryHeaders())->assertUnprocessable()->assertJsonValidationErrors('targets');
    postJson($url, $target($picking, 1), mobileInventoryHeaders())->assertUnprocessable()->assertJsonValidationErrors('targets');
    postJson($url, $target($storing, 0), mobileInventoryHeaders())->assertUnprocessable()->assertJsonValidationErrors('targets.0.quantity');
    postJson($url, $target($otherSkoLink, 1), mobileInventoryHeaders())->assertNotFound();
    postJson($url, $target($elsewhere, 1), mobileInventoryHeaders())->assertNotFound();

    expect((float) $picking->refresh()->quantity)->toBe(19.5);
});

test('mobile SKO consume takes stock out of one location for internal use', function () {
    [$user, $warehouse] = actingAsMobileWarehouseUser($this->group, RolesEnum::STOCK_CONTROLLER);
    [$orgStock, $picking] = mobileInventorySko($warehouse);
    $url = route('grp.api.mobile.location_org_stocks.consume', $picking->id);

    postJson($url, ['quantity' => 4, 'note' => 'Office tea'], mobileInventoryHeaders())
        ->assertOk()
        ->assertJsonPath('result.status', 'done')
        ->assertJsonPath('data.locations.0.quantity', 26);

    $movement = OrgStockMovement::where('org_stock_id', $orgStock->id)->where('type', OrgStockMovementTypeEnum::CONSUMPTION)->latest('id')->first();

    expect((float) $movement->quantity)->toBe(-4.0)
        ->and($movement->reason)->toBe(OrgStockMovementReasonEnum::INTERNAL_USE)
        ->and($movement->note)->toBe('Office tea')
        ->and($movement->user_id)->toBe($user->id);

    postJson($url, ['quantity' => 100], mobileInventoryHeaders())->assertUnprocessable()->assertJsonValidationErrors('quantity');
    postJson($url, ['quantity' => 0], mobileInventoryHeaders())->assertUnprocessable()->assertJsonValidationErrors('quantity');
});

test('mobile SKO add location links a location of this warehouse, and a new picking location turns the old one into storing', function () {
    $otherWarehouse = mobileInventoryOtherWarehouse();
    [$user, $warehouse] = actingAsMobileWarehouseUser($this->group, RolesEnum::STOCK_CONTROLLER);
    [$orgStock, $picking] = mobileInventorySko($warehouse);
    $url = route('grp.api.mobile.warehouses.org_stocks.locations.store', [$warehouse->id, $orgStock->id]);
    $new = mobileInventoryLocation($warehouse, $orgStock->code.'-N');

    $response = postJson($url, ['location_id' => $new->id, 'type' => 'picking'], mobileInventoryHeaders())
        ->assertOk()
        ->assertJsonPath('result.status', 'done')
        ->assertJsonCount(3, 'data.locations')
        ->assertJsonPath('data.locations.0.location.id', $new->id)
        ->assertJsonPath('data.locations.0.type', 'picking')
        ->assertJsonPath('data.locations.0.quantity', 0);

    $link        = LocationOrgStock::where('org_stock_id', $orgStock->id)->where('location_id', $new->id)->first();
    $association = OrgStockMovement::where('org_stock_id', $orgStock->id)->where('location_id', $new->id)->where('type', OrgStockMovementTypeEnum::ASSOCIATE)->first();

    expect($link->type)->toBe(LocationStockTypeEnum::PICKING)
        ->and($picking->refresh()->type)->toBe(LocationStockTypeEnum::STORING)
        ->and($association->user_id)->toBe($user->id)
        ->and($response->json('result.message'))->toContain($picking->location->code);

    $storingLocation = mobileInventoryLocation($warehouse, $orgStock->code.'-T');
    postJson($url, ['location_id' => $storingLocation->id, 'type' => 'storing'], mobileInventoryHeaders())->assertOk();
    expect(LocationOrgStock::where('org_stock_id', $orgStock->id)->where('location_id', $storingLocation->id)->first()->type)->toBe(LocationStockTypeEnum::STORING)
        ->and($link->refresh()->type)->toBe(LocationStockTypeEnum::PICKING);

    $noStock = mobileInventoryLocation($warehouse, $orgStock->code.'-U');
    $noStock->update(['allow_stocks' => false]);
    $broken = mobileInventoryLocation($warehouse, $orgStock->code.'-B');
    $broken->update(['status' => LocationStatusEnum::BROKEN]);

    postJson($url, ['location_id' => $new->id, 'type' => 'storing'], mobileInventoryHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['location_id' => 'This SKO is already in that location']);
    postJson($url, ['location_id' => $noStock->id, 'type' => 'storing'], mobileInventoryHeaders())->assertUnprocessable()->assertJsonValidationErrors('location_id');
    postJson($url, ['location_id' => $broken->id, 'type' => 'storing'], mobileInventoryHeaders())->assertUnprocessable()->assertJsonValidationErrors('location_id');
    postJson($url, ['location_id' => $new->id], mobileInventoryHeaders())->assertUnprocessable()->assertJsonValidationErrors('type');
    postJson($url, ['location_id' => mobileInventoryLocation($otherWarehouse, $orgStock->code.'-X')->id, 'type' => 'storing'], mobileInventoryHeaders())->assertNotFound();
});

test('mobile SKO remove locations writes off the stock left in them, all in one go', function () {
    [$user, $warehouse] = actingAsMobileWarehouseUser($this->group, RolesEnum::STOCK_CONTROLLER);
    [$orgStock, $picking, $storing] = mobileInventorySko($warehouse);
    [, $otherSkoLink] = mobileInventorySko($warehouse);
    $url = route('grp.api.mobile.warehouses.org_stocks.remove_locations', [$warehouse->id, $orgStock->id]);

    postJson($url, ['location_org_stock_ids' => [$storing->id, $otherSkoLink->id]], mobileInventoryHeaders())->assertNotFound();
    expect(LocationOrgStock::whereKey($storing->id)->exists())->toBeTrue();

    $response = postJson($url, ['location_org_stock_ids' => [$storing->id]], mobileInventoryHeaders())
        ->assertOk()
        ->assertJsonPath('result.status', 'done')
        ->assertJsonCount(1, 'data.locations')
        ->assertJsonPath('data.locations.0.location_org_stock_id', $picking->id);

    $writeOff = OrgStockMovement::where('org_stock_id', $orgStock->id)->where('location_id', $storing->location_id)->where('type', OrgStockMovementTypeEnum::DISASSOCIATE)->first();

    expect(LocationOrgStock::whereKey($storing->id)->exists())->toBeFalse()
        ->and((float) $writeOff->quantity)->toBe(-20.0)
        ->and($writeOff->user_id)->toBe($user->id)
        ->and($response->json('result.message'))->toContain('written off');

    postJson($url, ['location_org_stock_ids' => []], mobileInventoryHeaders())->assertUnprocessable()->assertJsonValidationErrors('location_org_stock_ids');
});

test('mobile SKO writes need stock edit rights', function () {
    [, $warehouse] = mobileInventoryViewer($this->group);
    [$orgStock, $picking, $storing] = mobileInventorySko($warehouse);
    $location = mobileInventoryLocation($warehouse, $orgStock->code.'-N');

    $writes = [
        [route('grp.api.mobile.warehouses.org_stocks.audit', [$warehouse->id, $orgStock->id]), ['counts' => [['location_org_stock_id' => $picking->id, 'quantity' => 30]]]],
        [route('grp.api.mobile.location_org_stocks.move', $picking->id), ['targets' => [['location_org_stock_id' => $storing->id, 'quantity' => 1]]]],
        [route('grp.api.mobile.location_org_stocks.consume', $picking->id), ['quantity' => 1]],
        [route('grp.api.mobile.warehouses.org_stocks.locations.store', [$warehouse->id, $orgStock->id]), ['location_id' => $location->id, 'type' => 'storing']],
        [route('grp.api.mobile.warehouses.org_stocks.remove_locations', [$warehouse->id, $orgStock->id]), ['location_org_stock_ids' => [$storing->id]]],
    ];

    foreach ($writes as [$url, $body]) {
        postJson($url, $body, mobileInventoryHeaders())->assertForbidden()->assertJsonStructure(['message']);
    }

    expect((float) $picking->refresh()->quantity)->toBe(30.0)
        ->and(LocationOrgStock::whereKey($storing->id)->exists())->toBeTrue();

    Sanctum::actingAs(createMobileAppUser($this->group), ['mobile']);
    getJson(route('grp.api.mobile.warehouses.org_stocks', $warehouse->id), mobileInventoryHeaders())->assertForbidden();
    getJson(route('grp.api.mobile.warehouses.org_stocks.show', [$warehouse->id, $orgStock->id]), mobileInventoryHeaders())->assertForbidden();
    getJson(route('grp.api.mobile.warehouses.org_stocks.stock_movements', [$warehouse->id, $orgStock->id]), mobileInventoryHeaders())->assertForbidden();
});

test('mobile locations gain a barcode, last audit, pick locations, image and numeric quantity, keeping their existing keys', function () {
    [, $warehouse] = actingAsMobileWarehouseUser($this->group, RolesEnum::STOCK_CONTROLLER);
    [, $stocked, , $prefix] = createMobileLocationsFixture($warehouse);
    $link = $stocked->locationOrgStocks()->first();

    postJson(route('grp.api.mobile.locations.audit', $stocked->id), ['counts' => [[
        'location_org_stock_id' => $link->id,
        'quantity'              => 30,
        'reason'                => OrgStockMovementReasonEnum::RECOUNT->value,
    ]]], mobileInventoryHeaders())->assertOk();

    $link->refresh();
    $orgStock    = $link->orgStock->refresh();
    $lastAudited = Carbon::parse($link->audited_at)->toIso8601String();
    $summaryKeys = [
        'id', 'slug', 'code', 'status', 'area', 'allow_stocks', 'allow_fulfilment', 'allow_dropshipping', 'is_goods_out', 'keep_empty_org_stocks',
        'max_weight', 'max_volume', 'number_org_stock_slots', 'number_empty_stock_slots', 'number_pallets', 'stock_value', 'audited_at',
        'barcode', 'last_audited_at',
    ];

    $row = getJson(route('grp.api.mobile.warehouses.locations', ['warehouse' => $warehouse->id, 'q' => $prefix.'-01']), mobileInventoryHeaders())
        ->assertOk()
        ->json('data.0');

    expect(array_keys($row))->toBe($summaryKeys)
        ->and($row['barcode'])->toBe($stocked->refresh()->barcode)
        ->and($row['last_audited_at'])->toBe($lastAudited)
        ->and($row['audited_at'])->toBeNull();

    $detail = getJson(route('grp.api.mobile.locations.show', $stocked->id), mobileInventoryHeaders())->assertOk()->json('data');
    $sko    = $detail['org_stocks'][0];
    $pick   = fn (?int $locationId) => $locationId ? ['id' => $locationId, 'code' => Location::find($locationId)->code] : null;

    expect(array_slice(array_keys($detail), 0, count($summaryKeys)))->toBe($summaryKeys)
        ->and(array_slice(array_keys($detail), count($summaryKeys)))->toBe(['pallets', 'org_stocks', 'can'])
        ->and($detail['last_audited_at'])->toBe($lastAudited)
        ->and(array_slice(array_keys($sko), -14))->toBe([
            'default_wholesale_picking_location', 'default_dropshipping_picking_location', 'min_stock', 'max_stock', 'replenishment_stock', 'audited_at',
            'image_url', 'picking_location', 'picking_dropshipping_location', 'quantity_value', 'packed_in', 'quantity_label', 'quantity_fraction', 'version',
        ])
        ->and($sko['quantity_label'])->toBe('30')
        ->and($sko['version'])->toBe((int) $link->version)
        ->and($sko['quantity'])->toBe('30')
        ->and($sko['quantity_value'])->toBe(30)
        ->and($sko['min_stock'])->toBe(12)
        ->and($sko['image_url'])->toBeNull()
        ->and($sko['picking_location'])->toBe($pick($orgStock->picking_location_id))
        ->and($sko['picking_location'])->toBe(['id' => $stocked->id, 'code' => $stocked->code])
        ->and($sko['picking_dropshipping_location'])->toBe($pick($orgStock->picking_dropshipping_location_id));

    $stats    = $warehouse->refresh()->stats;
    $overview = getJson(route('grp.api.mobile.warehouses.locations_overview', $warehouse->id), mobileInventoryHeaders())->assertOk()->json('data');

    expect(array_keys($overview))->toBe([
        'warehouse', 'number_areas', 'number_locations', 'number_locations_operational', 'number_locations_broken', 'number_locations_empty',
        'number_locations_partially_empty', 'stock_value', 'empty_locations_removal', 'utilisation', 'currency_code',
    ])
        ->and($overview['currency_code'])->toBe($warehouse->organisation->currency->code)
        ->and($overview['utilisation'])->toBe($stats->number_locations ? mobileInventoryNumber(($stats->number_locations - $stats->number_empty_locations) / $stats->number_locations) : null);
});

test('mobile SKO and location quantities carry the fractions the web shows, odd ones included, with the total rounded as the web stores it', function () {
    [, $warehouse] = actingAsMobileWarehouseUser($this->group, RolesEnum::STOCK_CONTROLLER);
    [$orgStock, $picking, $storing] = mobileInventorySko($warehouse);
    $orgStock->update(['packed_in' => 16]);
    $picking->update(['quantity' => 28.625]);
    $storing->update(['quantity' => 0.333333]);
    $webFraction = fn (float $quantity) => riseDivisor(divideWithRemainder(findSmallestFactors($quantity)), 16);

    $detail = getJson(route('grp.api.mobile.warehouses.org_stocks.show', [$warehouse->id, $orgStock->id]), mobileInventoryHeaders())->assertOk()->json('data');

    expect($detail['quantity_in_locations'])->toBe(28.958)
        ->and($detail['quantity_in_locations_label'])->toBe('28 91/95')
        ->and($detail['quantity_in_locations_fraction'])->toBe($webFraction(28.958))
        ->and($detail['locations'][0]['quantity_label'])->toBe('28 10/16')
        ->and($detail['locations'][0]['quantity_fraction'])->toBe([28, [10, 16]])
        ->and($detail['locations'][1]['quantity_label'])->toBe('1/3')
        ->and($detail['locations'][1]['quantity_fraction'])->toBe($webFraction(0.333333));

    $location = getJson(route('grp.api.mobile.locations.show', $picking->location_id), mobileInventoryHeaders())->assertOk()->json('data.org_stocks.0');

    expect($location['packed_in'])->toBe(16)
        ->and($location['quantity_label'])->toBe('28 10/16')
        ->and($location['quantity_fraction'])->toBe([28, [10, 16]]);
});

test('mobile SKOs of a family give their number of locations', function () {
    [, $warehouse] = mobileInventoryViewer($this->group);
    $stockFamily   = StoreStockFamily::make()->action($warehouse->group, StockFamily::factory()->definition());
    $family        = StoreOrgStockFamily::make()->action($warehouse->organisation, $stockFamily, []);
    [$orgStock]    = mobileInventorySko($warehouse);
    $orgStock->update(['org_stock_family_id' => $family->id]);

    $rows = collect(getJson(route('grp.api.mobile.warehouses.org_stock_families.skos', [$warehouse->id, $family->id]), mobileInventoryHeaders())
        ->assertOk()
        ->json('data'));

    expect($rows->firstWhere('id', $orgStock->id)['number_locations'])->toBe((int) DB::table('org_stock_stats')->where('org_stock_id', $orgStock->id)->value('number_locations'))
        ->and($rows->firstWhere('id', $orgStock->id)['number_locations'])->toBe(2);
});
