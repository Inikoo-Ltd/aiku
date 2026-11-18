<?php

/*
 * Author Louis Perez
 * Created on 18-11-2026-08h-51m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Feature;

use App\Actions\Fulfilment\PalletDelivery\StorePalletDelivery;
use App\Actions\Fulfilment\PalletReturn\Notifications\SendPalletReturnNotification;
use App\Actions\Goods\StockFamily\StoreStockFamily;
use App\Actions\GoodsIn\StockDelivery\StoreStockDelivery;
use App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock;
use App\Actions\Inventory\OrgStockFamily\StoreOrgStockFamily;
use App\Actions\Inventory\UI\ShowInventoryDashboard;
use App\Actions\Inventory\Warehouse\StoreWarehouse;
use App\Actions\SupplyChain\Supplier\StoreSupplier;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Fulfilment\PalletDelivery\PalletDeliveryStateEnum;
use App\Enums\GoodsIn\ReturnDeliveryNote\ReturnDeliveryNoteStateEnum;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Inventory\LocationStock\LocationStockTypeEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\Inventory\OrgStockFamily\OrgStockFamilyStateEnum;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Models\Goods\StockFamily;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

require_once __DIR__.'/../Support/MobileMyWorkHelpers.php';

const MSD_ROW_KEYS = ['type', 'id', 'code', 'title', 'subtitle', 'state', 'state_label', 'quantity', 'image_url', 'type_group'];

const MSD_MODULE_LISTS = [
    'view_goods_out'  => 'grp.api.mobile.warehouses.delivery_notes',
    'view_goods_in'   => 'grp.api.mobile.warehouses.goods_in_hub',
    'view_inventory'  => 'grp.api.mobile.warehouses.org_stocks',
    'view_locations'  => 'grp.api.mobile.warehouses.locations',
    'view_returns'    => 'grp.api.mobile.warehouses.returns',
    'view_fulfilment' => 'grp.api.mobile.warehouses.pallets.index',
];

const MSD_WEB_PAGES = [
    'view_goods_out' => 'grp.org.warehouses.show.dispatching.delivery-notes',
    'view_goods_in'  => 'grp.org.warehouses.show.incoming.stock_deliveries.index',
    'view_inventory' => 'grp.org.warehouses.show.inventory.org_stocks.negative_stocks.index',
];

/**
 * @param array<string, mixed> $query
 */
function msdSearch(Warehouse $warehouse, array $query): TestResponse
{
    return getGoodsOut('grp.api.mobile.warehouses.search', $warehouse->id, $query);
}

function msdDashboard(Warehouse $warehouse): TestResponse
{
    return getGoodsOut('grp.api.mobile.warehouses.dashboard', $warehouse->id);
}

/**
 * @return array<string, bool>
 */
function msdAbilities(Warehouse $warehouse): array
{
    return collect(getGoodsOut('grp.api.mobile.warehouses', [])->assertOk()->json('data'))->firstWhere('id', $warehouse->id)['can'];
}

/**
 * @return array<string, array<int, int|string>>
 */
function msdGroupCodes(TestResponse $response): array
{
    return collect($response->json('data.groups'))->mapWithKeys(fn (array $group) => [$group['type'] => array_column($group['rows'], 'code')])->all();
}

function msdStockDelivery(Organisation $organisation, string $reference): StockDelivery
{
    $supplier      = StoreSupplier::make()->action(parent: $organisation->group, modelData: Supplier::factory()->definition());
    $orgSupplier   = $supplier->orgSuppliers()->where('organisation_id', $organisation->id)->firstOrFail();
    $stockDelivery = StoreStockDelivery::make()->action($orgSupplier, ['reference' => $reference, 'date' => date('Y-m-d')]);
    $stockDelivery->update(['state' => StockDeliveryStateEnum::DISPATCHED, 'dispatched_at' => now()]);

    return $stockDelivery->refresh();
}

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    [$this->organisation, $this->user, $this->shop] = createShop();
    $this->group     = $this->organisation->group;
    $this->customer  = createCustomer($this->shop);
    $this->product   = createProduct($this->shop)[1];
    $this->order     = createOrder($this->customer, $this->product);
    $this->warehouse = StoreWarehouse::make()->action($this->organisation, Warehouse::factory()->definition());

    SendPalletReturnNotification::shouldRun()->andReturn();
});

test('every module ability equals what the module list lets the user open', function (array $permissions, array $expected) {
    $otherWarehouse = StoreWarehouse::make()->action($this->organisation, Warehouse::factory()->definition());
    $permissions    = array_map(fn (string $permission) => strtr($permission, [
        ':w' => $this->warehouse->id,
        ':x' => $otherWarehouse->id,
        ':o' => $this->organisation->id,
    ]), $permissions);

    $user = actingAsGoodsOutUser($this->group, $this->warehouse, [], $permissions);
    $can  = msdAbilities($this->warehouse);

    expect(collect($expected)->map(fn (bool $value, string $ability) => $can[$ability])->all())->toBe($expected);

    foreach (MSD_MODULE_LISTS as $ability => $route) {
        $status = getGoodsOut($route, $this->warehouse->id)->status();
        expect([$ability => $status])->toBe([$ability => $can[$ability] ? 200 : 403]);
    }

    actingAs($user);
    foreach (MSD_WEB_PAGES as $ability => $route) {
        $status = getJson(route($route, [$this->organisation->slug, $this->warehouse->slug]))->status();
        expect([$ability => $status])->toBe([$ability => $can[$ability] ? 200 : 403]);
    }
})->with([
    'supervisor of dispatching only' => [['supervisor-dispatching.:w'], [
        'view_goods_out' => true, 'view_goods_in' => false, 'view_inventory' => false, 'view_locations' => false, 'view_returns' => false, 'view_fulfilment' => false,
    ]],
    'supervisor of goods in only' => [['supervisor-incoming.:w'], [
        'view_goods_out' => false, 'view_goods_in' => true, 'view_inventory' => false, 'view_locations' => true, 'view_returns' => false, 'view_fulfilment' => true,
    ]],
    'fulfilment viewer sees inventory as on the web' => [['fulfilment.:w.view'], [
        'view_goods_out' => true, 'view_goods_in' => false, 'view_inventory' => true, 'view_locations' => false, 'view_returns' => false, 'view_fulfilment' => true,
    ]],
    'stock viewer of another warehouse of the organisation' => [['stocks.:x.view', 'locations.:w.view'], [
        'view_goods_out' => false, 'view_goods_in' => false, 'view_inventory' => true, 'view_locations' => true, 'view_returns' => false, 'view_fulfilment' => false,
    ]],
    'organisation warehouses viewer' => [['warehouses-view.:o', 'exception.:w.dispatching'], [
        'view_goods_out' => false, 'view_goods_in' => false, 'view_inventory' => false, 'view_locations' => true, 'view_returns' => false, 'view_fulfilment' => true,
    ]],
    'organisation inventory viewer' => [['inventory.:o.view', 'exception.:w.dispatching'], [
        'view_goods_out' => false, 'view_goods_in' => false, 'view_inventory' => true, 'view_locations' => true, 'view_returns' => false, 'view_fulfilment' => false,
    ]],
    'returns clerk' => [['returns.:w'], [
        'view_goods_out' => false, 'view_goods_in' => false, 'view_inventory' => false, 'view_locations' => false, 'view_returns' => true, 'view_fulfilment' => false,
    ]],
    'goods in and dispatching viewer' => [['incoming.:w.view', 'dispatching.:w.view'], [
        'view_goods_out' => true, 'view_goods_in' => true, 'view_inventory' => false, 'view_locations' => false, 'view_returns' => true, 'view_fulfilment' => false,
    ]],
]);

test('mobile search under two characters returns no groups but the types the user may search', function () {
    $warehouseId = $this->warehouse->id;
    actingAsGoodsOutUser($this->group, $this->warehouse, [], ["dispatching.$warehouseId.view", "locations.$warehouseId.view"]);

    foreach (['', ' a ', 'a'] as $query) {
        msdSearch($this->warehouse, ['q' => $query])
            ->assertOk()
            ->assertExactJson(['data' => [
                'query'  => trim($query),
                'types'  => ['locations', 'delivery_notes'],
                'exact'  => null,
                'groups' => [],
            ]]);
    }

    msdSearch($this->warehouse, ['q' => str_repeat('a', 65)])->assertUnprocessable();
    msdSearch($this->warehouse, ['q' => 'ab', 'type' => 'orders'])->assertUnprocessable();
});

test('mobile search finds every record type with the list predicate, in the contract order and row shape', function () {
    $token       = 'MSD'.Str::upper(Str::random(6));
    $warehouseId = $this->warehouse->id;
    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::WAREHOUSE_ADMIN], ["fulfilment.$warehouseId.view"]);

    $sko = goodsOutOrgStock($this->group, $this->order, $token.'SKO');
    $sko->update(['state' => OrgStockStateEnum::ACTIVE, 'quantity_in_locations' => 12]);

    $stockFamily = StoreStockFamily::make()->action($this->group, StockFamily::factory()->definition());
    $family      = StoreOrgStockFamily::make()->action($this->organisation, $stockFamily, []);
    $family->update(['code' => $token.'FAM', 'state' => OrgStockFamilyStateEnum::ACTIVE]);

    $location = goodsOutLocation($this->warehouse);
    $location->update(['code' => $token.'LOC']);

    $deliveryNote  = goodsOutDeliveryNote($this->order, $this->warehouse, ['reference' => $token.'DN', 'state' => DeliveryNoteStateEnum::HANDLING, 'number_items' => 6]);
    $stockDelivery = msdStockDelivery($this->organisation, $token.'SD');

    $return = myWorkReturn($this, null);
    $return->update(['reference' => 'R-'.$token]);

    $fulfilmentCustomer = mfoCustomer(mfoFulfilment($this->organisation, $this->warehouse));
    $fulfilmentCustomer->customer->update(['name' => 'Customer '.$token]);
    $pallet = mfoPallet($fulfilmentCustomer, $this->warehouse);
    $pallet->update(['customer_reference' => 'C-'.$token]);
    $palletReturn   = mfoPalletReturn($fulfilmentCustomer, $this->warehouse, [mfoPallet($fulfilmentCustomer, $this->warehouse)]);
    $palletDelivery = StorePalletDelivery::make()->action($fulfilmentCustomer, ['warehouse_id' => $warehouseId]);
    $palletDelivery->update(['warehouse_id' => $warehouseId, 'state' => PalletDeliveryStateEnum::CONFIRMED]);

    $response = msdSearch($this->warehouse, ['q' => strtolower($token)])->assertOk()
        ->assertJsonPath('data.query', strtolower($token))
        ->assertJsonPath('data.exact', null)
        ->assertJsonPath('data.types', ['skos', 'sko_families', 'locations', 'delivery_notes', 'stock_deliveries', 'returns', 'pallets', 'pallet_deliveries', 'pallet_returns']);

    expect(msdGroupCodes($response))->toBe([
        'skos'              => [$sko->code],
        'sko_families'      => [$family->code],
        'locations'         => [$location->code],
        'delivery_notes'    => [$deliveryNote->reference],
        'stock_deliveries'  => [$stockDelivery->reference],
        'returns'           => [$return->reference],
        'pallets'           => [$pallet->reference],
        'pallet_deliveries' => [$palletDelivery->refresh()->reference],
        'pallet_returns'    => [$palletReturn->reference],
    ]);

    $groups = collect($response->json('data.groups'))->keyBy('type');
    foreach ($groups as $group) {
        expect($group['has_more'])->toBeFalse()
            ->and(array_keys($group['rows'][0]))->toBe(MSD_ROW_KEYS);
    }

    expect($groups['skos']['label'])->toBe('SKOs')
        ->and($groups['skos']['rows'][0])->toMatchArray(['type' => 'sko', 'id' => $sko->id, 'title' => $sko->code, 'subtitle' => $sko->name, 'state' => 'active', 'state_label' => 'Active', 'quantity' => 12])
        ->and($groups['delivery_notes']['rows'][0])->toMatchArray(['type' => 'delivery_note', 'id' => $deliveryNote->id, 'state' => 'handling', 'state_label' => 'Picking', 'quantity' => null, 'image_url' => null, 'type_group' => null])
        ->and($groups['delivery_notes']['rows'][0]['subtitle'])->toEndWith('6 items')
        ->and($groups['stock_deliveries']['rows'][0])->toMatchArray(['type' => 'stock_delivery', 'id' => $stockDelivery->id, 'state' => 'dispatched'])
        ->and($groups['returns']['rows'][0])->toMatchArray(['type' => 'return', 'id' => $return->id, 'subtitle' => $this->customer->name])
        ->and($groups['pallets']['rows'][0])->toMatchArray(['type' => 'pallet', 'id' => $pallet->id, 'subtitle' => 'C-'.$token])
        ->and($groups['pallet_deliveries']['rows'][0])->toMatchArray(['type' => 'pallet_delivery', 'id' => $palletDelivery->id, 'state' => 'confirmed'])
        ->and($groups['pallet_returns']['rows'][0])->toMatchArray(['type' => 'pallet_return', 'id' => $palletReturn->id, 'type_group' => 'pallets', 'subtitle' => 'Customer '.$token]);
});

test('mobile search shows five rows per type for all and pages twenty at a time for one type', function () {
    $token       = 'MSP'.Str::upper(Str::random(6));
    $warehouseId = $this->warehouse->id;
    actingAsGoodsOutUser($this->group, $this->warehouse, [], ["locations.$warehouseId.view"]);

    $codes = collect(range(1, 21))->map(function (int $index) use ($token) {
        $location = goodsOutLocation($this->warehouse);
        $location->update(['code' => $token.'-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT)]);

        return $location->code;
    })->all();

    msdSearch($this->warehouse, ['q' => $token])->assertOk()
        ->assertJsonPath('data.groups.0.type', 'locations')
        ->assertJsonPath('data.groups.0.label', 'Locations')
        ->assertJsonPath('data.groups.0.has_more', true)
        ->assertJsonCount(5, 'data.groups.0.rows')
        ->assertJsonPath('data.groups.0.rows.0.code', $codes[0]);

    $firstPage = msdSearch($this->warehouse, ['q' => $token, 'type' => 'locations'])->assertOk()
        ->assertJsonPath('data.groups.0.has_more', true);
    $lastPage = msdSearch($this->warehouse, ['q' => $token, 'type' => 'locations', 'page' => 2])->assertOk()
        ->assertJsonPath('data.groups.0.has_more', false);

    expect(msdGroupCodes($firstPage)['locations'])->toBe(array_slice($codes, 0, 20))
        ->and(msdGroupCodes($lastPage)['locations'])->toBe([$codes[20]]);

    msdSearch($this->warehouse, ['q' => $token, 'type' => 'locations', 'page' => 3])->assertOk()->assertJsonPath('data.groups', []);
});

test('mobile search finds delivery notes of every state by reference prefix or tracking number, those in the warehouse first', function () {
    $token       = 'MSN'.Str::upper(Str::random(6));
    $warehouseId = $this->warehouse->id;
    actingAsGoodsOutUser($this->group, $this->warehouse, [], ["dispatching.$warehouseId.view"]);

    $note = fn (string $suffix, array $attributes) => goodsOutDeliveryNote($this->order, $this->warehouse, array_merge(['reference' => $token.$suffix], $attributes));

    $handling  = $note('HAN', ['state' => DeliveryNoteStateEnum::HANDLING, 'date' => now()->subDays(2)]);
    $tracked   = goodsOutDeliveryNote($this->order, $this->warehouse, ['tracking_number' => 'TRK'.$token.'X', 'state' => DeliveryNoteStateEnum::UNASSIGNED, 'date' => now()->subDay()]);
    $cancelled = $note('CAN', ['state' => DeliveryNoteStateEnum::CANCELLED, 'date' => now()->subDays(5)]);
    $recent    = $note('REC', ['state' => DeliveryNoteStateEnum::DISPATCHED, 'date' => now()->subDays(10), 'dispatched_at' => now()->subDays(10)]);
    $old       = $note('OLD', ['state' => DeliveryNoteStateEnum::DISPATCHED, 'date' => now()->subDays(400), 'dispatched_at' => now()->subDays(400)]);
    goodsOutDeliveryNote($this->order, $this->warehouse, ['reference' => 'X'.$token.'MID']);

    $response = msdSearch($this->warehouse, ['q' => strtolower($token)])->assertOk()
        ->assertJsonPath('data.exact', null)
        ->assertJsonPath('data.groups.0.has_more', false);

    expect(msdGroupCodes($response)['delivery_notes'])->toBe([
        $tracked->reference,
        $handling->reference,
        $cancelled->reference,
        $recent->reference,
        $old->reference,
    ]);

    msdSearch($this->warehouse, ['q' => $token.'_'])->assertOk()->assertJsonPath('data.groups', []);
    msdSearch($this->warehouse, ['q' => $token.'%'])->assertOk()->assertJsonPath('data.groups', []);

    $exact = msdSearch($this->warehouse, ['q' => strtolower($old->reference)])->assertOk()
        ->assertJsonPath('data.exact.type', 'delivery_note')
        ->assertJsonPath('data.exact.id', $old->id)
        ->assertJsonPath('data.exact.state', 'dispatched')
        ->assertJsonPath('data.exact.state_label', 'Dispatched');
    expect(msdGroupCodes($exact))->toBe(['delivery_notes' => [$old->reference]]);
});

test('mobile search offers only the types the user may open', function () {
    $token       = 'MSR'.Str::upper(Str::random(6));
    $warehouseId = $this->warehouse->id;
    $location    = goodsOutLocation($this->warehouse);
    $location->update(['code' => $token.'LOC']);
    goodsOutDeliveryNote($this->order, $this->warehouse, ['reference' => $token.'DN']);

    actingAsGoodsOutUser($this->group, $this->warehouse, [], ["locations.$warehouseId.view"]);

    msdSearch($this->warehouse, ['q' => $token])->assertOk()
        ->assertJsonPath('data.types', ['locations'])
        ->assertJsonCount(1, 'data.groups')
        ->assertJsonPath('data.groups.0.type', 'locations');
    msdSearch($this->warehouse, ['q' => strtolower($location->code)])->assertOk()
        ->assertJsonPath('data.exact.type', 'location')
        ->assertJsonPath('data.exact.id', $location->id)
        ->assertJsonPath('data.exact.code', $location->code);
    msdSearch($this->warehouse, ['q' => $token.'DN'])->assertOk()->assertJsonPath('data.exact', null);
    msdSearch($this->warehouse, ['q' => $token, 'type' => 'delivery_notes'])->assertForbidden();

    actingAsGoodsOutUser($this->group, $this->warehouse, [], ["supervisor-dispatching.$warehouseId"]);
    msdSearch($this->warehouse, ['q' => $token])->assertOk()->assertJsonPath('data.types', ['delivery_notes']);

    actingAsGoodsOutUser($this->group, $this->warehouse, [], ["exception.$warehouseId.dispatching"]);
    msdSearch($this->warehouse, ['q' => $token])->assertForbidden();

    $otherWarehouse = StoreWarehouse::make()->action($this->organisation, Warehouse::factory()->definition());
    msdSearch($otherWarehouse, ['q' => $token])->assertNotFound();
    msdDashboard($otherWarehouse)->assertNotFound();
});

test('mobile dashboard gives each block the figures its web and mobile screens define', function () {
    $timezone = $this->organisation->timezone?->name ?? 'UTC';
    $this->travelTo(Carbon::parse('2026-11-18 15:00:00', $timezone));
    $now = now();

    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::WAREHOUSE_ADMIN]);

    foreach ([ReturnDeliveryNoteStateEnum::RECEIVED, ReturnDeliveryNoteStateEnum::RETURNING, ReturnDeliveryNoteStateEnum::RETURNED] as $state) {
        myWorkReturn($this, null, $state);
    }
    msdStockDelivery($this->organisation, 'MSD'.Str::upper(Str::random(8)));

    $notes = [
        [DeliveryNoteStateEnum::UNASSIGNED, null],
        [DeliveryNoteStateEnum::UNASSIGNED, null],
        [DeliveryNoteStateEnum::HANDLING, null],
        [DeliveryNoteStateEnum::HANDLING_BLOCKED, null],
        [DeliveryNoteStateEnum::PICKED, null],
        [DeliveryNoteStateEnum::PACKED, null],
        [DeliveryNoteStateEnum::FINALISED, null],
        [DeliveryNoteStateEnum::CANCELLED, null],
        [DeliveryNoteStateEnum::DISPATCHED, $now->copy()->subHour()],
        [DeliveryNoteStateEnum::DISPATCHED, $now->copy()->subDay()->subHour()],
        [DeliveryNoteStateEnum::DISPATCHED, $now->copy()->subDay()->addHour()],
        [DeliveryNoteStateEnum::DISPATCHED, $now->copy()->subWeek()->subHours(2)],
        [DeliveryNoteStateEnum::DISPATCHED, $now->copy()->subWeek()->addMinutes(30)],
    ];
    foreach ($notes as [$state, $dispatchedAt]) {
        goodsOutDeliveryNote($this->order, $this->warehouse, ['state' => $state, 'dispatched_at' => $dispatchedAt]);
    }

    $data = msdDashboard($this->warehouse)->assertOk()->json('data');

    expect(array_keys($data))->toBe(['generated_at', 'goods_out', 'goods_in', 'inventory'])
        ->and(Carbon::parse($data['generated_at'])->equalTo($now))->toBeTrue()
        ->and($data['goods_out'])->toBe([
            'unassigned'       => 2,
            'handling'         => 1,
            'handling_blocked' => 1,
            'picked'           => 1,
            'packed'           => 1,
            'in_warehouse'     => 7,
            'dispatched_today' => ['count' => 1, 'yesterday_by_now' => 1, 'same_day_last_week' => 1],
        ]);

    $buckets = getGoodsOut('grp.api.mobile.warehouses.delivery_notes.counts', $this->warehouse->id)->assertOk()->json('data.buckets');
    expect(Arr::except($data['goods_out'], 'dispatched_today'))->toBe(Arr::only($buckets, ['unassigned', 'handling', 'handling_blocked', 'picked', 'packed', 'in_warehouse']));

    $hubRows = collect(getGoodsOut('grp.api.mobile.warehouses.goods_in_hub', $this->warehouse->id)->assertOk()->json('data'))
        ->whereIn('key', ['stock_deliveries', 'partners', 'agents']);
    expect($data['goods_in'])->toBe([
        'arriving'           => $hubRows->sum('arriving'),
        'received'           => $hubRows->sum('received'),
        'checked'            => $hubRows->sum('checked'),
        'booking_in'         => $hubRows->sum('booking_in'),
        'returns_to_process' => 2,
    ])->and($data['goods_in']['arriving'])->toBeGreaterThanOrEqual(1);

    $inventory = getGoodsOut('grp.api.mobile.warehouses.inventory_dashboard', $this->warehouse->id)->assertOk()->json('data');
    expect(Arr::only($data['inventory'], ['number_current_org_stocks', 'number_abnormalities']))->toBe([
        'number_current_org_stocks' => $inventory['number_current_org_stocks'],
        'number_abnormalities'      => $inventory['number_abnormalities'],
    ]);
});

test('every mobile dashboard tile equals the total of the list it opens', function () {
    $token       = 'MST'.Str::upper(Str::random(6));
    $warehouseId = $this->warehouse->id;
    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::WAREHOUSE_ADMIN], ["incoming.$warehouseId.view", "dispatching.$warehouseId.view"]);

    foreach ([DeliveryNoteStateEnum::UNASSIGNED, DeliveryNoteStateEnum::HANDLING, DeliveryNoteStateEnum::HANDLING_BLOCKED, DeliveryNoteStateEnum::PICKED, DeliveryNoteStateEnum::PICKED, DeliveryNoteStateEnum::PACKED] as $state) {
        goodsOutDeliveryNote($this->order, $this->warehouse, ['state' => $state]);
    }
    foreach ([StockDeliveryStateEnum::DISPATCHED, StockDeliveryStateEnum::RECEIVED, StockDeliveryStateEnum::CHECKED, StockDeliveryStateEnum::BOOKING_IN] as $state) {
        msdStockDelivery($this->organisation, 'MSD'.Str::upper(Str::random(8)))->update(['state' => $state]);
    }
    foreach ([ReturnDeliveryNoteStateEnum::RECEIVED, ReturnDeliveryNoteStateEnum::RETURNING, ReturnDeliveryNoteStateEnum::RETURNED] as $state) {
        myWorkReturn($this, null, $state);
    }

    $sko = function (string $suffix, array $attributes) use ($token): OrgStock {
        $orgStock = goodsOutOrgStock($this->group, $this->order, $token.$suffix);
        $orgStock->update(array_merge(['state' => OrgStockStateEnum::ACTIVE], $attributes));

        return $orgStock->refresh();
    };
    $link = fn (OrgStock $orgStock, Warehouse $warehouse, float $quantity) => StoreLocationOrgStock::make()->action(
        orgStock: $orgStock,
        location: goodsOutLocation($warehouse),
        modelData: ['quantity' => $quantity, 'type' => LocationStockTypeEnum::PICKING, 'fetched_at' => now()],
        strict: false
    )->update(['quantity' => $quantity]);

    $belowMinimum = $sko('MIN', ['minimum_stock' => 10, 'quantity_in_locations' => 2]);
    $sko('ENOUGH', ['minimum_stock' => 10, 'quantity_in_locations' => 12]);

    $twiceNegative = $sko('NEG2', []);
    $link($twiceNegative, $this->warehouse, -3);
    $link($twiceNegative, $this->warehouse, -1);
    $onceNegative = $sko('NEG1', []);
    $link($onceNegative, $this->warehouse, -2);
    $link($onceNegative, $this->warehouse, 5);
    $link($sko('NEGX', []), StoreWarehouse::make()->action($this->organisation, Warehouse::factory()->definition()), -4);

    $data = msdDashboard($this->warehouse)->assertOk()->json('data');

    foreach (['unassigned', 'handling', 'handling_blocked', 'picked', 'packed'] as $bucket) {
        $total = getGoodsOut('grp.api.mobile.warehouses.delivery_notes', $warehouseId, ['bucket' => $bucket])->assertOk()->json('pagination.total');
        expect([$bucket => $data['goods_out'][$bucket]])->toBe([$bucket => $total]);
    }
    expect($data['goods_out']['picked'])->toBe(2);

    foreach (['arriving' => 'dispatched', 'received' => 'received', 'checked' => 'checked', 'booking_in' => 'booking_in'] as $figure => $state) {
        $total = getGoodsOut('grp.api.mobile.warehouses.stock_deliveries', $warehouseId, ['state' => $state])->assertOk()->json('meta.total');
        expect([$figure => $data['goods_in'][$figure]])->toBe([$figure => $total])
            ->and($total)->toBeGreaterThanOrEqual(1);
    }

    $returns = getGoodsOut('grp.api.mobile.warehouses.returns', $warehouseId, ['state' => 'to_process'])->assertOk();
    expect($data['goods_in']['returns_to_process'])->toBe(2)
        ->and($returns->json('meta.total'))->toBe(2)
        ->and(collect($returns->json('data'))->pluck('state')->unique()->sort()->values()->all())->toBe(['received', 'returning']);

    foreach (['below_minimum_stock' => 'below_minimum', 'negative_stocks' => 'negative'] as $figure => $bucket) {
        $list = getGoodsOut('grp.api.mobile.warehouses.org_stocks', $warehouseId, ['bucket' => $bucket])->assertOk();
        expect([$figure => $data['inventory'][$figure]])->toBe([$figure => $list->json('meta.total')])
            ->and($list->json("counts.$bucket"))->toBe($list->json('meta.total'));
    }

    expect($data['inventory']['negative_stocks'])->toBe(3)
        ->and($data['inventory']['negative_stocks'])->toBe(ShowInventoryDashboard::numberNegativeStocks($this->warehouse))
        ->and(collect(getGoodsOut('grp.api.mobile.warehouses.org_stocks', $warehouseId, ['bucket' => 'negative'])->json('data'))->map(fn (array $row) => [$row['id'], $row['location_quantity']])->all())
        ->toBe([[$twiceNegative->id, -3], [$onceNegative->id, -2], [$twiceNegative->id, -1]])
        ->and(getGoodsOut('grp.api.mobile.warehouses.org_stocks', $warehouseId, ['bucket' => 'below_minimum', 'q' => $belowMinimum->code])->json('data.*.id'))->toBe([$belowMinimum->id]);
});

test('mobile dashboard blocks are null without their right, and figures are cached 55 seconds per warehouse before rights apply', function () {
    $warehouseId = $this->warehouse->id;
    goodsOutDeliveryNote($this->order, $this->warehouse);

    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::WAREHOUSE_ADMIN]);
    msdDashboard($this->warehouse)->assertOk()->assertJsonPath('data.goods_out.unassigned', 1);

    goodsOutDeliveryNote($this->order, $this->warehouse);

    actingAsGoodsOutUser($this->group, $this->warehouse, [], ["dispatching.$warehouseId.view"]);
    msdDashboard($this->warehouse)->assertOk()
        ->assertJsonPath('data.goods_out.unassigned', 1)
        ->assertJsonPath('data.goods_in', null)
        ->assertJsonPath('data.inventory', null);

    actingAsGoodsOutUser($this->group, $this->warehouse, [], ["incoming.$warehouseId.view"]);
    $goodsIn = msdDashboard($this->warehouse)->assertOk()
        ->assertJsonPath('data.goods_out', null)
        ->assertJsonPath('data.inventory', null)
        ->json('data.goods_in');
    expect(array_keys($goodsIn))->toBe(['arriving', 'received', 'checked', 'booking_in', 'returns_to_process'])
        ->and($goodsIn['returns_to_process'])->toBeInt();

    actingAsGoodsOutUser($this->group, $this->warehouse, [], ["returns.$warehouseId"]);
    msdDashboard($this->warehouse)->assertOk()
        ->assertJsonPath('data.goods_out', null)
        ->assertJsonPath('data.goods_in', null)
        ->assertJsonPath('data.inventory', null);

    $this->travel(56)->seconds();

    actingAsGoodsOutUser($this->group, $this->warehouse, [], ["dispatching.$warehouseId.view"]);
    msdDashboard($this->warehouse)->assertOk()->assertJsonPath('data.goods_out.unassigned', 2);
});
