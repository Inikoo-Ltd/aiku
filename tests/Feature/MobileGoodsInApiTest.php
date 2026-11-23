<?php

/*
 * Author Louis Perez
 * Created on 16-11-2026-11h-33m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Feature;

use App\Actions\Goods\Stock\StoreStock;
use App\Actions\GoodsIn\StockDelivery\DispatchStockDelivery;
use App\Actions\GoodsIn\StockDelivery\ReceiveStockDelivery;
use App\Actions\GoodsIn\StockDelivery\StoreStockDelivery;
use App\Actions\GoodsIn\StockDeliveryItem\SetStockDeliveryItemCheckedQuantity;
use App\Actions\GoodsIn\StockDeliveryItem\StoreStockDeliveryItem;
use App\Actions\GoodsIn\StockDeliveryItem\UpsertStockDeliveryItemPlaced;
use App\Actions\Inventory\Location\StoreLocation;
use App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock;
use App\Actions\Inventory\OrgStock\StoreOrgStock;
use App\Actions\Inventory\Warehouse\StoreWarehouse;
use App\Actions\Procurement\OrgSupplierProducts\StoreOrgSupplierProduct;
use App\Actions\SupplyChain\Supplier\StoreSupplier;
use App\Actions\SupplyChain\SupplierProduct\StoreSupplierProduct;
use App\Actions\SysAdmin\Organisation\StoreOrganisation;
use App\Actions\UI\Incoming\GetIncomingHubReturnDeliveryNoteWidget;
use App\Actions\UI\Incoming\GetIncomingHubStockDeliveryWidget;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Enums\Inventory\Location\LocationStatusEnum;
use App\Enums\Inventory\LocationStock\LocationStockTypeEnum;
use App\Enums\SupplyChain\Supplier\SupplierPackingMethodEnum;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\Goods\Stock;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Models\Inventory\Location;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\getJson;

require_once __DIR__.'/../Support/MobileApiHelpers.php';
require_once __DIR__.'/../Support/MobileGoodsOutHelpers.php';

function mobileGoodsInSko(Organisation $organisation, int $packedIn = 1, ?string $code = null): OrgStock
{
    $stock    = StoreStock::make()->action($organisation->group, array_merge(Stock::factory()->definition(), ['code' => $code ?? 'MGS'.Str::upper(Str::random(8)), 'state' => StockStateEnum::ACTIVE]));
    $orgStock = StoreOrgStock::make()->action($organisation, $stock);
    $orgStock->update(['packed_in' => $packedIn, 'barcode' => 'MGB'.Str::upper(Str::random(10))]);

    return $orgStock->refresh();
}

/**
 * @param array<int, array{0: OrgStock, 1: float}> $lines
 */
function mobileGoodsInDelivery(Organisation $organisation, array $lines, ?string $reference = null): StockDelivery
{
    $reference   ??= 'MGD'.Str::upper(Str::random(8));
    $supplier    = StoreSupplier::make()->action(parent: $organisation->group, modelData: Supplier::factory()->definition());
    $orgSupplier = $supplier->orgSuppliers()->where('organisation_id', $organisation->id)->firstOrFail();

    $stockDelivery = StoreStockDelivery::make()->action($orgSupplier, ['reference' => $reference, 'date' => date('Y-m-d')]);

    foreach ($lines as $index => [$orgStock, $units]) {
        $supplierProduct = StoreSupplierProduct::make()->action($supplier, [
            'code'             => $reference.'-P'.$index,
            'name'             => 'Goods in product '.$index,
            'cost'             => 100,
            'stock_id'         => $orgStock->stock_id,
            'units_per_pack'   => max(1, (int) $orgStock->packed_in),
            'units_per_carton' => 100,
        ]);
        StoreOrgSupplierProduct::make()->action($orgSupplier, $supplierProduct);
        StoreStockDeliveryItem::make()->action($stockDelivery, $supplierProduct->historicSupplierProduct, $orgStock, ['unit_quantity' => $units]);
    }

    return $stockDelivery->refresh();
}

function mobileGoodsInReceived(StockDelivery $stockDelivery): StockDelivery
{
    DispatchStockDelivery::make()->action($stockDelivery->refresh());

    return ReceiveStockDelivery::make()->action($stockDelivery->refresh())->refresh();
}

function mobileGoodsInChecked(StockDelivery $stockDelivery, float $units): StockDeliveryItem
{
    return SetStockDeliveryItemCheckedQuantity::make()->action($stockDelivery->items()->orderBy('id')->firstOrFail(), ['unit_quantity_checked' => $units]);
}

function mobileGoodsInLocation(Warehouse $warehouse, array $attributes = []): Location
{
    $location = StoreLocation::make()->action($warehouse, ['code' => 'MGL'.Str::upper(Str::random(6))]);
    if ($attributes) {
        $location->update($attributes);
    }

    return $location->refresh();
}

function mobileGoodsInLink(OrgStock $orgStock, Location $location, float $quantity = 5, bool $isDefaultPicking = false): LocationOrgStock
{
    $link = StoreLocationOrgStock::make()->action(
        orgStock: $orgStock,
        location: $location,
        modelData: ['quantity' => $quantity, 'type' => $isDefaultPicking ? LocationStockTypeEnum::PICKING : LocationStockTypeEnum::STORING, 'fetched_at' => now()],
        strict: false
    );
    $link->update(['default_wholesale_picking_location' => $isDefaultPicking, 'default_dropshipping_picking_location' => $isDefaultPicking]);

    return $link->refresh();
}

function mobileGoodsInOtherWarehouse(Organisation $organisation): Warehouse
{
    return StoreWarehouse::make()->action($organisation, Warehouse::factory()->definition());
}

/**
 * @param array<string, mixed> $query
 */
function mobileGoodsInList(Warehouse $warehouse, array $query = []): TestResponse
{
    return getGoodsOut('grp.api.mobile.warehouses.stock_deliveries', $warehouse->id, $query);
}

/**
 * @param array<string, mixed> $body
 */
function mobileGoodsInPost(string $action, Warehouse $warehouse, StockDelivery $stockDelivery, array $body = [], ?StockDeliveryItem $item = null): TestResponse
{
    $parameters = ['warehouse' => $warehouse->id, 'stockDelivery' => $stockDelivery->id];
    if ($item) {
        $parameters['stockDeliveryItem'] = $item->id;
    }

    return postGoodsOut('grp.api.mobile.warehouses.stock_deliveries.'.$action, $parameters, $body);
}

function mobileGoodsInShow(Warehouse $warehouse, StockDelivery $stockDelivery): TestResponse
{
    return getGoodsOut('grp.api.mobile.warehouses.stock_deliveries.show', ['warehouse' => $warehouse->id, 'stockDelivery' => $stockDelivery->id]);
}

/**
 * @return array<int, string>
 */
function mobileGoodsInKeys(mixed $data): array
{
    if (!is_array($data)) {
        return [];
    }

    return collect($data)->flatMap(fn ($value, $key) => [...(is_string($key) ? [$key] : []), ...mobileGoodsInKeys($value)])->unique()->values()->all();
}

function mobileGoodsInClerk(object $test, array $roles = [RolesEnum::GOODS_IN_CLERK], array $permissions = []): User
{
    return actingAsGoodsOutUser($test->group, $test->warehouse, $roles, $permissions);
}

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $this->warehouse    = createWarehouse();
    $this->organisation = $this->warehouse->organisation;
    $this->group        = $this->organisation->group;
});

test('mobile goods in hub gives the web hub figures, fulfilment and returns only with their rights', function () {
    mobileGoodsInClerk($this);
    $hub    = fn () => getGoodsOut('grp.api.mobile.warehouses.goods_in_hub', $this->warehouse->id)->assertOk()->json('data');
    $before = $hub();

    DispatchStockDelivery::make()->action(mobileGoodsInDelivery($this->organisation, [[mobileGoodsInSko($this->organisation), 10]]));

    $rows   = $hub();
    $widget = GetIncomingHubStockDeliveryWidget::run($this->warehouse, ['OrgSupplier', 'Production'])['data']['_global'];
    $stats  = $this->warehouse->refresh()->stats;

    expect(collect($rows)->pluck('key')->all())->toBe(['stock_deliveries', 'partners', 'agents', 'returns'])
        ->and($rows[0]['arriving'])->toBe($before[0]['arriving'] + 1)
        ->and($rows[0])->toBe([
            'key'          => 'stock_deliveries',
            'label'        => 'Stock Deliveries',
            'arriving'     => $widget['arriving']['value'],
            'received'     => $widget['received']['value'],
            'checked'      => $widget['checked']['value'],
            'booking_in'   => $widget['booking_in']['value'],
            'booked_in_7d' => $widget['booked_in']['value'],
            'placed_7d'    => $widget['booked_in']['suffix']['value'],
        ])
        ->and($rows[3])->toBe([
            'key'          => 'returns',
            'label'        => 'Returns',
            'arriving'     => null,
            'received'     => (int) $stats->number_return_delivery_notes_state_received,
            'checked'      => null,
            'booking_in'   => (int) $stats->number_return_delivery_notes_state_returning,
            'booked_in_7d' => null,
            'placed_7d'    => null,
        ])
        ->and(GetIncomingHubReturnDeliveryNoteWidget::run($this->warehouse)['data']['_global']['booking_in']['value'])->toBe($rows[3]['booking_in']);

    mobileGoodsInClerk($this, [RolesEnum::GOODS_IN_CLERK, RolesEnum::FULFILMENT_WAREHOUSE_WORKER]);
    $rows = $hub();
    expect(collect($rows)->pluck('key')->all())->toBe(['stock_deliveries', 'partners', 'agents', 'fulfilment', 'returns'])
        ->and($rows[3]['checked'])->toBeNull()
        ->and($rows[3]['placed_7d'])->toBeNull()
        ->and($rows[3]['booked_in_7d'])->toBeInt();

    mobileGoodsInClerk($this, [RolesEnum::RETURNS_CLERK]);
    getGoodsOut('grp.api.mobile.warehouses.goods_in_hub', $this->warehouse->id)->assertForbidden();
});

test('mobile goods in hub labels follow the language the app sends in Accept-Language', function () {
    mobileGoodsInClerk($this);
    $labels = fn (array $headers) => collect(getJson(route('grp.api.mobile.warehouses.goods_in_hub', $this->warehouse->id), ['X-Aiku-App-Key' => 'mobile-test-key', ...$headers])
        ->assertOk()
        ->json('data'))->pluck('label', 'key')->only(['stock_deliveries', 'returns'])->all();

    expect($labels(['Accept-Language' => 'sk']))->toBe(['stock_deliveries' => __('Stock Deliveries', [], 'sk'), 'returns' => __('Returns', [], 'sk')])
        ->and($labels(['Accept-Language' => 'zh-Hans']))->toBe(['stock_deliveries' => __('Stock Deliveries', [], 'zh-Hans'), 'returns' => __('Returns', [], 'zh-Hans')])
        ->and($labels(['Accept-Language' => 'sk'])['stock_deliveries'])->not->toBe('Stock Deliveries')
        ->and($labels(['Accept-Language' => 'el']))->toBe(['stock_deliveries' => 'Stock Deliveries', 'returns' => 'Returns'])
        ->and($labels([]))->toBe(['stock_deliveries' => 'Stock Deliveries', 'returns' => 'Returns'])
        ->and(app()->getLocale())->toBe('en');
});

test('mobile stock delivery list filters by state, source, search and dispatched date, sorts and counts per state', function () {
    mobileGoodsInClerk($this);
    $prefix   = 'MGL'.Str::upper(Str::random(5));
    $baseline = mobileGoodsInList($this->warehouse, ['source' => 'suppliers'])->assertOk()->json('counts');

    $today = mobileGoodsInDelivery($this->organisation, [[mobileGoodsInSko($this->organisation), 10], [mobileGoodsInSko($this->organisation), 4]], $prefix.'-A');
    DispatchStockDelivery::make()->action($today);
    $week = mobileGoodsInReceived(mobileGoodsInDelivery($this->organisation, [[mobileGoodsInSko($this->organisation), 10]], $prefix.'-B'));
    mobileGoodsInChecked($week, 10);
    $older = mobileGoodsInDelivery($this->organisation, [[mobileGoodsInSko($this->organisation), 10]], $prefix.'-C');
    DispatchStockDelivery::make()->action($older);
    $agent = mobileGoodsInDelivery($this->organisation, [[mobileGoodsInSko($this->organisation), 10]], $prefix.'-D');
    DispatchStockDelivery::make()->action($agent);
    $placed = mobileGoodsInDelivery($this->organisation, [[mobileGoodsInSko($this->organisation), 10]], $prefix.'-E');

    DB::table('stock_deliveries')->where('id', $week->id)->update(['dispatched_at' => now()->subDays(3), 'date' => now()->subDays(3)]);
    DB::table('stock_deliveries')->where('id', $older->id)->update(['dispatched_at' => now()->subDays(10), 'date' => now()->subDays(10)]);
    DB::table('stock_deliveries')->where('id', $agent->id)->update(['parent_type' => 'OrgAgent']);
    DB::table('stock_deliveries')->where('id', $placed->id)->update(['state' => StockDeliveryStateEnum::PLACED->value]);

    $references = fn (array $query = []) => collect(mobileGoodsInList($this->warehouse, ['q' => $prefix, ...$query])->assertOk()->json('data'))->pluck('reference')->all();

    expect($references())->toBe([$prefix.'-D', $prefix.'-A', $prefix.'-B', $prefix.'-C'])
        ->and($references(['source' => 'suppliers']))->toBe([$prefix.'-A', $prefix.'-B', $prefix.'-C'])
        ->and($references(['source' => 'agents']))->toBe([$prefix.'-D'])
        ->and($references(['state' => 'checked']))->toBe([$prefix.'-B'])
        ->and($references(['state' => 'placed']))->toBe([$prefix.'-E'])
        ->and($references(['dispatched' => 'today', 'source' => 'suppliers']))->toBe([$prefix.'-A'])
        ->and($references(['dispatched' => 'week', 'source' => 'suppliers']))->toBe([$prefix.'-A', $prefix.'-B'])
        ->and($references(['dispatched' => 'older']))->toBe([$prefix.'-C'])
        ->and($references(['q' => $prefix.'-C']))->toBe([$prefix.'-C'])
        ->and($references(['sort' => 'reference']))->toBe([$prefix.'-A', $prefix.'-B', $prefix.'-C', $prefix.'-D'])
        ->and($references(['sort' => '-dispatched_at', 'source' => 'suppliers']))->toBe([$prefix.'-A', $prefix.'-B', $prefix.'-C'])
        ->and($references(['sort' => '-number_items', 'source' => 'suppliers'])[0])->toBe($prefix.'-A');

    $response = mobileGoodsInList($this->warehouse, ['q' => $prefix.'-B', 'per_page' => 1])
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('meta.per_page', 1)
        ->assertJsonPath('data.0.id', $week->id)
        ->assertJsonPath('data.0.agent_reference', null)
        ->assertJsonPath('data.0.supplier.type', 'OrgSupplier')
        ->assertJsonPath('data.0.supplier.name', $week->parent_name)
        ->assertJsonPath('data.0.source', 'suppliers')
        ->assertJsonPath('data.0.state', 'checked')
        ->assertJsonPath('data.0.state_label', 'Checked')
        ->assertJsonPath('data.0.received_at', $week->refresh()->received_at->toIso8601String())
        ->assertJsonPath('data.0.number_items', 1)
        ->assertJsonPath('data.0.number_checked', 1)
        ->assertJsonPath('data.0.number_placed', 0);

    expect(array_keys($response->json('data.0')))->toBe(['id', 'reference', 'agent_reference', 'supplier', 'source', 'state', 'state_label', 'dispatched_at', 'received_at', 'estimated_receiving_date', 'number_items', 'number_checked', 'number_placed']);

    $counts = mobileGoodsInList($this->warehouse, ['source' => 'suppliers'])->json('counts');
    expect($counts['dispatched'])->toBe($baseline['dispatched'] + 2)
        ->and($counts['checked'])->toBe($baseline['checked'] + 1)
        ->and($counts['placed'])->toBe($baseline['placed'] + 1)
        ->and($counts['active'])->toBe($baseline['active'] + 3)
        ->and(array_keys($counts))->toBe(['dispatched', 'received', 'checked', 'booking_in', 'booked_in', 'placed', 'not_received', 'cancelled', 'active']);

    mobileGoodsInList($this->warehouse, ['state' => 'nope', 'source' => 'agent', 'dispatched' => 'month', 'sort' => 'parent_name'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['state', 'source', 'dispatched', 'sort']);

    mobileGoodsInClerk($this, [RolesEnum::RETURNS_CLERK]);
    mobileGoodsInList($this->warehouse)->assertForbidden();
});

test('mobile stock delivery list sorts by supplier and expected date and filters by delivery date from and to', function () {
    mobileGoodsInClerk($this);
    $prefix     = 'MGR'.Str::upper(Str::random(5));
    $deliveries = [];
    foreach (['A' => ['Zulu', '2026-04-01 12:00:00', '2026-05-03'], 'B' => ['Alpha', '2026-04-03 12:00:00', '2026-05-01'], 'C' => ['Mike', '2026-04-05 12:00:00', null]] as $suffix => [$supplier, $date, $expected]) {
        $stockDelivery = mobileGoodsInDelivery($this->organisation, [[mobileGoodsInSko($this->organisation), 1]], $prefix.'-'.$suffix);
        DispatchStockDelivery::make()->action($stockDelivery);
        DB::table('stock_deliveries')->where('id', $stockDelivery->id)->update([
            'parent_name' => $supplier.' '.$prefix,
            'date'        => $date,
            'data'        => json_encode(array_filter([...($stockDelivery->refresh()->data ?? []), 'estimated_receiving_date' => $expected])),
        ]);
        $deliveries[$suffix] = $prefix.'-'.$suffix;
    }

    $references = fn (array $query = []) => collect(mobileGoodsInList($this->warehouse, ['q' => $prefix, ...$query])->assertOk()->json('data'))->pluck('reference')->all();

    expect($references())->toBe([$deliveries['C'], $deliveries['B'], $deliveries['A']])
        ->and($references(['sort' => 'supplier']))->toBe([$deliveries['B'], $deliveries['C'], $deliveries['A']])
        ->and($references(['sort' => '-supplier']))->toBe([$deliveries['A'], $deliveries['C'], $deliveries['B']])
        ->and($references(['sort' => 'estimated_receiving_date']))->toBe([$deliveries['B'], $deliveries['A'], $deliveries['C']])
        ->and($references(['sort' => '-estimated_receiving_date']))->toBe([$deliveries['A'], $deliveries['B'], $deliveries['C']])
        ->and($references(['from' => '2026-04-02', 'to' => '2026-04-04']))->toBe([$deliveries['B']])
        ->and($references(['from' => '2026-04-03']))->toBe([$deliveries['C'], $deliveries['B']])
        ->and($references(['to' => '2026-04-01']))->toBe([$deliveries['A']]);

    mobileGoodsInList($this->warehouse, ['from' => 'yesterday', 'sort' => 'expected'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['from', 'sort']);

    mobileGoodsInList($this->warehouse, ['from' => '2026-04-03', 'to' => '2026-04-02'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['to']);
});

test('mobile stock delivery detail sends no money, this warehouse locations, items not done first and actions by state and right', function () {
    $foreignOrganisation = Organisation::where('id', '!=', $this->organisation->id)->where('group_id', $this->group->id)->orderBy('id')->first()
        ?? StoreOrganisation::make()->action($this->group, array_merge(Organisation::factory()->definition(), ['code' => 'mgi'.random_int(1000, 9999), 'type' => OrganisationTypeEnum::SHOP]));
    $otherWarehouse = mobileGoodsInOtherWarehouse($this->organisation);
    $prefix         = 'MGT'.Str::upper(Str::random(5));
    $linked         = mobileGoodsInSko($this->organisation, 1, $prefix.'B');
    $unlinked       = mobileGoodsInSko($this->organisation, 6, $prefix.'A');
    $picking        = mobileGoodsInLink($linked, mobileGoodsInLocation($this->warehouse), 4, true);
    mobileGoodsInLink($linked, mobileGoodsInLocation($otherWarehouse), 9);

    $stockDelivery = mobileGoodsInDelivery($this->organisation, [[$linked, 10], [$unlinked, 9]]);
    DispatchStockDelivery::make()->action($stockDelivery);

    mobileGoodsInClerk($this);
    mobileGoodsInShow($this->warehouse, $stockDelivery)->assertOk()->assertJsonPath('data.available_actions', [
        ['key' => 'receive', 'label' => 'Mark as received'],
        ['key' => 'checklist', 'label' => 'Checklist'],
    ]);

    $stockDelivery = ReceiveStockDelivery::make()->action($stockDelivery->refresh())->refresh();
    $linkedItem    = $stockDelivery->items()->where('org_stock_id', $linked->id)->firstOrFail();
    $linkedItem    = SetStockDeliveryItemCheckedQuantity::make()->action($linkedItem, ['unit_quantity_checked' => 10]);
    UpsertStockDeliveryItemPlaced::make()->action($linkedItem, ['quantity' => 10, 'location_org_stock_id' => $picking->id]);

    $response = mobileGoodsInShow($this->warehouse, $stockDelivery)
        ->assertOk()
        ->assertJsonPath('data.id', $stockDelivery->id)
        ->assertJsonPath('data.state', 'checked')
        ->assertJsonPath('data.supplier.type', 'OrgSupplier')
        ->assertJsonPath('data.source', 'suppliers')
        ->assertJsonPath('data.summary.number_items', 2)
        ->assertJsonPath('data.summary.number_checked', 1)
        ->assertJsonPath('data.summary.number_placed', 1)
        ->assertJsonPath('data.summary.number_not_received', 0)
        ->assertJsonPath('data.items.0.sko.code', $unlinked->code)
        ->assertJsonPath('data.items.0.sko.packed_in', 6)
        ->assertJsonPath('data.items.0.sko_expected', 1.5)
        ->assertJsonPath('data.items.0.sko_expected_label', '1 3/6')
        ->assertJsonPath('data.items.0.has_picking_location', false)
        ->assertJsonPath('data.items.0.locations', [])
        ->assertJsonPath('data.items.1.sko.code', $linked->code)
        ->assertJsonPath('data.items.1.state', 'placed')
        ->assertJsonPath('data.items.1.sko_placed', 10)
        ->assertJsonPath('data.items.1.sko_to_place', 0)
        ->assertJsonPath('data.items.1.has_picking_location', true)
        ->assertJsonPath('data.items.1.locations', [[
            'location_org_stock_id'   => $picking->id,
            'location'                => ['id' => $picking->location_id, 'code' => $picking->location->code],
            'type'                    => 'picking',
            'quantity'                => 14,
            'quantity_label'          => '14',
            'is_default_wholesale'    => true,
            'is_default_dropshipping' => true,
            'version'                 => (int) $picking->refresh()->version,
        ]])
        ->assertJsonPath('data.items.1.placements.0.quantity', 10)
        ->assertJsonPath('data.items.1.placements.0.location.id', $picking->location_id)
        ->assertJsonPath('data.available_actions', [
            ['key' => 'check', 'label' => 'Check'],
            ['key' => 'place', 'label' => 'Place'],
            ['key' => 'checklist', 'label' => 'Checklist'],
        ]);

    $moneyKeys = collect(mobileGoodsInKeys($response->json('data')))->filter(fn (string $key) => Str::contains($key, ['amount', 'cost', 'price', 'value', 'currency', 'exchange']));
    expect($moneyKeys->all())->toBe([]);

    $stockDelivery->refresh()->update(['state' => StockDeliveryStateEnum::RECEIVED]);
    mobileGoodsInClerk($this, [RolesEnum::GOODS_IN_SUPERVISOR]);
    mobileGoodsInShow($this->warehouse, $stockDelivery)->assertJsonPath('data.available_actions.0', ['key' => 'unreceive', 'label' => 'Unmark as received']);

    mobileGoodsInClerk($this, [], ["incoming.{$this->warehouse->id}.view"]);
    mobileGoodsInShow($this->warehouse, $stockDelivery)->assertOk()->assertJsonPath('data.available_actions', []);

    DB::table('stock_deliveries')->where('id', $stockDelivery->id)->update(['organisation_id' => $foreignOrganisation->id]);
    mobileGoodsInShow($this->warehouse, $stockDelivery)->assertNotFound();
    DB::table('stock_deliveries')->where('id', $stockDelivery->id)->update(['organisation_id' => $this->organisation->id]);
});

test('mobile receive marks as received through the web, refuses the wrong state, a partner delivery and a user without the book in right', function () {
    $stockDelivery = mobileGoodsInDelivery($this->organisation, [[mobileGoodsInSko($this->organisation), 10]]);
    DispatchStockDelivery::make()->action($stockDelivery);
    [, , $shop]   = createShop();
    $deliveryNote = goodsOutDeliveryNote(createOrder(createCustomer($shop), createProduct($shop)[1]), $this->warehouse);

    mobileGoodsInClerk($this, [], ["incoming.{$this->warehouse->id}.view"]);
    mobileGoodsInPost('receive', $this->warehouse, $stockDelivery)->assertForbidden();

    mobileGoodsInClerk($this);
    mobileGoodsInPost('receive', $this->warehouse, $stockDelivery)
        ->assertOk()
        ->assertJsonPath('data.state', 'received')
        ->assertJsonPath('data.items.0.state', 'received')
        ->assertJsonPath('result.status', 'done')
        ->assertJsonPath('result.message', 'Marked as received.');

    mobileGoodsInPost('receive', $this->warehouse, $stockDelivery)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['state' => 'You can not receive this stock delivery with state received']);

    $partner = mobileGoodsInDelivery($this->organisation, [[mobileGoodsInSko($this->organisation), 10]]);
    DB::table('stock_deliveries')->where('id', $partner->id)->update(['delivery_note_id' => $deliveryNote->id]);

    expect($partner->refresh()->isManagedByPartner())->toBeTrue();
    mobileGoodsInShow($this->warehouse, $partner)->assertJsonPath('data.available_actions', []);
    mobileGoodsInPost('receive', $this->warehouse, $partner)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['state' => 'This delivery is managed by the partner until they dispatch it']);
    expect($partner->refresh()->state)->toBe(StockDeliveryStateEnum::IN_PROCESS);
});

test('mobile unreceive is for goods in supervisors and only while the delivery is received', function () {
    $stockDelivery = mobileGoodsInReceived(mobileGoodsInDelivery($this->organisation, [[mobileGoodsInSko($this->organisation), 10]]));

    mobileGoodsInClerk($this);
    mobileGoodsInPost('unreceive', $this->warehouse, $stockDelivery)->assertForbidden();

    mobileGoodsInClerk($this, [RolesEnum::GOODS_IN_SUPERVISOR]);
    mobileGoodsInPost('unreceive', $this->warehouse, $stockDelivery)
        ->assertOk()
        ->assertJsonPath('data.state', 'dispatched')
        ->assertJsonPath('result.status', 'done');

    $stockDelivery = ReceiveStockDelivery::make()->action($stockDelivery->refresh());
    mobileGoodsInChecked($stockDelivery, 10);
    mobileGoodsInPost('unreceive', $this->warehouse, $stockDelivery)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['state' => 'You can not unmark this stock delivery as received with state checked']);
});

test('mobile check counts in decimal SKOs, 0 is not received and a value below what is placed is raised and said', function () {
    $orgStock      = mobileGoodsInSko($this->organisation, 6);
    $stockDelivery = mobileGoodsInReceived(mobileGoodsInDelivery($this->organisation, [[$orgStock, 12], [mobileGoodsInSko($this->organisation), 4]]));
    [$item, $other] = $stockDelivery->items()->orderBy('id')->get()->all();
    $check         = fn (StockDeliveryItem $line, mixed $quantity) => mobileGoodsInPost('items.check', $this->warehouse, $stockDelivery, ['sko_quantity' => $quantity], $line);

    mobileGoodsInClerk($this);
    $before = mobileGoodsInShow($this->warehouse, $stockDelivery)->assertOk();
    expect(collect($before->json('data.items'))->pluck('sko_checked')->all())->toBe([null, null]);

    $check($item, 1.5)
        ->assertOk()
        ->assertJsonPath('result.status', 'done')
        ->assertJsonPath('result.message', 'Checked 1 3/6.')
        ->assertJsonPath('data.state', 'checked');
    expect((float) $item->refresh()->unit_quantity_checked)->toBe(9.0)
        ->and($item->state)->toBe(StockDeliveryItemStateEnum::CHECKED);

    $check($item, 1.5)->assertOk()->assertJsonPath('result.status', 'nothing_changed');

    $notReceived = $check($other, 0)->assertOk()->assertJsonPath('result.message', 'Marked as not received.');
    expect($other->refresh()->state)->toBe(StockDeliveryItemStateEnum::NOT_RECEIVED)
        ->and(collect($notReceived->json('data.items'))->firstWhere('id', $other->id)['sko_checked'])->toBe(0);

    UpsertStockDeliveryItemPlaced::make()->action($item->refresh(), ['quantity' => 1, 'location_org_stock_id' => mobileGoodsInLink($orgStock, mobileGoodsInLocation($this->warehouse))->id]);
    $clamped = $check($item, 0.5)
        ->assertOk()
        ->assertJsonPath('result.message', '1 already placed, so the checked quantity stays at 1.');
    expect(collect($clamped->json('data.items'))->firstWhere('id', $item->id)['sko_checked'])->toBe(1)
        ->and((float) $item->refresh()->unit_quantity_checked)->toBe(6.0);

    $check($item, -1)->assertUnprocessable()->assertJsonValidationErrors(['sko_quantity']);

    $foreignItem = mobileGoodsInDelivery($this->organisation, [[mobileGoodsInSko($this->organisation), 4]])->items()->firstOrFail();
    $check($foreignItem, 1)->assertNotFound();

    mobileGoodsInClerk($this, [], ["incoming.{$this->warehouse->id}.view"]);
    $check($item, 1)->assertForbidden();
});

test('mobile check all checks every item not counted yet as expected', function () {
    $stockDelivery = mobileGoodsInReceived(mobileGoodsInDelivery($this->organisation, [[mobileGoodsInSko($this->organisation), 10], [mobileGoodsInSko($this->organisation), 4], [mobileGoodsInSko($this->organisation), 7]]));
    [$first, $second, $third] = $stockDelivery->items()->orderBy('id')->get()->all();
    SetStockDeliveryItemCheckedQuantity::make()->action($third, ['unit_quantity_checked' => 5]);

    mobileGoodsInClerk($this);
    mobileGoodsInPost('check_all', $this->warehouse, $stockDelivery)
        ->assertOk()
        ->assertJsonPath('result.status', 'done')
        ->assertJsonPath('result.message', '2 items checked as expected.')
        ->assertJsonPath('data.summary.number_checked', 3);

    expect((float) $first->refresh()->unit_quantity_checked)->toBe(10.0)
        ->and((float) $second->refresh()->unit_quantity_checked)->toBe(4.0)
        ->and((float) $third->refresh()->unit_quantity_checked)->toBe(5.0);

    mobileGoodsInPost('check_all', $this->warehouse, $stockDelivery)->assertOk()->assertJsonPath('result.status', 'nothing_changed');

    $dispatched = mobileGoodsInDelivery($this->organisation, [[mobileGoodsInSko($this->organisation), 10]]);
    DispatchStockDelivery::make()->action($dispatched);
    mobileGoodsInPost('check_all', $this->warehouse, $dispatched)->assertUnprocessable()->assertJsonValidationErrors(['state']);
});

test('mobile place puts stock only in a working location of this warehouse that takes the SKO, once per request id', function () {
    $orgStock      = mobileGoodsInSko($this->organisation);
    $stockDelivery = mobileGoodsInReceived(mobileGoodsInDelivery($this->organisation, [[$orgStock, 10]]));
    $item          = mobileGoodsInChecked($stockDelivery, 10);
    $link          = mobileGoodsInLink($orgStock, mobileGoodsInLocation($this->warehouse), 2);
    $place         = fn (array $body) => mobileGoodsInPost('items.place', $this->warehouse, $stockDelivery, ['client_request_id' => (string) Str::uuid(), ...$body], $item);
    $elsewhere     = mobileGoodsInLocation(mobileGoodsInOtherWarehouse($this->organisation));

    mobileGoodsInClerk($this);

    $place(['quantity' => 1, 'location_id' => $elsewhere->id])->assertUnprocessable()->assertJsonValidationErrors(['location_id' => 'That location is not in this warehouse.']);
    $place(['quantity' => 1, 'location_id' => mobileGoodsInLocation($this->warehouse, ['status' => LocationStatusEnum::BROKEN])->id])->assertUnprocessable()->assertJsonValidationErrors(['location_id' => 'This location is not operational.']);
    $place(['quantity' => 1, 'location_id' => mobileGoodsInLocation($this->warehouse, ['allow_stocks' => false])->id])->assertUnprocessable()->assertJsonValidationErrors(['location_id' => 'This location does not take SKOs.']);
    $place(['quantity' => 1, 'location_org_stock_id' => mobileGoodsInLink(mobileGoodsInSko($this->organisation), mobileGoodsInLocation($this->warehouse))->id])->assertUnprocessable()->assertJsonValidationErrors(['location_org_stock_id' => 'That location does not hold this SKO.']);
    $place(['quantity' => 1, 'location_org_stock_id' => $link->id, 'location_id' => $link->location_id])->assertUnprocessable()->assertJsonValidationErrors(['location_org_stock_id']);
    $place(['quantity' => 1])->assertUnprocessable()->assertJsonValidationErrors(['location_org_stock_id', 'location_id']);
    $place(['quantity' => 11, 'location_org_stock_id' => $link->id])->assertUnprocessable()->assertJsonValidationErrors(['quantity' => 'You can not place more than the checked quantity (10 remaining)']);
    mobileGoodsInPost('items.place', $this->warehouse, $stockDelivery, ['quantity' => 1, 'location_org_stock_id' => $link->id], $item)->assertUnprocessable()->assertJsonValidationErrors(['client_request_id']);
    expect($item->sowings()->count())->toBe(0);

    $requestId = (string) Str::uuid();
    $body      = ['quantity' => 3, 'location_org_stock_id' => $link->id, 'client_request_id' => $requestId];
    mobileGoodsInPost('items.place', $this->warehouse, $stockDelivery, $body, $item)
        ->assertOk()
        ->assertJsonPath('result.status', 'done')
        ->assertJsonPath('result.message', 'Placed 3 in '.$link->location->code.'.')
        ->assertJsonPath('data.items.0.sko_placed', 3)
        ->assertJsonPath('data.items.0.sko_to_place', 7);
    mobileGoodsInPost('items.place', $this->warehouse, $stockDelivery, $body, $item)
        ->assertOk()
        ->assertJsonPath('result.status', 'nothing_changed')
        ->assertJsonPath('result.message', 'Already placed.')
        ->assertJsonPath('data.items.0.sko_placed', 3);

    expect($item->sowings()->count())->toBe(1)
        ->and((float) $link->refresh()->quantity)->toBe(5.0)
        ->and($item->refresh()->sowings()->first()->sower_user_id)->not->toBeNull();

    $newLocation = mobileGoodsInLocation($this->warehouse);
    $place(['quantity' => 2, 'location_id' => $newLocation->id, 'set_as_picking_location' => true])->assertOk();
    $newLink = LocationOrgStock::where('org_stock_id', $orgStock->id)->where('location_id', $newLocation->id)->firstOrFail();
    expect((float) $newLink->quantity)->toBe(2.0)
        ->and($newLink->default_wholesale_picking_location)->toBeTrue()
        ->and($newLink->default_dropshipping_picking_location)->toBeTrue();

    $thirdLocation = mobileGoodsInLocation($this->warehouse);
    $place(['quantity' => 1, 'location_id' => $thirdLocation->id, 'set_as_picking_location' => true])->assertOk()->assertJsonPath('result.message', 'Placed 1 in '.$thirdLocation->code.'.');
    $thirdLink = LocationOrgStock::where('org_stock_id', $orgStock->id)->where('location_id', $thirdLocation->id)->firstOrFail();
    expect($thirdLink->default_wholesale_picking_location)->toBeFalse()
        ->and($newLink->refresh()->default_wholesale_picking_location)->toBeTrue();
});

test('mobile place all places what is left, and placing is refused once nothing is left or the delivery is not booking in', function () {
    $orgStock      = mobileGoodsInSko($this->organisation, 6);
    $stockDelivery = mobileGoodsInReceived(mobileGoodsInDelivery($this->organisation, [[$orgStock, 12]]));
    $item          = mobileGoodsInChecked($stockDelivery, 10);
    $link          = mobileGoodsInLink($orgStock, mobileGoodsInLocation($this->warehouse), 0);
    $placeAll      = fn () => mobileGoodsInPost('items.place_all', $this->warehouse, $stockDelivery, ['location_org_stock_id' => $link->id, 'client_request_id' => (string) Str::uuid()], $item);

    mobileGoodsInClerk($this);
    $placeAll()
        ->assertOk()
        ->assertJsonPath('result.message', 'Placed 1 4/6 in '.$link->location->code.'.')
        ->assertJsonPath('data.state', 'booked_in')
        ->assertJsonPath('data.items.0.state', 'placed');

    expect(round((float) $link->refresh()->quantity, 4))->toBe(round(10 / 6, 4))
        ->and((float) $item->refresh()->unit_quantity_placed)->toBe(10.0);

    $placeAll()->assertUnprocessable()->assertJsonValidationErrors(['state']);

    $dispatched = mobileGoodsInDelivery($this->organisation, [[$orgStock, 6]]);
    DispatchStockDelivery::make()->action($dispatched);
    mobileGoodsInPost('items.place_all', $this->warehouse, $dispatched, ['location_org_stock_id' => $link->id, 'client_request_id' => (string) Str::uuid()], $dispatched->items()->firstOrFail())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['state']);
});

test('mobile checklist records a pass, needs a note to fail and opens an incident', function () {
    $orgStock      = mobileGoodsInSko($this->organisation);
    $stockDelivery = mobileGoodsInReceived(mobileGoodsInDelivery($this->organisation, [[$orgStock, 10]]));
    $stockDelivery->items()->firstOrFail()->supplierProduct->update(['packing_method' => SupplierPackingMethodEnum::SUPPLIER_PACKS]);

    $user = mobileGoodsInClerk($this);
    $checklist = mobileGoodsInShow($this->warehouse, $stockDelivery)->assertOk()->json('data.checklist');
    $key       = $checklist['sections'][0]['checks'][0]['key'];

    expect($checklist['total'])->toBeGreaterThan(0)
        ->and($checklist['sections'][0]['checks'][0]['result'])->toBeNull()
        ->and(collect($checklist['incident_types'])->pluck('value'))->toContain('damaged');

    mobileGoodsInPost('checklist', $this->warehouse, $stockDelivery, ['key' => $key, 'result' => 'failed'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['body']);
    mobileGoodsInPost('checklist', $this->warehouse, $stockDelivery, ['key' => 'supplier:0:nope', 'result' => 'passed'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['key']);

    $check = mobileGoodsInPost('checklist', $this->warehouse, $stockDelivery, ['key' => $key, 'result' => 'failed', 'body' => 'Wrong labels', 'incident_type' => 'packing_labels'])
        ->assertOk()
        ->assertJsonPath('result.status', 'done')
        ->json('data.checklist.sections.0.checks.0');

    expect($check['result'])->toBe('failed')
        ->and($check['checked_by'])->toBe(['id' => $user->id, 'name' => $user->contact_name ?: $user->username])
        ->and($check['checked_at'])->not->toBeNull()
        ->and(Arr::get($stockDelivery->refresh()->data, 'goods_in_checks.'.$key.'.incident_id'))->not->toBeNull();

    mobileGoodsInPost('checklist', $this->warehouse, $stockDelivery, ['key' => $key, 'result' => 'passed'])
        ->assertOk()
        ->assertJsonPath('data.checklist.sections.0.checks.0.result', 'passed');
});

test('mobile scan finds a stock delivery by reference or agent reference and lets a goods in user scan', function () {
    $stockDelivery = mobileGoodsInDelivery($this->organisation, [[mobileGoodsInSko($this->organisation), 10]]);
    $stockDelivery->update(['agent_reference' => 'Stuffing '.Str::upper(Str::random(6))]);
    $scan = fn (string $code) => getGoodsOut('grp.api.mobile.warehouses.scan', $this->warehouse->id, ['code' => $code]);

    mobileGoodsInClerk($this, [], ["incoming.{$this->warehouse->id}.view"]);
    $scan(strtolower($stockDelivery->reference))
        ->assertOk()
        ->assertJsonPath('data.type', 'stock_delivery')
        ->assertJsonPath('data.sko', null)
        ->assertJsonPath('data.location', null)
        ->assertJsonPath('data.delivery_note', null)
        ->assertJsonPath('data.stock_delivery', [
            'id'          => $stockDelivery->id,
            'reference'   => $stockDelivery->reference,
            'state'       => 'in_process',
            'state_label' => 'In process',
        ]);
    $scan($stockDelivery->agent_reference)->assertOk()->assertJsonPath('data.stock_delivery.id', $stockDelivery->id);
    $scan('NOTHING-'.Str::random(8))->assertOk()->assertJsonPath('data.type', 'unknown')->assertJsonPath('data.stock_delivery', null);

    mobileGoodsInClerk($this, [], ["locations.{$this->warehouse->id}.view"]);
    $scan($stockDelivery->reference)->assertOk()->assertJsonPath('data.type', 'unknown');
});
