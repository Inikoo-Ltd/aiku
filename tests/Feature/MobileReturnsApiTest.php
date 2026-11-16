<?php

/*
 * Author Louis Perez
 * Created on 16-11-2026-11h-33m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Feature;

use App\Actions\GoodsIn\ReturnDeliveryNote\ProcessReturnDeliveryNote;
use App\Actions\Goods\Stock\StoreStock;
use App\Actions\Inventory\Location\StoreLocation;
use App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock;
use App\Actions\Inventory\OrgStock\StoreOrgStock;
use App\Actions\Inventory\Warehouse\StoreWarehouse;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\GoodsIn\ReturnDeliveryNote\ReturnDeliveryNoteStateEnum;
use App\Enums\GoodsIn\ReturnDeliveryNoteItem\ReturnDeliveryNoteItemStateEnum;
use App\Enums\GoodsIn\Sowing\SowingTypeEnum;
use App\Enums\Inventory\Location\LocationStatusEnum;
use App\Enums\Inventory\LocationStock\LocationStockTypeEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Models\Goods\Stock;
use App\Models\GoodsIn\ReturnDeliveryNote;
use App\Models\GoodsIn\ReturnDeliveryNoteItem;
use App\Models\GoodsIn\Sowing;
use App\Models\Inventory\Location;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\OrgStockMovement;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\getJson;

require_once __DIR__.'/../Support/MobileApiHelpers.php';
require_once __DIR__.'/../Support/MobileGoodsOutHelpers.php';
require_once __DIR__.'/../Support/DispatchingHelpers.php';

/**
 * @return array{0: ReturnDeliveryNote, 1: ReturnDeliveryNoteItem, 2: LocationOrgStock}
 */
function mobileReturnFixture(object $test, float $dispatched = 3): array
{
    [$deliveryNote, $deliveryNoteItem] = handlingDeliveryNoteWithPicking($test);
    $deliveryNote->update(['state' => DeliveryNoteStateEnum::DISPATCHED]);
    $deliveryNoteItem->update(['quantity_dispatched' => $dispatched]);

    $returnDeliveryNote = ProcessReturnDeliveryNote::make()->handle($deliveryNote->refresh(), []);
    $item               = $returnDeliveryNote->returnDeliveryNoteItem()->firstOrFail();
    $link               = LocationOrgStock::where('org_stock_id', $item->org_stock_id)->where('warehouse_id', $test->warehouse->id)->firstOrFail();

    return [$returnDeliveryNote->refresh(), $item, $link];
}

function mobileReturnReturning(ReturnDeliveryNote $returnDeliveryNote, User $user): ReturnDeliveryNote
{
    $returnDeliveryNote->update(['state' => ReturnDeliveryNoteStateEnum::RETURNING, 'handler_user_id' => $user->id, 'returning_at' => now()]);
    $returnDeliveryNote->returnDeliveryNoteItem()->update(['state' => ReturnDeliveryNoteItemStateEnum::HANDLING]);

    return $returnDeliveryNote->refresh();
}

function mobileReturnsUser(object $test, array $roles = [RolesEnum::RETURNS_CLERK], array $permissions = []): User
{
    return actingAsGoodsOutUser($test->group, $test->warehouse, $roles, $permissions);
}

/**
 * @param array<string, mixed> $body
 */
function mobileReturnPost(string $action, Warehouse $warehouse, ReturnDeliveryNote $returnDeliveryNote, array $body = [], ?ReturnDeliveryNoteItem $item = null): TestResponse
{
    $parameters = ['warehouse' => $warehouse->id, 'returnDeliveryNote' => $returnDeliveryNote->id];
    if ($item) {
        $parameters['returnDeliveryNoteItem'] = $item->id;
    }

    return postGoodsOut('grp.api.mobile.warehouses.returns.'.$action, $parameters, $body);
}

function mobileReturnShow(Warehouse $warehouse, ReturnDeliveryNote $returnDeliveryNote): TestResponse
{
    return getGoodsOut('grp.api.mobile.warehouses.returns.show', ['warehouse' => $warehouse->id, 'returnDeliveryNote' => $returnDeliveryNote->id]);
}

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    [$this->organisation, $this->user, $this->shop] = createShop();
    $this->shop->update(['is_aiku' => true]);
    $this->group     = $this->organisation->group;
    $this->warehouse = createWarehouse();
    $this->product   = createProduct($this->shop)[1];
    $this->customer  = createCustomer($this->shop);
});

test('mobile warehouses give a returns clerk the returns abilities but not goods in', function () {
    mobileReturnsUser($this);

    getJson(route('grp.api.mobile.warehouses'), ['X-Aiku-App-Key' => 'mobile-test-key'])
        ->assertOk()
        ->assertJsonPath('data.0.id', $this->warehouse->id)
        ->assertJsonPath('data.0.can.view_returns', true)
        ->assertJsonPath('data.0.can.handle_returns', true)
        ->assertJsonPath('data.0.can.view_goods_in', false)
        ->assertJsonPath('data.0.can.book_in', false);

    getGoodsOut('grp.api.mobile.warehouses.returns', $this->warehouse->id)->assertOk();
    getGoodsOut('grp.api.mobile.warehouses.stock_deliveries', $this->warehouse->id)->assertForbidden();

    mobileReturnsUser($this, [], ["incoming.{$this->warehouse->id}.view"]);
    getJson(route('grp.api.mobile.warehouses'), ['X-Aiku-App-Key' => 'mobile-test-key'])
        ->assertOk()
        ->assertJsonPath('data.0.can.view_returns', true)
        ->assertJsonPath('data.0.can.handle_returns', false);

    mobileReturnsUser($this, [RolesEnum::DISPATCH_CLERK]);
    getJson(route('grp.api.mobile.warehouses'), ['X-Aiku-App-Key' => 'mobile-test-key'])
        ->assertOk()
        ->assertJsonPath('data.0.can.view_returns', false)
        ->assertJsonPath('data.0.can.handle_returns', false);
    getGoodsOut('grp.api.mobile.warehouses.returns', $this->warehouse->id)->assertForbidden();
});

test('mobile returns list filters by state and reference, newest first, with the warehouse counts', function () {
    [$first]  = mobileReturnFixture($this);
    [$second] = mobileReturnFixture($this);
    $second   = mobileReturnReturning($second, $this->user);
    $second->update(['created_at' => now()->addMinute()]);

    mobileReturnsUser($this);
    $list = fn (array $query = []) => getGoodsOut('grp.api.mobile.warehouses.returns', $this->warehouse->id, $query)->assertOk();
    $ids  = fn (array $query = []) => collect($list($query)->json('data'))->pluck('id')->all();

    expect(array_slice($ids(), 0, 2))->toBe([$second->id, $first->id])
        ->and($ids(['q' => $first->reference]))->toBe([$first->id])
        ->and($ids(['q' => $first->reference, 'state' => 'returning']))->toBe([])
        ->and($ids(['q' => $second->reference, 'state' => 'returning']))->toBe([$second->id])
        ->and($ids(['q' => $first->reference, 'sort' => 'created_at']))->toBe([$first->id]);

    $stats = $this->warehouse->refresh()->stats;
    $list(['q' => $second->reference])
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.reference', $second->reference)
        ->assertJsonPath('data.0.type', 'return')
        ->assertJsonPath('data.0.type_label', 'Return')
        ->assertJsonPath('data.0.state', 'returning')
        ->assertJsonPath('data.0.state_label', 'Returning to Locations')
        ->assertJsonPath('data.0.customer.name', $this->customer->name)
        ->assertJsonPath('data.0.delivery_note.id', $second->delivery_note_id)
        ->assertJsonPath('data.0.number_items', 1)
        ->assertJsonPath('data.0.number_handled', 0)
        ->assertJsonPath('data.0.handler', ['id' => $this->user->id, 'name' => $this->user->contact_name ?: $this->user->username])
        ->assertJsonPath('counts', [
            'received'  => (int) $stats->number_return_delivery_notes_state_received,
            'returning' => (int) $stats->number_return_delivery_notes_state_returning,
            'returned'  => (int) $stats->number_return_delivery_notes_state_returned,
            'done'      => (int) $stats->number_return_delivery_notes_state_done,
            'cancelled' => (int) $stats->number_return_delivery_notes_state_cancelled,
            'all'       => (int) $stats->number_return_delivery_notes,
        ]);

    getGoodsOut('grp.api.mobile.warehouses.returns', $this->warehouse->id, ['state' => 'processed', 'sort' => 'reference'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['state', 'sort']);
});

test('mobile return detail lists the items with this warehouse locations and is not found from another warehouse', function () {
    $otherWarehouse = StoreWarehouse::make()->action($this->warehouse->organisation, Warehouse::factory()->definition());
    [$returnDeliveryNote, $item, $link] = mobileReturnFixture($this);
    $elsewhere = StoreLocationOrgStock::make()->action(orgStock: $item->orgStock, location: StoreLocation::make()->action($otherWarehouse, ['code' => 'MRX'.Str::upper(Str::random(5))]), modelData: ['quantity' => 7, 'type' => LocationStockTypeEnum::STORING, 'fetched_at' => now()], strict: false);

    mobileReturnsUser($this);
    $response = mobileReturnShow($this->warehouse, $returnDeliveryNote)
        ->assertOk()
        ->assertJsonPath('data.id', $returnDeliveryNote->id)
        ->assertJsonPath('data.reference', $returnDeliveryNote->reference)
        ->assertJsonPath('data.state', 'received')
        ->assertJsonPath('data.customer.name', $this->customer->name)
        ->assertJsonPath('data.order.reference', $returnDeliveryNote->order->reference)
        ->assertJsonPath('data.delivery_note.id', $returnDeliveryNote->delivery_note_id)
        ->assertJsonPath('data.handler', null)
        ->assertJsonPath('data.timeline.0.state', 'received')
        ->assertJsonPath('data.timeline.0.at', $returnDeliveryNote->created_at->toIso8601String())
        ->assertJsonPath('data.timeline.1.at', null)
        ->assertJsonPath('data.items.0.id', $item->id)
        ->assertJsonPath('data.items.0.sko.id', $item->org_stock_id)
        ->assertJsonPath('data.items.0.expected', 3)
        ->assertJsonPath('data.items.0.expected_label', '3')
        ->assertJsonPath('data.items.0.remaining', 3)
        ->assertJsonPath('data.items.0.puts', [])
        ->assertJsonPath('data.items.0.locations', [[
            'location_org_stock_id' => $link->id,
            'location'              => ['id' => $link->location_id, 'code' => $link->location->code],
            'type'                  => 'picking',
            'quantity'              => json_decode(json_encode((float) $link->quantity)),
            'quantity_label'        => trimDecimalZeros($link->quantity),
        ]])
        ->assertJsonPath('data.available_actions', [['key' => 'start', 'label' => 'Start']]);

    expect(collect($response->json('data.items.0.locations'))->pluck('location_org_stock_id'))->not->toContain($elsewhere->id);

    actingAsGoodsOutUser($this->group, $otherWarehouse, [RolesEnum::RETURNS_CLERK]);
    mobileReturnShow($otherWarehouse, $returnDeliveryNote)->assertNotFound();

    mobileReturnsUser($this, [], ["incoming.{$this->warehouse->id}.view"]);
    mobileReturnShow($this->warehouse, $returnDeliveryNote)->assertOk()->assertJsonPath('data.available_actions', []);

    mobileReturnsUser($this, [RolesEnum::DISPATCH_CLERK]);
    mobileReturnShow($this->warehouse, $returnDeliveryNote)->assertForbidden();
});

test('mobile start sets the return returning with the user as handler, only from received', function () {
    [$returnDeliveryNote] = mobileReturnFixture($this);

    mobileReturnsUser($this, [], ["incoming.{$this->warehouse->id}.view"]);
    mobileReturnPost('start', $this->warehouse, $returnDeliveryNote)->assertForbidden();

    $user = mobileReturnsUser($this);
    mobileReturnPost('start', $this->warehouse, $returnDeliveryNote)
        ->assertOk()
        ->assertJsonPath('data.state', 'returning')
        ->assertJsonPath('data.handler.id', $user->id)
        ->assertJsonPath('data.items.0.state', 'handling')
        ->assertJsonPath('data.available_actions', [['key' => 'put_back', 'label' => 'Put back'], ['key' => 'undo', 'label' => 'Undo']])
        ->assertJsonPath('result.status', 'done');

    expect($returnDeliveryNote->refresh()->handler_user_id)->toBe($user->id);

    mobileReturnPost('start', $this->warehouse, $returnDeliveryNote)->assertUnprocessable()->assertJsonValidationErrors(['state']);
});

test('mobile put back, damaged and not returned check the state, the quantity and the location, and only put back moves stock', function () {
    $otherWarehouse = StoreWarehouse::make()->action($this->warehouse->organisation, Warehouse::factory()->definition());
    [$returnDeliveryNote, $item, $link] = mobileReturnFixture($this, 5);
    [$otherReturn] = mobileReturnFixture($this);
    $otherSko  = StoreOrgStock::make()->action($this->warehouse->organisation, StoreStock::make()->action($this->group, Stock::factory()->definition()));
    $otherLink = StoreLocationOrgStock::make()->action(orgStock: $otherSko, location: StoreLocation::make()->action($this->warehouse, ['code' => 'MRS'.Str::upper(Str::random(5))]), modelData: ['quantity' => 1, 'type' => LocationStockTypeEnum::STORING, 'fetched_at' => now()], strict: false);
    $foreign   = StoreLocationOrgStock::make()->action(orgStock: $item->orgStock, location: StoreLocation::make()->action($otherWarehouse, ['code' => 'MRF'.Str::upper(Str::random(5))]), modelData: ['quantity' => 1, 'type' => LocationStockTypeEnum::STORING, 'fetched_at' => now()], strict: false);
    $broken    = StoreLocationOrgStock::make()->action(orgStock: $item->orgStock, location: StoreLocation::make()->action($this->warehouse, ['code' => 'MRB'.Str::upper(Str::random(5))]), modelData: ['quantity' => 0, 'type' => LocationStockTypeEnum::STORING, 'fetched_at' => now()], strict: false);
    Location::whereKey($broken->location_id)->update(['status' => LocationStatusEnum::BROKEN]);
    $movements = fn () => OrgStockMovement::where('org_stock_id', $item->org_stock_id)->where('type', OrgStockMovementTypeEnum::RETURN_PICKED)->count();
    $before    = $movements();

    $user = mobileReturnsUser($this);
    $put  = fn (string $action, array $body) => mobileReturnPost('items.'.$action, $this->warehouse, $returnDeliveryNote, $body, $item);

    $put('returned', ['quantity' => 1, 'location_org_stock_id' => $link->id])->assertUnprocessable()->assertJsonValidationErrors(['state']);

    $returnDeliveryNote = mobileReturnReturning($returnDeliveryNote, $this->user);

    $put('returned', ['quantity' => 6, 'location_org_stock_id' => $link->id])->assertUnprocessable()->assertJsonValidationErrors(['quantity' => 'Only 5 is left to handle on this item.']);
    $put('returned', ['quantity' => 1, 'location_org_stock_id' => $otherLink->id])->assertUnprocessable()->assertJsonValidationErrors(['location_org_stock_id' => 'That location does not hold this SKO.']);
    $put('returned', ['quantity' => 1, 'location_org_stock_id' => $foreign->id])->assertUnprocessable()->assertJsonValidationErrors(['location_org_stock_id' => 'That location is not in this warehouse.']);
    $put('returned', ['quantity' => 1, 'location_org_stock_id' => $broken->id])->assertUnprocessable()->assertJsonValidationErrors(['location_org_stock_id' => 'This location is not operational.']);
    $put('returned', ['quantity' => 1])->assertUnprocessable()->assertJsonValidationErrors(['location_org_stock_id']);
    $put('damaged', ['quantity' => 0])->assertUnprocessable()->assertJsonValidationErrors(['quantity']);
    expect($item->sowings()->count())->toBe(0);

    $shelf = (float) $link->refresh()->quantity;
    $put('returned', ['quantity' => 2, 'location_org_stock_id' => $link->id])
        ->assertOk()
        ->assertJsonPath('result.status', 'done')
        ->assertJsonPath('result.message', 'Put 2 back in '.$link->location->code.'.')
        ->assertJsonPath('data.items.0.returned', 2)
        ->assertJsonPath('data.items.0.remaining', 3)
        ->assertJsonPath('data.items.0.puts.0.type', 'sow')
        ->assertJsonPath('data.items.0.puts.0.type_label', 'Returned')
        ->assertJsonPath('data.items.0.puts.0.location.id', $link->location_id)
        ->assertJsonPath('data.items.0.puts.0.by.id', $user->id);
    expect((float) $link->refresh()->quantity)->toBe($shelf + 2)
        ->and($movements())->toBe($before + 1);

    $put('damaged', ['quantity' => 1])
        ->assertOk()
        ->assertJsonPath('result.message', '1 marked as damaged; it does not go back to stock.')
        ->assertJsonPath('data.items.0.damaged', 1);
    $put('not_returned', ['quantity' => null])
        ->assertOk()
        ->assertJsonPath('result.message', '2 marked as not returned.')
        ->assertJsonPath('data.items.0.not_returned', 2)
        ->assertJsonPath('data.items.0.remaining', 0)
        ->assertJsonPath('data.items.0.is_handled', true)
        ->assertJsonPath('data.available_actions.2', ['key' => 'set_as_returned', 'label' => 'Set as returned']);

    expect((float) $link->refresh()->quantity)->toBe($shelf + 2)
        ->and($movements())->toBe($before + 1)
        ->and($item->sowings()->pluck('type')->map(fn (SowingTypeEnum $type) => $type->value)->all())->toBe(['sow', 'damaged', 'not-sow']);

    $put('damaged', [])->assertUnprocessable()->assertJsonValidationErrors(['quantity' => 'Nothing is left to handle on this item.']);

    mobileReturnPost('items.damaged', $this->warehouse, $otherReturn, ['quantity' => 1], $item)->assertNotFound();

    mobileReturnsUser($this, [], ["incoming.{$this->warehouse->id}.view"]);
    $put('damaged', ['quantity' => 1])->assertForbidden();
});

test('mobile undo takes a put back off the shelf again, only while returning', function () {
    [$returnDeliveryNote, $item, $link] = mobileReturnFixture($this);
    $returnDeliveryNote = mobileReturnReturning($returnDeliveryNote, $this->user);
    [$otherReturn, $otherItem] = mobileReturnFixture($this);
    $otherReturn = mobileReturnReturning($otherReturn, $this->user);

    mobileReturnsUser($this);
    $shelf = (float) $link->refresh()->quantity;
    mobileReturnPost('items.returned', $this->warehouse, $returnDeliveryNote, ['quantity' => null, 'location_org_stock_id' => $link->id], $item)->assertOk()->assertJsonPath('data.items.0.returned', 3);
    $sowing = Sowing::where('return_item_id', $item->id)->firstOrFail();
    $undo   = fn () => deleteGoodsOut('grp.api.mobile.warehouses.returns.puts.delete', ['warehouse' => $this->warehouse->id, 'returnDeliveryNote' => $returnDeliveryNote->id, 'sowing' => $sowing->id]);

    $returnDeliveryNote->update(['state' => ReturnDeliveryNoteStateEnum::RETURNED]);
    $undo()->assertUnprocessable()->assertJsonValidationErrors(['state']);

    $returnDeliveryNote->update(['state' => ReturnDeliveryNoteStateEnum::RETURNING]);
    $undo()
        ->assertOk()
        ->assertJsonPath('result.status', 'done')
        ->assertJsonPath('data.items.0.returned', 0)
        ->assertJsonPath('data.items.0.remaining', 3)
        ->assertJsonPath('data.items.0.puts', []);

    expect((float) $link->refresh()->quantity)->toBe($shelf)
        ->and(Sowing::find($sowing->id))->toBeNull();

    $undo()->assertNotFound();

    mobileReturnPost('items.damaged', $this->warehouse, $otherReturn, ['quantity' => 1], $otherItem)->assertOk();
    $otherSowing = Sowing::where('return_item_id', $otherItem->id)->firstOrFail();
    deleteGoodsOut('grp.api.mobile.warehouses.returns.puts.delete', ['warehouse' => $this->warehouse->id, 'returnDeliveryNote' => $returnDeliveryNote->id, 'sowing' => $otherSowing->id])->assertNotFound();
});

test('mobile set as returned needs a handled item and marks what is left as not returned', function () {
    [$returnDeliveryNote, $item, $link] = mobileReturnFixture($this, 4);
    $returnDeliveryNote = mobileReturnReturning($returnDeliveryNote, $this->user);

    mobileReturnsUser($this);
    mobileReturnPost('set_as_returned', $this->warehouse, $returnDeliveryNote)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['state' => 'Put back, mark as damaged or as not returned at least one item first.']);

    mobileReturnPost('items.returned', $this->warehouse, $returnDeliveryNote, ['quantity' => 1, 'location_org_stock_id' => $link->id], $item)->assertOk();
    mobileReturnPost('set_as_returned', $this->warehouse, $returnDeliveryNote)->assertUnprocessable();

    $item->update(['is_handled' => true]);
    mobileReturnPost('set_as_returned', $this->warehouse, $returnDeliveryNote)
        ->assertOk()
        ->assertJsonPath('data.state', 'returned')
        ->assertJsonPath('data.items.0.returned', 1)
        ->assertJsonPath('data.items.0.not_returned', 3)
        ->assertJsonPath('data.items.0.remaining', 0)
        ->assertJsonPath('data.available_actions', [])
        ->assertJsonPath('result.message', 'Set as returned; what was left on 1 item is now not returned.');

    expect((float) $item->refresh()->total_item_not_returned)->toBe(3.0)
        ->and($item->state)->toBe(ReturnDeliveryNoteItemStateEnum::PROCESSED);

    mobileReturnPost('set_as_returned', $this->warehouse, $returnDeliveryNote)->assertUnprocessable()->assertJsonValidationErrors(['state']);
});

test('mobile return writes need the handle returns right', function () {
    [$returnDeliveryNote, $item, $link] = mobileReturnFixture($this);
    $returnDeliveryNote = mobileReturnReturning($returnDeliveryNote, $this->user);

    mobileReturnsUser($this, [RolesEnum::DISPATCH_CLERK]);
    mobileReturnPost('start', $this->warehouse, $returnDeliveryNote)->assertForbidden();
    mobileReturnPost('items.returned', $this->warehouse, $returnDeliveryNote, ['quantity' => 1, 'location_org_stock_id' => $link->id], $item)->assertForbidden();
    mobileReturnPost('set_as_returned', $this->warehouse, $returnDeliveryNote)->assertForbidden();
    getGoodsOut('grp.api.mobile.warehouses.returns', $this->warehouse->id)->assertForbidden();

    mobileReturnsUser($this, [RolesEnum::GOODS_IN_CLERK]);
    mobileReturnPost('items.damaged', $this->warehouse, $returnDeliveryNote, ['quantity' => 1], $item)->assertOk();
});
