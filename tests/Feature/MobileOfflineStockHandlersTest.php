<?php

/*
 * Author Louis Perez
 * Created on 20-11-2026-09h-54m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Feature;

use App\Actions\Api\Group\Offline\Handlers\MoveStockOfflineHandler;
use App\Actions\Api\Group\Offline\MobileOfflineHandlerRegistry;
use App\Actions\Api\Group\Offline\MobileOfflineOutcome;
use App\Actions\GoodsIn\ReturnDeliveryNote\ProcessReturnDeliveryNote;
use App\Actions\GoodsIn\StockDeliveryItem\SetStockDeliveryItemCheckedQuantity;
use App\Actions\GoodsIn\StockDeliveryItem\StoreStockDeliveryItem;
use App\Actions\GoodsIn\StockDelivery\DispatchStockDelivery;
use App\Actions\GoodsIn\StockDelivery\ReceiveStockDelivery;
use App\Actions\GoodsIn\StockDelivery\StoreStockDelivery;
use App\Actions\Goods\Stock\StoreStock;
use App\Actions\Inventory\LocationOrgStock\AuditLocationOrgStock;
use App\Actions\Inventory\LocationOrgStock\MoveOrgStockToOtherLocation;
use App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock;
use App\Actions\Inventory\Location\StoreLocation;
use App\Actions\Inventory\OrgStock\StoreOrgStock;
use App\Actions\Procurement\OrgSupplierProducts\StoreOrgSupplierProduct;
use App\Actions\SupplyChain\SupplierProduct\StoreSupplierProduct;
use App\Actions\SupplyChain\Supplier\StoreSupplier;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\GoodsIn\ReturnDeliveryNoteItem\ReturnDeliveryNoteItemStateEnum;
use App\Enums\GoodsIn\ReturnDeliveryNote\ReturnDeliveryNoteStateEnum;
use App\Enums\GoodsIn\Sowing\SowingTypeEnum;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Enums\Inventory\LocationStock\LocationStockTypeEnum;
use App\Enums\Inventory\Location\LocationStatusEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementReasonEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Models\GoodsIn\ReturnDeliveryNote;
use App\Models\GoodsIn\ReturnDeliveryNoteItem;
use App\Models\GoodsIn\Sowing;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Models\Goods\Stock;
use App\Models\Inventory\Location;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockMovement;
use App\Models\Inventory\Warehouse;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\MobileOfflineOperation;
use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use Carbon\CarbonImmutable;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\MobileOfflineTestContext;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

require_once __DIR__.'/../Support/MobileApiHelpers.php';
require_once __DIR__.'/../Support/MobileGoodsOutHelpers.php';
require_once __DIR__.'/../Support/DispatchingHelpers.php';
require_once __DIR__.'/../Support/MobileOfflineTestContext.php';

function offlineStockUser(object $test, array $roles = [RolesEnum::STOCK_CONTROLLER, RolesEnum::DISPATCH_CLERK, RolesEnum::GOODS_IN_CLERK, RolesEnum::RETURNS_CLERK]): User
{
    return actingAsGoodsOutUser($test->group, $test->warehouse, $roles);
}

function offlineStockSko(Warehouse $warehouse, int $packedIn = 1): OrgStock
{
    $stock    = StoreStock::make()->action($warehouse->group, array_merge(Stock::factory()->definition(), ['code' => 'OFS'.Str::upper(Str::random(8)), 'state' => StockStateEnum::ACTIVE]));
    $orgStock = StoreOrgStock::make()->action($warehouse->organisation, $stock);
    $orgStock->update(['packed_in' => $packedIn, 'barcode' => 'OFB'.Str::upper(Str::random(10)), 'unit_barcode' => 'OFU'.Str::upper(Str::random(10))]);

    return $orgStock->refresh();
}

function offlineStockLocation(Warehouse $warehouse, array $attributes = []): Location
{
    $location = StoreLocation::make()->action($warehouse, ['code' => 'OFL'.Str::upper(Str::random(6))]);
    if ($attributes) {
        $location->update($attributes);
    }

    return $location->refresh();
}

function offlineStockLink(OrgStock $orgStock, Location $location, float $quantity, LocationStockTypeEnum $type = LocationStockTypeEnum::STORING): LocationOrgStock
{
    return StoreLocationOrgStock::make()->action(
        orgStock: $orgStock,
        location: $location,
        modelData: ['quantity' => $quantity, 'type' => $type, 'fetched_at' => now()],
        strict: false
    )->refresh();
}

/**
 * @return array{0: OrgStock, 1: LocationOrgStock, 2: LocationOrgStock}
 */
function offlineStockPair(Warehouse $warehouse, float $source = 10, float $target = 4): array
{
    $orgStock = offlineStockSko($warehouse);

    return [$orgStock, offlineStockLink($orgStock, offlineStockLocation($warehouse), $source), offlineStockLink($orgStock, offlineStockLocation($warehouse), $target)];
}

function offlineStockContext(User $user, Warehouse $warehouse, ?CarbonImmutable $performedAt = null): MobileOfflineTestContext
{
    return new MobileOfflineTestContext($user, $warehouse, $performedAt ?? CarbonImmutable::now());
}

/**
 * @param array<string, mixed> $payload
 */
function offlineStockApply(string $operation, MobileOfflineTestContext $context, array $payload): MobileOfflineOutcome
{
    $handler   = app(MobileOfflineHandlerRegistry::class)->for($operation);
    $validated = $handler->validatePayload($payload);

    return DB::transaction(fn () => $handler->apply($context, $validated));
}

/**
 * @param array<string, mixed> $payload
 */
function offlineStockOperation(MobileOfflineTestContext $context, string $operation, array $payload, MobileOfflineOutcome $outcome): MobileOfflineOperation
{
    $warehouse = $context->warehouse();
    $handler   = app(MobileOfflineHandlerRegistry::class)->for($operation);

    $journal = MobileOfflineOperation::create([
        'group_id'           => $warehouse->group_id,
        'organisation_id'    => $warehouse->organisation_id,
        'warehouse_id'       => $warehouse->id,
        'user_id'            => $context->user()->id,
        'device_uuid'        => (string) Str::uuid(),
        'device_sequence'    => 1,
        'operation'          => $operation,
        'kind'               => $handler->kind(),
        'touched_rows'       => $handler->touchedRows($payload),
        'payload'            => $payload,
        'requested_quantity' => $outcome->requestedQuantity,
        'applied_quantity'   => $outcome->appliedQuantity,
        'performed_at'       => $context->performedAt(),
        'synced_at'          => now(),
        'counted_since'      => $outcome->countedSince,
        'state'              => $outcome->state,
        'outcome_reason'     => $outcome->reason,
    ]);

    foreach ($context->created as $model) {
        $journal->effects()->create(['model_type' => class_basename($model), 'model_id' => $model->getKey(), 'effect' => 'created']);
    }

    return $journal;
}

function offlineStockRequestId(): string
{
    return (string) Str::uuid();
}

function offlineStockMovements(OrgStock $orgStock, ?OrgStockMovementTypeEnum $type = null): int
{
    return OrgStockMovement::where('org_stock_id', $orgStock->id)->when($type, fn ($query) => $query->where('type', $type))->count();
}

function offlineStockQuantity(LocationOrgStock $link): float
{
    return round((float) LocationOrgStock::whereKey($link->id)->value('quantity'), 4);
}

/**
 * @param array<int, array{0: OrgStock, 1: float}> $lines
 */
function offlineStockDelivery(Warehouse $warehouse, array $lines): StockDelivery
{
    $organisation = $warehouse->organisation;
    $reference    = 'OFD'.Str::upper(Str::random(8));
    $supplier     = StoreSupplier::make()->action(parent: $organisation->group, modelData: Supplier::factory()->definition());
    $orgSupplier  = $supplier->orgSuppliers()->where('organisation_id', $organisation->id)->firstOrFail();

    $stockDelivery = StoreStockDelivery::make()->action($orgSupplier, ['reference' => $reference, 'date' => date('Y-m-d')]);

    foreach ($lines as $index => [$orgStock, $units]) {
        $supplierProduct = StoreSupplierProduct::make()->action($supplier, [
            'code'             => $reference.'-P'.$index,
            'name'             => 'Offline product '.$index,
            'cost'             => 100,
            'stock_id'         => $orgStock->stock_id,
            'units_per_pack'   => max(1, (int) $orgStock->packed_in),
            'units_per_carton' => 100,
        ]);
        StoreOrgSupplierProduct::make()->action($orgSupplier, $supplierProduct);
        StoreStockDeliveryItem::make()->action($stockDelivery, $supplierProduct->historicSupplierProduct, $orgStock, ['unit_quantity' => $units]);
    }

    DispatchStockDelivery::make()->action($stockDelivery->refresh());

    return ReceiveStockDelivery::make()->action($stockDelivery->refresh())->refresh();
}

/**
 * @return array{0: StockDelivery, 1: StockDeliveryItem, 2: LocationOrgStock, 3: OrgStock}
 */
function offlineStockPutAway(Warehouse $warehouse, float $checked = 10, float $onShelf = 2): array
{
    $orgStock      = offlineStockSko($warehouse);
    $stockDelivery = offlineStockDelivery($warehouse, [[$orgStock, $checked]]);
    $item          = SetStockDeliveryItemCheckedQuantity::make()->action($stockDelivery->items()->orderBy('id')->firstOrFail(), ['unit_quantity_checked' => $checked]);
    $link          = offlineStockLink($orgStock, offlineStockLocation($warehouse), $onShelf);

    return [$stockDelivery->refresh(), $item->refresh(), $link, $orgStock];
}

/**
 * @param array<string, mixed> $extra
 * @return array<string, mixed>
 */
function offlinePlacePayload(StockDeliveryItem $item, float $quantity, array $extra = []): array
{
    return array_merge([
        'stock_delivery_id'      => $item->stock_delivery_id,
        'stock_delivery_item_id' => $item->id,
        'quantity'               => $quantity,
        'client_request_id'      => offlineStockRequestId(),
    ], $extra);
}

/**
 * @return array{0: ReturnDeliveryNote, 1: ReturnDeliveryNoteItem, 2: LocationOrgStock}
 */
function offlineStockReturn(object $test, float $dispatched = 5): array
{
    [$deliveryNote, $deliveryNoteItem] = handlingDeliveryNoteWithPicking($test);
    $deliveryNote->update(['state' => DeliveryNoteStateEnum::DISPATCHED]);
    $deliveryNoteItem->update(['quantity_dispatched' => $dispatched]);

    $returnDeliveryNote = ProcessReturnDeliveryNote::make()->handle($deliveryNote->refresh(), []);
    $item               = $returnDeliveryNote->returnDeliveryNoteItem()->firstOrFail();
    $link               = LocationOrgStock::where('org_stock_id', $item->org_stock_id)->where('warehouse_id', $test->warehouse->id)->firstOrFail();

    $returnDeliveryNote->update(['state' => ReturnDeliveryNoteStateEnum::RETURNING, 'handler_user_id' => $test->user->id, 'returning_at' => now()]);
    $returnDeliveryNote->returnDeliveryNoteItem()->update(['state' => ReturnDeliveryNoteItemStateEnum::HANDLING]);

    return [$returnDeliveryNote->refresh(), $item->refresh(), $link->refresh()];
}

/**
 * @param array<string, mixed> $extra
 * @return array<string, mixed>
 */
function offlineReturnPayload(ReturnDeliveryNoteItem $item, float $quantity, array $extra = []): array
{
    return array_merge([
        'return_delivery_note_id'      => $item->return_delivery_note_id,
        'return_delivery_note_item_id' => $item->id,
        'quantity'                     => $quantity,
        'client_request_id'            => offlineStockRequestId(),
    ], $extra);
}

/**
 * @return array<string, float>
 */
function offlineReturnTotals(ReturnDeliveryNoteItem $item): array
{
    $item->refresh();

    return [
        'returned'     => (float) $item->total_item_returned,
        'damaged'      => (float) $item->total_item_damaged,
        'not_returned' => (float) $item->total_item_not_returned,
    ];
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
    Cache::flush();
});

test('move validates an explicit quantity and a target, in the flat or the targets shape', function () {
    $handler = app(MobileOfflineHandlerRegistry::class)->for('move_stock');
    $base    = ['location_org_stock_id' => 1, 'client_request_id' => offlineStockRequestId()];

    expect(fn () => $handler->validatePayload([...$base, 'target_location_org_stock_id' => 2]))->toThrow(ValidationException::class)
        ->and(fn () => $handler->validatePayload([...$base, 'quantity' => 1]))->toThrow(ValidationException::class)
        ->and(fn () => $handler->validatePayload([...$base, 'target_location_org_stock_id' => 1, 'quantity' => 1]))->toThrow(ValidationException::class)
        ->and(fn () => $handler->validatePayload([...$base, 'targets' => [['location_org_stock_id' => 2, 'quantity' => 1]], 'quantity' => 3]))->toThrow(ValidationException::class)
        ->and(fn () => $handler->validatePayload([...$base, 'targets' => [['location_org_stock_id' => 2, 'location_id' => 3, 'quantity' => 1]]]))->toThrow(ValidationException::class)
        ->and(fn () => $handler->validatePayload(['location_org_stock_id' => 1, 'target_location_org_stock_id' => 2, 'quantity' => 1]))->toThrow(ValidationException::class);

    $flat    = $handler->validatePayload([...$base, 'location_id' => 7, 'quantity' => 2.5]);
    $targets = $handler->validatePayload([...$base, 'targets' => [['location_org_stock_id' => 2, 'quantity' => 1], ['location_id' => 7, 'quantity' => 2]], 'quantity' => 3]);

    expect($flat['targets'])->toBe([['location_org_stock_id' => null, 'location_id' => 7, 'quantity' => 2.5]])
        ->and($targets['quantity'])->toBe(3.0)
        ->and($handler->kind())->toBe('rules');
});

test('move takes the stock from the source to the target row with the user, locking both rows', function () {
    $user = offlineStockUser($this);
    [$orgStock, $source, $target] = offlineStockPair($this->warehouse);
    $handler = app(MobileOfflineHandlerRegistry::class)->for('move_stock');
    $payload = ['location_org_stock_id' => $source->id, 'target_location_org_stock_id' => $target->id, 'quantity' => 3, 'client_request_id' => offlineStockRequestId()];

    expect($handler->subject($payload))->toBe(['subject_type' => 'LocationOrgStock', 'subject_id' => $source->id, 'parent_type' => 'Location', 'parent_id' => $source->location_id])
        ->and($handler->touchedRows($payload))->toBe([['t' => 'LocationOrgStock', 'id' => $source->id], ['t' => 'LocationOrgStock', 'id' => $target->id]]);

    $context = offlineStockContext($user, $this->warehouse);
    $outcome = offlineStockApply('move_stock', $context, $payload);

    $transfers = OrgStockMovement::where('org_stock_id', $orgStock->id)->where('type', OrgStockMovementTypeEnum::LOCATION_TRANSFER)->get();

    expect($outcome->state)->toBe(MobileOfflineOutcome::APPLIED)
        ->and($outcome->appliedQuantity)->toBe(3.0)
        ->and($outcome->countedSince)->toBeFalse()
        ->and(offlineStockQuantity($source))->toBe(7.0)
        ->and(offlineStockQuantity($target))->toBe(7.0)
        ->and($transfers)->toHaveCount(2)
        ->and($transfers->pluck('user_id')->unique()->all())->toBe([$user->id])
        ->and(collect($context->createdOf(OrgStockMovement::class))->pluck('id')->sort()->values()->all())->toBe($transfers->pluck('id')->sort()->values()->all())
        ->and(Cache::has(MoveStockOfflineHandler::mobileWriteKey($user->id, $payload['client_request_id'])))->toBeTrue();
});

test('move to a location with no row for the SKO creates the row, records it as created, and rollback removes it once empty', function () {
    $user = offlineStockUser($this);
    [$orgStock, $source] = offlineStockPair($this->warehouse);
    $newLocation = offlineStockLocation($this->warehouse);
    $payload     = ['location_org_stock_id' => $source->id, 'location_id' => $newLocation->id, 'quantity' => 4, 'client_request_id' => offlineStockRequestId()];

    expect(app(MobileOfflineHandlerRegistry::class)->for('move_stock')->touchedRows($payload)[1])->toBe(['t' => 'Location', 'id' => $newLocation->id]);

    $context = offlineStockContext($user, $this->warehouse);
    $outcome = offlineStockApply('move_stock', $context, $payload);
    $created = LocationOrgStock::where('location_id', $newLocation->id)->where('org_stock_id', $orgStock->id)->firstOrFail();

    expect($outcome->state)->toBe(MobileOfflineOutcome::APPLIED)
        ->and(offlineStockQuantity($created))->toBe(4.0)
        ->and($created->type)->toBe(LocationStockTypeEnum::STORING)
        ->and(collect($context->createdOf(LocationOrgStock::class))->pluck('id')->all())->toBe([$created->id]);

    $operation = offlineStockOperation($context, 'move_stock', $payload, $outcome);
    $handler   = app(MobileOfflineHandlerRegistry::class)->for('move_stock');

    expect($handler->canRollback($operation))->toBeTrue();
    $handler->rollback($operation, $user);

    expect(offlineStockQuantity($source))->toBe(10.0)
        ->and(LocationOrgStock::find($created->id))->toBeNull()
        ->and($handler->canRollback($operation))->toBeFalse();
});

test('move rollback is refused once the target no longer holds what was moved', function () {
    $user = offlineStockUser($this);
    [, $source, $target] = offlineStockPair($this->warehouse, 10, 0);
    $payload = ['location_org_stock_id' => $source->id, 'target_location_org_stock_id' => $target->id, 'quantity' => 3, 'client_request_id' => offlineStockRequestId()];
    $context = offlineStockContext($user, $this->warehouse);
    $outcome = offlineStockApply('move_stock', $context, $payload);

    AuditLocationOrgStock::make()->action($target->refresh(), ['quantity' => 1, 'reason' => OrgStockMovementReasonEnum::COUNT_SHORT->value], $user);
    $operation = offlineStockOperation($context, 'move_stock', $payload, $outcome);
    $handler   = app(MobileOfflineHandlerRegistry::class)->for('move_stock');

    expect($handler->canRollback($operation))->toBeFalse()
        ->and(fn () => $handler->rollback($operation, $user))->toThrow(ValidationException::class)
        ->and(offlineStockQuantity($source))->toBe(7.0);
});

test('move of more than the source holds is refused with a stock check, and goods stay where they are', function () {
    $user = offlineStockUser($this);
    [$orgStock, $source, $target] = offlineStockPair($this->warehouse, 2, 1);
    $before  = offlineStockMovements($orgStock);
    $context = offlineStockContext($user, $this->warehouse);

    $outcome = offlineStockApply('move_stock', $context, ['location_org_stock_id' => $source->id, 'target_location_org_stock_id' => $target->id, 'quantity' => 3, 'client_request_id' => offlineStockRequestId()]);

    expect($outcome->state)->toBe(MobileOfflineOutcome::REFUSED)
        ->and($outcome->reason)->toBe('not_enough_stock')
        ->and($outcome->instruction)->toBe(MobileOfflineOutcome::INSTRUCTION_NONE)
        ->and($context->stockChecks)->toHaveCount(1)
        ->and($context->stockChecks[0]['model']->id)->toBe($source->id)
        ->and(offlineStockQuantity($source))->toBe(2.0)
        ->and(offlineStockQuantity($target))->toBe(1.0)
        ->and(offlineStockMovements($orgStock))->toBe($before);
});

test('move from a source counted after the move only adds to the target, and one onto a counted target only takes from the source', function () {
    $user = offlineStockUser($this);
    [, $source, $target] = offlineStockPair($this->warehouse, 2, 1);
    $performedAt = CarbonImmutable::now()->subMinutes(10);

    AuditLocationOrgStock::make()->action($source, ['quantity' => 0, 'reason' => OrgStockMovementReasonEnum::COUNT_SHORT->value], $user);
    $outcome = offlineStockApply('move_stock', offlineStockContext($user, $this->warehouse, $performedAt), ['location_org_stock_id' => $source->id, 'target_location_org_stock_id' => $target->id, 'quantity' => 2, 'client_request_id' => offlineStockRequestId()]);

    expect($outcome->state)->toBe(MobileOfflineOutcome::APPLIED)
        ->and($outcome->countedSince)->toBeTrue()
        ->and(offlineStockQuantity($source))->toBe(0.0)
        ->and(offlineStockQuantity($target))->toBe(3.0);

    [, $other, $countedTarget] = offlineStockPair($this->warehouse, 5, 1);
    AuditLocationOrgStock::make()->action($countedTarget, ['quantity' => 4, 'reason' => OrgStockMovementReasonEnum::COUNT_GAIN->value], $user);
    $outcome = offlineStockApply('move_stock', offlineStockContext($user, $this->warehouse, $performedAt), ['location_org_stock_id' => $other->id, 'target_location_org_stock_id' => $countedTarget->id, 'quantity' => 3, 'client_request_id' => offlineStockRequestId()]);

    expect($outcome->countedSince)->toBeTrue()
        ->and(offlineStockQuantity($other))->toBe(2.0)
        ->and(offlineStockQuantity($countedTarget))->toBe(4.0);
});

test('move is refused without the right, to a location that takes no stock, or to a row of another SKO', function () {
    [, $source, $target] = offlineStockPair($this->warehouse);
    $broken     = offlineStockLocation($this->warehouse, ['status' => LocationStatusEnum::BROKEN]);
    $otherSkoRow = offlineStockLink(offlineStockSko($this->warehouse), offlineStockLocation($this->warehouse), 1);
    $move       = fn (User $user, array $target) => offlineStockApply('move_stock', offlineStockContext($user, $this->warehouse), ['location_org_stock_id' => $source->id, 'quantity' => 1, 'client_request_id' => offlineStockRequestId(), ...$target]);

    $viewer = offlineStockUser($this, [RolesEnum::DISPATCH_CLERK]);
    expect($move($viewer, ['target_location_org_stock_id' => $target->id])->reason)->toBe('no_right');

    $user = offlineStockUser($this);
    expect($move($user, ['location_id' => $broken->id])->reason)->toBe('wrong_state')
        ->and($move($user, ['location_id' => $broken->id])->instruction)->toBe(MobileOfflineOutcome::INSTRUCTION_PUT_BACK)
        ->and($move($user, ['target_location_org_stock_id' => $otherSkoRow->id])->reason)->toBe('not_found')
        ->and($move($user, ['location_id' => $source->location_id])->reason)->toBe('invalid')
        ->and(offlineStockQuantity($source))->toBe(10.0);
});

test('a move already done online with the same request id is answered applied without moving again, and the handler key stops an online retry', function () {
    $user = offlineStockUser($this);
    [, $source, $target] = offlineStockPair($this->warehouse);
    $requestId = offlineStockRequestId();

    postGoodsOut('grp.api.mobile.location_org_stocks.move', $source->id, ['targets' => [['location_org_stock_id' => $target->id, 'quantity' => 2]], 'client_request_id' => $requestId])
        ->assertOk()
        ->assertJsonPath('result.status', 'done');

    $outcome = offlineStockApply('move_stock', offlineStockContext($user, $this->warehouse), ['location_org_stock_id' => $source->id, 'target_location_org_stock_id' => $target->id, 'quantity' => 2, 'client_request_id' => $requestId]);

    expect($outcome->state)->toBe(MobileOfflineOutcome::APPLIED)
        ->and($outcome->message)->toBe('Already done.')
        ->and(offlineStockQuantity($source))->toBe(8.0);

    $offlineId = offlineStockRequestId();
    offlineStockApply('move_stock', offlineStockContext($user, $this->warehouse), ['location_org_stock_id' => $source->id, 'target_location_org_stock_id' => $target->id, 'quantity' => 1, 'client_request_id' => $offlineId]);
    postGoodsOut('grp.api.mobile.location_org_stocks.move', $source->id, ['targets' => [['location_org_stock_id' => $target->id, 'quantity' => 1]], 'client_request_id' => $offlineId])
        ->assertOk()
        ->assertJsonPath('result.status', 'nothing_changed');

    expect(offlineStockQuantity($source))->toBe(7.0);
});

test('online move some, move all, audits and return puts are done once per client request id, and still work without one', function () {
    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::STOCK_CONTROLLER, RolesEnum::DISPATCH_CLERK], ["locations.{$this->warehouse->id}.edit"]);
    [$orgStock, $source] = offlineStockPair($this->warehouse);
    $target    = offlineStockLocation($this->warehouse);
    $requestId = offlineStockRequestId();
    $someBody  = ['location_id' => $target->id, 'org_stocks' => [['org_stock_id' => $orgStock->id, 'quantity' => 1]], 'reason' => OrgStockMovementReasonEnum::TRANSFER->value, 'client_request_id' => $requestId];

    postGoodsOut('grp.api.mobile.locations.move_some', $source->location_id, $someBody)->assertOk();
    postGoodsOut('grp.api.mobile.locations.move_some', $source->location_id, $someBody)->assertOk();
    expect(offlineStockQuantity($source))->toBe(9.0);

    postGoodsOut('grp.api.mobile.locations.move_some', $source->location_id, ['client_request_id' => 'not-a-uuid'] + $someBody)->assertUnprocessable()->assertJsonValidationErrors('client_request_id');
    postGoodsOut('grp.api.mobile.locations.move_some', $source->location_id, ['client_request_id' => null] + $someBody)->assertOk();
    expect(offlineStockQuantity($source))->toBe(8.0);

    $auditBody = ['counts' => [['location_org_stock_id' => $source->id, 'quantity' => 5, 'reason' => OrgStockMovementReasonEnum::COUNT_SHORT->value]], 'client_request_id' => offlineStockRequestId()];
    postGoodsOut('grp.api.mobile.locations.audit', $source->location_id, $auditBody)->assertOk();
    $source->refresh()->update(['quantity' => 6]);
    postGoodsOut('grp.api.mobile.locations.audit', $source->location_id, $auditBody)->assertOk();
    expect(offlineStockQuantity($source))->toBe(6.0);

    $skoAudit = ['counts' => [['location_org_stock_id' => $source->id, 'quantity' => 2, 'reason' => OrgStockMovementReasonEnum::COUNT_SHORT->value]], 'client_request_id' => offlineStockRequestId()];
    postGoodsOut('grp.api.mobile.warehouses.org_stocks.audit', [$this->warehouse->id, $orgStock->id], $skoAudit)->assertOk()->assertJsonPath('result.status', 'done');
    postGoodsOut('grp.api.mobile.warehouses.org_stocks.audit', [$this->warehouse->id, $orgStock->id], $skoAudit)->assertOk()->assertJsonPath('result.status', 'nothing_changed')->assertJsonPath('result.message', 'Already done.');

    $allBody = ['location_id' => $target->id, 'reason' => OrgStockMovementReasonEnum::TRANSFER->value, 'client_request_id' => offlineStockRequestId()];
    postGoodsOut('grp.api.mobile.locations.move_all', $source->location_id, $allBody)->assertOk();
    $moved = offlineStockQuantity(LocationOrgStock::where('location_id', $target->id)->where('org_stock_id', $orgStock->id)->firstOrFail());
    postGoodsOut('grp.api.mobile.locations.move_all', $source->location_id, $allBody)->assertOk();
    expect($moved)->toBe(4.0)
        ->and(offlineStockQuantity(LocationOrgStock::where('location_id', $target->id)->where('org_stock_id', $orgStock->id)->firstOrFail()))->toBe(4.0);

    [$returnDeliveryNote, $item, $link] = offlineStockReturn($this);
    offlineStockUser($this);
    $shelf = offlineStockQuantity($link);
    $body  = ['quantity' => 1, 'location_org_stock_id' => $link->id, 'client_request_id' => offlineStockRequestId()];
    $route = ['warehouse' => $this->warehouse->id, 'returnDeliveryNote' => $returnDeliveryNote->id, 'returnDeliveryNoteItem' => $item->id];
    postGoodsOut('grp.api.mobile.warehouses.returns.items.returned', $route, $body)->assertOk()->assertJsonPath('result.status', 'done');
    postGoodsOut('grp.api.mobile.warehouses.returns.items.returned', $route, $body)->assertOk()->assertJsonPath('result.status', 'nothing_changed');
    postGoodsOut('grp.api.mobile.warehouses.returns.items.damaged', $route, ['quantity' => 1])->assertOk();

    expect(offlineStockQuantity($link))->toBe($shelf + 1)
        ->and(offlineReturnTotals($item))->toBe(['returned' => 1.0, 'damaged' => 1.0, 'not_returned' => 0.0]);
});

test('place records the put-away with the user and the shared request key, and rollback undoes it while booking in', function () {
    $user = offlineStockUser($this);
    [$stockDelivery, $item, $link, $orgStock] = offlineStockPutAway($this->warehouse);
    $payload = offlinePlacePayload($item, 4, ['location_org_stock_id' => $link->id]);
    $handler = app(MobileOfflineHandlerRegistry::class)->for('place_stock_delivery_item');

    expect($handler->subject($payload))->toBe(['subject_type' => 'StockDeliveryItem', 'subject_id' => $item->id, 'parent_type' => 'StockDelivery', 'parent_id' => $stockDelivery->id])
        ->and($handler->touchedRows($payload))->toBe([['t' => 'StockDeliveryItem', 'id' => $item->id], ['t' => 'LocationOrgStock', 'id' => $link->id]])
        ->and(fn () => $handler->validatePayload(offlinePlacePayload($item, 4)))->toThrow(ValidationException::class)
        ->and(fn () => $handler->validatePayload(offlinePlacePayload($item, 0, ['location_org_stock_id' => $link->id])))->toThrow(ValidationException::class);

    $context = offlineStockContext($user, $this->warehouse);
    $outcome = offlineStockApply('place_stock_delivery_item', $context, $payload);
    $sowing  = Sowing::where('stock_delivery_item_id', $item->id)->firstOrFail();

    expect($outcome->state)->toBe(MobileOfflineOutcome::APPLIED)
        ->and($outcome->appliedQuantity)->toBe(4.0)
        ->and(offlineStockQuantity($link))->toBe(6.0)
        ->and((float) $item->refresh()->unit_quantity_placed)->toBe(4.0)
        ->and($sowing->sower_user_id)->toBe($user->id)
        ->and(collect($context->createdOf(Sowing::class))->pluck('id')->all())->toBe([$sowing->id])
        ->and($context->updated[0]['after'])->toBe(['unit_quantity_placed' => 4.0])
        ->and(Cache::has(MoveStockOfflineHandler::mobileWriteKey($user->id, $payload['client_request_id'])))->toBeTrue();

    $mobileGoodsInPlace = fn (string $requestId) => postGoodsOut('grp.api.mobile.warehouses.stock_deliveries.items.place', ['warehouse' => $this->warehouse->id, 'stockDelivery' => $stockDelivery->id, 'stockDeliveryItem' => $item->id], ['quantity' => 1, 'location_org_stock_id' => $link->id, 'client_request_id' => $requestId]);
    $mobileGoodsInPlace($payload['client_request_id'])->assertOk()->assertJsonPath('result.status', 'nothing_changed');

    $operation = offlineStockOperation($context, 'place_stock_delivery_item', $payload, $outcome);
    expect($handler->canRollback($operation))->toBeTrue();
    $handler->rollback($operation, $user);

    expect(offlineStockQuantity($link))->toBe(2.0)
        ->and((float) $item->refresh()->unit_quantity_placed)->toBe(0.0)
        ->and($handler->canRollback($operation))->toBeFalse()
        ->and(offlineStockMovements($orgStock, OrgStockMovementTypeEnum::CANCEL_PURCHASE))->toBe(1);
});

test('place over what is left records what is left with a stock check, and nothing left is refused', function () {
    $user = offlineStockUser($this);
    [, $item, $link] = offlineStockPutAway($this->warehouse, 10, 2);
    $context = offlineStockContext($user, $this->warehouse);

    $outcome = offlineStockApply('place_stock_delivery_item', $context, offlinePlacePayload($item, 12, ['location_org_stock_id' => $link->id]));

    expect($outcome->state)->toBe(MobileOfflineOutcome::PARTLY_APPLIED)
        ->and($outcome->reason)->toBe('over_checked')
        ->and($outcome->instruction)->toBe(MobileOfflineOutcome::INSTRUCTION_CHECK_SHELF)
        ->and($outcome->requestedQuantity)->toBe(12.0)
        ->and($outcome->appliedQuantity)->toBe(10.0)
        ->and(offlineStockQuantity($link))->toBe(12.0)
        ->and($context->stockChecks)->toHaveCount(1)
        ->and($context->stockChecks[0]['model']->id)->toBe($link->id);

    $item->stockDelivery->update(['state' => StockDeliveryStateEnum::BOOKING_IN]);
    $again   = offlineStockContext($user, $this->warehouse);
    $outcome = offlineStockApply('place_stock_delivery_item', $again, offlinePlacePayload($item, 1, ['location_org_stock_id' => $link->id]));

    expect($outcome->state)->toBe(MobileOfflineOutcome::REFUSED)
        ->and($outcome->reason)->toBe('over_checked')
        ->and($again->stockChecks)->toHaveCount(1)
        ->and(offlineStockQuantity($link))->toBe(12.0);
});

test('place on a delivery out of goods in, a broken location, a row of another SKO or without the right is refused', function () {
    [$stockDelivery, $item, $link] = offlineStockPutAway($this->warehouse);
    $broken = offlineStockLocation($this->warehouse, ['status' => LocationStatusEnum::BROKEN]);
    $place  = fn (User $user, array $extra, ?MobileOfflineTestContext $context = null) => offlineStockApply('place_stock_delivery_item', $context ?? offlineStockContext($user, $this->warehouse), offlinePlacePayload($item, 1, $extra));

    $clerk = offlineStockUser($this, [RolesEnum::DISPATCH_CLERK]);
    expect($place($clerk, ['location_org_stock_id' => $link->id])->reason)->toBe('no_right');

    $user    = offlineStockUser($this);
    $context = offlineStockContext($user, $this->warehouse);
    $refused = $place($user, ['location_id' => $broken->id], $context);
    expect($refused->reason)->toBe('wrong_state')
        ->and($context->stockChecks[0]['model']->id)->toBe($broken->id)
        ->and($place($user, ['location_org_stock_id' => offlineStockLink(offlineStockSko($this->warehouse), offlineStockLocation($this->warehouse), 1)->id])->reason)->toBe('not_found');

    $stockDelivery->update(['state' => StockDeliveryStateEnum::PLACED]);
    $context = offlineStockContext($user, $this->warehouse);
    $closed  = $place($user, ['location_org_stock_id' => $link->id], $context);

    expect($closed->reason)->toBe('delivery_closed')
        ->and($closed->instruction)->toBe(MobileOfflineOutcome::INSTRUCTION_CHECK_SHELF)
        ->and($context->stockChecks[0]['model']->id)->toBe($link->id)
        ->and(Sowing::where('stock_delivery_item_id', $item->id)->count())->toBe(0)
        ->and(offlineStockQuantity($link))->toBe(2.0);
});

test('place on a location counted since is recorded without a stock movement, and a new location row is recorded as created', function () {
    $user = offlineStockUser($this);
    [, $item, $link, $orgStock] = offlineStockPutAway($this->warehouse);
    AuditLocationOrgStock::make()->action($link, ['quantity' => 5, 'reason' => OrgStockMovementReasonEnum::COUNT_GAIN->value], $user);
    $purchases = offlineStockMovements($orgStock, OrgStockMovementTypeEnum::PURCHASE);

    $outcome = offlineStockApply('place_stock_delivery_item', offlineStockContext($user, $this->warehouse, CarbonImmutable::now()->subMinutes(5)), offlinePlacePayload($item, 3, ['location_org_stock_id' => $link->id]));

    expect($outcome->state)->toBe(MobileOfflineOutcome::APPLIED)
        ->and($outcome->countedSince)->toBeTrue()
        ->and(offlineStockQuantity($link))->toBe(5.0)
        ->and((float) $item->refresh()->unit_quantity_placed)->toBe(3.0)
        ->and(offlineStockMovements($orgStock, OrgStockMovementTypeEnum::PURCHASE))->toBe($purchases);

    $newLocation = offlineStockLocation($this->warehouse);
    $context     = offlineStockContext($user, $this->warehouse);
    offlineStockApply('place_stock_delivery_item', $context, offlinePlacePayload($item, 2, ['location_id' => $newLocation->id]));
    $newRow = LocationOrgStock::where('location_id', $newLocation->id)->where('org_stock_id', $orgStock->id)->firstOrFail();

    expect(offlineStockQuantity($newRow))->toBe(2.0)
        ->and(collect($context->createdOf(LocationOrgStock::class))->pluck('id')->all())->toBe([$newRow->id]);
});

test('return put back, damaged and not returned record the puts, and rollback deletes a put while returning', function () {
    [$returnDeliveryNote, $item, $link] = offlineStockReturn($this);
    $user  = offlineStockUser($this);
    $shelf = offlineStockQuantity($link);
    $registry = app(MobileOfflineHandlerRegistry::class);

    expect($registry->for('return_put_back')->touchedRows(offlineReturnPayload($item, 1, ['location_org_stock_id' => $link->id])))->toBe([['t' => 'ReturnDeliveryNoteItem', 'id' => $item->id], ['t' => 'LocationOrgStock', 'id' => $link->id]])
        ->and($registry->for('return_damaged')->subject(offlineReturnPayload($item, 1)))->toBe(['subject_type' => 'ReturnDeliveryNoteItem', 'subject_id' => $item->id, 'parent_type' => 'ReturnDeliveryNote', 'parent_id' => $returnDeliveryNote->id])
        ->and(fn () => $registry->for('return_put_back')->validatePayload(offlineReturnPayload($item, 1)))->toThrow(ValidationException::class)
        ->and(fn () => $registry->for('return_damaged')->validatePayload(array_merge(offlineReturnPayload($item, 1), ['quantity' => null])))->toThrow(ValidationException::class);

    $context  = offlineStockContext($user, $this->warehouse);
    $putBack  = offlineReturnPayload($item, 2, ['location_org_stock_id' => $link->id]);
    $outcome  = offlineStockApply('return_put_back', $context, $putBack);

    expect($outcome->state)->toBe(MobileOfflineOutcome::APPLIED)
        ->and(offlineStockQuantity($link))->toBe($shelf + 2)
        ->and(offlineStockApply('return_damaged', offlineStockContext($user, $this->warehouse), offlineReturnPayload($item, 1))->state)->toBe(MobileOfflineOutcome::APPLIED)
        ->and(offlineStockApply('return_not_returned', offlineStockContext($user, $this->warehouse), offlineReturnPayload($item, 1))->state)->toBe(MobileOfflineOutcome::APPLIED)
        ->and(offlineReturnTotals($item))->toBe(['returned' => 2.0, 'damaged' => 1.0, 'not_returned' => 1.0])
        ->and($item->sowings()->pluck('type')->map(fn (SowingTypeEnum $type) => $type->value)->all())->toBe(['sow', 'damaged', 'not-sow']);

    $operation = offlineStockOperation($context, 'return_put_back', $putBack, $outcome);
    $handler   = $registry->for('return_put_back');
    expect($handler->canRollback($operation))->toBeTrue();
    $handler->rollback($operation, $user);

    expect(offlineStockQuantity($link))->toBe($shelf)
        ->and(offlineReturnTotals($item)['returned'])->toBe(0.0)
        ->and($handler->canRollback($operation))->toBeFalse();
});

test('return put over what is left records what is left, over_expected, with a stock check for the extra on a put back', function () {
    [, $item, $link] = offlineStockReturn($this, 5);
    $user    = offlineStockUser($this);
    $shelf   = offlineStockQuantity($link);
    $context = offlineStockContext($user, $this->warehouse);

    $outcome = offlineStockApply('return_put_back', $context, offlineReturnPayload($item, 6, ['location_org_stock_id' => $link->id]));

    expect($outcome->state)->toBe(MobileOfflineOutcome::PARTLY_APPLIED)
        ->and($outcome->reason)->toBe('over_expected')
        ->and($outcome->appliedQuantity)->toBe(5.0)
        ->and($outcome->instruction)->toBe(MobileOfflineOutcome::INSTRUCTION_CHECK_SHELF)
        ->and(offlineStockQuantity($link))->toBe($shelf + 5)
        ->and($context->stockChecks)->toHaveCount(1)
        ->and($context->stockChecks[0]['model']->id)->toBe($link->id);

    $damaged = offlineStockApply('return_damaged', offlineStockContext($user, $this->warehouse), offlineReturnPayload($item, 1));
    expect($damaged->state)->toBe(MobileOfflineOutcome::REFUSED)
        ->and($damaged->reason)->toBe('over_expected')
        ->and($damaged->instruction)->toBe(MobileOfflineOutcome::INSTRUCTION_NONE);
});

test('a put back to a location of another SKO is refused, and one onto a location counted since puts back without a stock movement', function () {
    [, $item, $link] = offlineStockReturn($this);
    $user     = offlineStockUser($this);
    $otherRow = offlineStockLink(offlineStockSko($this->warehouse), offlineStockLocation($this->warehouse), 1);

    expect(offlineStockApply('return_put_back', offlineStockContext($user, $this->warehouse), offlineReturnPayload($item, 1, ['location_org_stock_id' => $otherRow->id]))->reason)->toBe('not_found');

    AuditLocationOrgStock::make()->action($link->refresh(), ['quantity' => 50, 'reason' => OrgStockMovementReasonEnum::COUNT_GAIN->value], $user);
    $returnMovements = offlineStockMovements($item->orgStock, OrgStockMovementTypeEnum::RETURN_PICKED);
    $outcome = offlineStockApply('return_put_back', offlineStockContext($user, $this->warehouse, CarbonImmutable::now()->subMinutes(5)), offlineReturnPayload($item, 2, ['location_org_stock_id' => $link->id]));

    expect($outcome->state)->toBe(MobileOfflineOutcome::APPLIED)
        ->and($outcome->countedSince)->toBeTrue()
        ->and(offlineStockQuantity($link))->toBe(50.0)
        ->and(offlineReturnTotals($item)['returned'])->toBe(2.0)
        ->and(offlineStockMovements($item->orgStock, OrgStockMovementTypeEnum::RETURN_PICKED))->toBe($returnMovements);
});

test('walk-through E: puts sent after the return was closed are refused with a task to check the return before the refund', function () {
    [$returnDeliveryNote, $item, $link] = offlineStockReturn($this, 5);
    $user  = offlineStockUser($this);
    $shelf = offlineStockQuantity($link);
    $returnDeliveryNote->update(['state' => ReturnDeliveryNoteStateEnum::RETURNED]);

    $putContext     = offlineStockContext($user, $this->warehouse);
    $damagedContext = offlineStockContext($user, $this->warehouse);
    $putBack = offlineStockApply('return_put_back', $putContext, offlineReturnPayload($item, 4, ['location_org_stock_id' => $link->id]));
    $damaged = offlineStockApply('return_damaged', $damagedContext, offlineReturnPayload($item, 1));

    expect($putBack->state)->toBe(MobileOfflineOutcome::REFUSED)
        ->and($putBack->reason)->toBe('return_closed')
        ->and($putBack->instruction)->toBe(MobileOfflineOutcome::INSTRUCTION_CHECK_SHELF)
        ->and($damaged->reason)->toBe('return_closed')
        ->and($damaged->instruction)->toBe(MobileOfflineOutcome::INSTRUCTION_NONE)
        ->and($putContext->stockChecks)->toHaveCount(1)
        ->and($putContext->stockChecks[0]['model'])->toBeInstanceOf(ReturnDeliveryNote::class)
        ->and($putContext->stockChecks[0]['description'])->toContain($returnDeliveryNote->reference)
        ->and($putContext->stockChecks[0]['description'])->toContain('refund')
        ->and($putContext->stockChecks[0]['description'])->toContain($link->location->code)
        ->and($damagedContext->stockChecks[0]['model'])->toBeInstanceOf(ReturnDeliveryNote::class)
        ->and(offlineStockQuantity($link))->toBe($shelf)
        ->and(offlineReturnTotals($item))->toBe(['returned' => 0.0, 'damaged' => 0.0, 'not_returned' => 0.0]);
});

test('audit location applies the counts when the versions match and is never rolled back', function () {
    $user = offlineStockUser($this);
    [, $first, $second] = offlineStockPair($this->warehouse, 10, 4);
    $location = $first->location;
    $third    = offlineStockLink(offlineStockSko($this->warehouse), $location, 3);
    $handler  = app(MobileOfflineHandlerRegistry::class)->for('audit_location');
    $payload  = [
        'location_id'       => $location->id,
        'counts'            => [
            ['location_org_stock_id' => $first->id, 'quantity' => 8, 'reason' => OrgStockMovementReasonEnum::COUNT_SHORT->value],
            ['location_org_stock_id' => $third->id, 'quantity' => 3, 'reason' => OrgStockMovementReasonEnum::RECOUNT->value],
        ],
        'versions'          => [(string) $first->id => $first->refresh()->version, (string) $third->id => $third->refresh()->version],
        'client_request_id' => offlineStockRequestId(),
    ];

    expect($handler->kind())->toBe('version')
        ->and($handler->touchedRows($payload))->toBe([['t' => 'LocationOrgStock', 'id' => $first->id, 'v' => $first->version], ['t' => 'LocationOrgStock', 'id' => $third->id, 'v' => $third->version]])
        ->and(fn () => $handler->validatePayload(array_merge($payload, ['versions' => [(string) $first->id => 1]])))->toThrow(ValidationException::class);

    $outcome = offlineStockApply('audit_location', offlineStockContext($user, $this->warehouse), $payload);

    expect($outcome->state)->toBe(MobileOfflineOutcome::APPLIED)
        ->and(offlineStockQuantity($first))->toBe(8.0)
        ->and($first->refresh()->audited_at)->not->toBeNull()
        ->and($third->refresh()->audited_at)->not->toBeNull()
        ->and(offlineStockQuantity($second))->toBe(4.0)
        ->and($handler->canRollback(new MobileOfflineOperation()))->toBeFalse()
        ->and(fn () => $handler->rollback(new MobileOfflineOperation(), $user))->toThrow(ValidationException::class);
});

test('walk-through C: a count over a row someone else changed is a conflict, while the person own earlier move is allowed', function () {
    $user  = offlineStockUser($this);
    $other = createMobileAppUser($this->group);
    [$orgStock, $shelf, $elsewhere] = offlineStockPair($this->warehouse, 12, 0);
    $from     = offlineStockLink($orgStock, offlineStockLocation($this->warehouse), 10);
    $seen     = (int) $shelf->refresh()->version;
    $countR   = fn () => [
        'location_id'       => $shelf->location_id,
        'counts'            => [['location_org_stock_id' => $shelf->id, 'quantity' => 15, 'reason' => OrgStockMovementReasonEnum::COUNT_GAIN->value]],
        'versions'          => [(string) $shelf->id => $seen],
        'client_request_id' => offlineStockRequestId(),
    ];

    MoveOrgStockToOtherLocation::make()->action($shelf->refresh(), $elsewhere->refresh(), ['quantity' => 2], $other);
    expect(offlineStockQuantity($shelf))->toBe(10.0);

    $context = offlineStockContext($user, $this->warehouse);
    $before  = (int) $shelf->refresh()->version;
    offlineStockApply('move_stock', $context, ['location_org_stock_id' => $from->id, 'target_location_org_stock_id' => $shelf->id, 'quantity' => 3, 'client_request_id' => offlineStockRequestId()]);
    $context->withOwnChange($shelf, $before, (int) $shelf->refresh()->version);

    $conflict = offlineStockApply('audit_location', $context, $countR());

    expect($conflict->state)->toBe(MobileOfflineOutcome::CONFLICT)
        ->and($conflict->reason)->toBe('other_change')
        ->and($conflict->instruction)->toBe(MobileOfflineOutcome::INSTRUCTION_RECOUNT)
        ->and($conflict->conflictingUserId)->toBe($other->id)
        ->and($conflict->serverSnapshot[0])->toMatchArray(['location_org_stock_id' => $shelf->id, 'quantity' => 13.0, 'version_seen' => $seen, 'counted' => 15.0])
        ->and(offlineStockQuantity($shelf))->toBe(13.0);

    [$orgStock, $quiet] = offlineStockPair($this->warehouse, 12, 0);
    $source  = offlineStockLink($orgStock, offlineStockLocation($this->warehouse), 10);
    $seen    = (int) $quiet->refresh()->version;
    $context = offlineStockContext($user, $this->warehouse);
    offlineStockApply('move_stock', $context, ['location_org_stock_id' => $source->id, 'target_location_org_stock_id' => $quiet->id, 'quantity' => 3, 'client_request_id' => offlineStockRequestId()]);
    $context->withOwnChange($quiet, $seen, (int) $quiet->refresh()->version);

    $applied = offlineStockApply('audit_location', $context, [
        'location_id'       => $quiet->location_id,
        'counts'            => [['location_org_stock_id' => $quiet->id, 'quantity' => 15, 'reason' => OrgStockMovementReasonEnum::RECOUNT->value]],
        'versions'          => [(string) $quiet->id => $seen],
        'client_request_id' => offlineStockRequestId(),
    ]);

    expect($applied->state)->toBe(MobileOfflineOutcome::APPLIED)
        ->and(offlineStockQuantity($quiet))->toBe(15.0);
});

test('a count conflict names only someone who changed the row after the phone downloaded it', function () {
    $user  = offlineStockUser($this);
    $other = createMobileAppUser($this->group);
    [$orgStock, $shelf, $elsewhere] = offlineStockPair($this->warehouse, 12, 0);

    MoveOrgStockToOtherLocation::make()->action($shelf->refresh(), $elsewhere->refresh(), ['quantity' => 2], $other);
    $this->travel(5)->seconds();
    $seenAt = CarbonImmutable::now();
    $seen   = (int) $shelf->refresh()->version;
    $this->travel(5)->seconds();
    MoveOrgStockToOtherLocation::make()->action($shelf->refresh(), $elsewhere->refresh(), ['quantity' => 1], $user);

    $conflict = offlineStockApply('audit_location', new MobileOfflineTestContext($user, $this->warehouse, CarbonImmutable::now(), $seenAt), [
        'location_id'       => $shelf->location_id,
        'counts'            => [['location_org_stock_id' => $shelf->id, 'quantity' => 15, 'reason' => OrgStockMovementReasonEnum::COUNT_GAIN->value]],
        'versions'          => [(string) $shelf->id => $seen],
        'client_request_id' => offlineStockRequestId(),
    ]);

    expect($conflict->state)->toBe(MobileOfflineOutcome::CONFLICT)
        ->and($conflict->reason)->toBe('other_change')
        ->and($conflict->conflictingUserId)->toBeNull();
});

test('audit SKO checks the versions of the counted rows of this warehouse and the reason of a change', function () {
    $user = offlineStockUser($this);
    [$orgStock, $picking, $storing] = offlineStockPair($this->warehouse, 10, 4);
    $handler = app(MobileOfflineHandlerRegistry::class)->for('audit_org_stock');
    $payload = fn (array $counts, array $versions) => ['org_stock_id' => $orgStock->id, 'counts' => $counts, 'versions' => $versions, 'client_request_id' => offlineStockRequestId()];
    $versions = [(string) $picking->id => (int) $picking->refresh()->version, (string) $storing->id => (int) $storing->refresh()->version];

    expect($handler->subject($payload([], []))['subject_type'])->toBe('OrgStock');

    $noReason = offlineStockApply('audit_org_stock', offlineStockContext($user, $this->warehouse), $payload([['location_org_stock_id' => $picking->id, 'quantity' => 9]], $versions));
    expect($noReason->state)->toBe(MobileOfflineOutcome::REFUSED)
        ->and($noReason->reason)->toBe('invalid')
        ->and(offlineStockQuantity($picking))->toBe(10.0);

    $outcome = offlineStockApply('audit_org_stock', offlineStockContext($user, $this->warehouse), $payload([
        ['location_org_stock_id' => $picking->id, 'quantity' => 10],
        ['location_org_stock_id' => $storing->id, 'quantity' => 6, 'reason' => OrgStockMovementReasonEnum::COUNT_GAIN->value],
    ], $versions));

    expect($outcome->state)->toBe(MobileOfflineOutcome::APPLIED)
        ->and(offlineStockQuantity($storing))->toBe(6.0);

    $stale = offlineStockApply('audit_org_stock', offlineStockContext($user, $this->warehouse), $payload([['location_org_stock_id' => $storing->id, 'quantity' => 6]], $versions));
    expect($stale->state)->toBe(MobileOfflineOutcome::CONFLICT)
        ->and($stale->conflictingUserId)->toBeNull();

    $foreignRow = offlineStockLink(offlineStockSko($this->warehouse), offlineStockLocation($this->warehouse), 1);
    expect(offlineStockApply('audit_org_stock', offlineStockContext($user, $this->warehouse), $payload([['location_org_stock_id' => $foreignRow->id, 'quantity' => 1]], [(string) $foreignRow->id => 1]))->reason)->toBe('not_found');

    $viewer = offlineStockUser($this, [RolesEnum::DISPATCH_CLERK]);
    expect(offlineStockApply('audit_org_stock', offlineStockContext($viewer, $this->warehouse), $payload([['location_org_stock_id' => $storing->id, 'quantity' => 6]], [(string) $storing->id => (int) $storing->refresh()->version]))->reason)->toBe('no_right');
});

test('location rows carry their version on the location page, the SKO page, return items and stock delivery items', function () {
    offlineStockUser($this);
    [$orgStock, $link] = offlineStockPair($this->warehouse, 10, 4);
    $link->refresh()->update(['quantity' => 11]);
    $version = (int) $link->refresh()->version;

    $locationRow = collect(getGoodsOut('grp.api.mobile.locations.show', $link->location_id)->assertOk()->json('data.org_stocks'))->firstWhere('location_org_stock_id', $link->id);
    $skoRow      = collect(getGoodsOut('grp.api.mobile.warehouses.org_stocks.show', [$this->warehouse->id, $orgStock->id])->assertOk()->json('data.locations'))->firstWhere('location_org_stock_id', $link->id);

    expect($version)->toBeGreaterThan(1)
        ->and($locationRow['version'])->toBe($version)
        ->and($skoRow['version'])->toBe($version);

    [$stockDelivery, , $deliveryLink, $deliverySko] = offlineStockPutAway($this->warehouse);
    $deliveryRow = getGoodsOut('grp.api.mobile.warehouses.stock_deliveries.show', ['warehouse' => $this->warehouse->id, 'stockDelivery' => $stockDelivery->id])->assertOk()->json('data.items.0.locations.0');
    expect($deliveryRow['location_org_stock_id'])->toBe($deliveryLink->id)
        ->and($deliveryRow['version'])->toBe((int) $deliveryLink->refresh()->version);

    [$returnDeliveryNote, $item, $returnLink] = offlineStockReturn($this);
    $item->orgStock->update(['barcode' => 'OFR'.Str::upper(Str::random(8)), 'unit_barcode' => 'OFQ'.Str::upper(Str::random(8))]);
    offlineStockUser($this);
    $returnItem = getGoodsOut('grp.api.mobile.warehouses.returns.show', ['warehouse' => $this->warehouse->id, 'returnDeliveryNote' => $returnDeliveryNote->id])->assertOk()->json('data.items.0');

    expect($returnItem['barcodes'])->toBe([$item->orgStock->refresh()->barcode, $item->orgStock->unit_barcode])
        ->and($returnItem['locations'][0])->toMatchArray([
            'location_org_stock_id' => $returnLink->id,
            'location_id'           => $returnLink->location_id,
            'location_code'         => $returnLink->location->code,
            'version'               => (int) $returnLink->refresh()->version,
        ])
        ->and($deliverySko->id)->not->toBe($item->org_stock_id);
});

dataset('offline stock shared scenarios', [
    'over-place' => ['over_place'],
    'put on a closed return' => ['closed_return'],
    'over-put on a return' => ['over_put'],
    'move more than the source holds' => ['short_move'],
]);

test('the online endpoint and the offline handler leave the same stock and totals', function (string $scenario) {
    $user = offlineStockUser($this);

    $run = match ($scenario) {
        'over_place' => function (bool $online) use ($user) {
            [$stockDelivery, $item, $link] = offlineStockPutAway($this->warehouse, 10, 2);
            if ($online) {
                $route = ['warehouse' => $this->warehouse->id, 'stockDelivery' => $stockDelivery->id, 'stockDeliveryItem' => $item->id];
                postGoodsOut('grp.api.mobile.warehouses.stock_deliveries.items.place', $route, ['quantity' => 12, 'location_org_stock_id' => $link->id, 'client_request_id' => offlineStockRequestId()])
                    ->assertUnprocessable()
                    ->assertJsonValidationErrors(['quantity' => 'You can not place more than the checked quantity (10 remaining)']);
                postGoodsOut('grp.api.mobile.warehouses.stock_deliveries.items.place', $route, ['quantity' => 10, 'location_org_stock_id' => $link->id, 'client_request_id' => offlineStockRequestId()])->assertOk();
            } else {
                expect(offlineStockApply('place_stock_delivery_item', offlineStockContext($user, $this->warehouse), offlinePlacePayload($item, 12, ['location_org_stock_id' => $link->id]))->appliedQuantity)->toBe(10.0);
            }

            return [offlineStockQuantity($link), (float) $item->refresh()->unit_quantity_placed, $item->state->value, $stockDelivery->refresh()->state->value];
        },
        'closed_return' => function (bool $online) use ($user) {
            [$returnDeliveryNote, $item, $link] = offlineStockReturn($this);
            $shelf = offlineStockQuantity($link);
            $returnDeliveryNote->update(['state' => ReturnDeliveryNoteStateEnum::RETURNED]);
            if ($online) {
                postGoodsOut('grp.api.mobile.warehouses.returns.items.returned', ['warehouse' => $this->warehouse->id, 'returnDeliveryNote' => $returnDeliveryNote->id, 'returnDeliveryNoteItem' => $item->id], ['quantity' => 2, 'location_org_stock_id' => $link->id])
                    ->assertUnprocessable()
                    ->assertJsonValidationErrors(['state']);
            } else {
                expect(offlineStockApply('return_put_back', offlineStockContext($user, $this->warehouse), offlineReturnPayload($item, 2, ['location_org_stock_id' => $link->id]))->reason)->toBe('return_closed');
            }

            return [offlineStockQuantity($link) - $shelf, offlineReturnTotals($item)];
        },
        'over_put' => function (bool $online) use ($user) {
            [$returnDeliveryNote, $item, $link] = offlineStockReturn($this, 5);
            $shelf = offlineStockQuantity($link);
            if ($online) {
                $route = ['warehouse' => $this->warehouse->id, 'returnDeliveryNote' => $returnDeliveryNote->id, 'returnDeliveryNoteItem' => $item->id];
                postGoodsOut('grp.api.mobile.warehouses.returns.items.returned', $route, ['quantity' => 6, 'location_org_stock_id' => $link->id])
                    ->assertUnprocessable()
                    ->assertJsonValidationErrors(['quantity' => 'Only 5 is left to handle on this item.']);
                postGoodsOut('grp.api.mobile.warehouses.returns.items.returned', $route, ['quantity' => 5, 'location_org_stock_id' => $link->id])->assertOk();
            } else {
                expect(offlineStockApply('return_put_back', offlineStockContext($user, $this->warehouse), offlineReturnPayload($item, 6, ['location_org_stock_id' => $link->id]))->appliedQuantity)->toBe(5.0);
            }

            return [offlineStockQuantity($link) - $shelf, offlineReturnTotals($item), (bool) $item->refresh()->is_handled];
        },
        'short_move' => function (bool $online) use ($user) {
            [, $source, $target] = offlineStockPair($this->warehouse, 2, 1);
            if ($online) {
                postGoodsOut('grp.api.mobile.location_org_stocks.move', $source->id, ['targets' => [['location_org_stock_id' => $target->id, 'quantity' => 3]]])
                    ->assertUnprocessable()
                    ->assertJsonValidationErrors('targets');
            } else {
                expect(offlineStockApply('move_stock', offlineStockContext($user, $this->warehouse), ['location_org_stock_id' => $source->id, 'target_location_org_stock_id' => $target->id, 'quantity' => 3, 'client_request_id' => offlineStockRequestId()])->reason)->toBe('not_enough_stock');
            }

            return [offlineStockQuantity($source), offlineStockQuantity($target)];
        },
    };

    expect($run(false))->toBe($run(true));
})->with('offline stock shared scenarios');

dataset('offline stock handlers through the sync endpoint', [
    'move_stock', 'place_stock_delivery_item', 'return_put_back', 'return_damaged', 'return_not_returned', 'audit_location', 'audit_org_stock',
]);

test('each stock handler runs end to end through the offline sync endpoint', function (string $operation) {

    [$payload, $check, $subjectType, $parentType] = match ($operation) {
        'move_stock' => (function () {
            [, $source, $target] = offlineStockPair($this->warehouse);

            return [
                ['location_org_stock_id' => $source->id, 'target_location_org_stock_id' => $target->id, 'quantity' => 3, 'client_request_id' => offlineStockRequestId()],
                fn () => expect(offlineStockQuantity($source))->toBe(7.0)->and(offlineStockQuantity($target))->toBe(7.0),
                'LocationOrgStock',
                'Location',
            ];
        })(),
        'place_stock_delivery_item' => (function () {
            [, $item, $link] = offlineStockPutAway($this->warehouse);

            return [
                offlinePlacePayload($item, 4, ['location_org_stock_id' => $link->id]),
                fn () => expect(offlineStockQuantity($link))->toBe(6.0)->and((float) $item->refresh()->unit_quantity_placed)->toBe(4.0),
                'StockDeliveryItem',
                'StockDelivery',
            ];
        })(),
        'return_put_back', 'return_damaged', 'return_not_returned' => (function () use ($operation) {
            [, $item, $link] = offlineStockReturn($this);
            $shelf = offlineStockQuantity($link);
            $key   = ['return_put_back' => 'returned', 'return_damaged' => 'damaged', 'return_not_returned' => 'not_returned'][$operation];

            return [
                offlineReturnPayload($item, 2, $operation === 'return_put_back' ? ['location_org_stock_id' => $link->id] : []),
                fn () => expect(offlineReturnTotals($item)[$key])->toBe(2.0)->and(offlineStockQuantity($link))->toBe($operation === 'return_put_back' ? $shelf + 2 : $shelf),
                'ReturnDeliveryNoteItem',
                'ReturnDeliveryNote',
            ];
        })(),
        'audit_location' => (function () {
            [, $link] = offlineStockPair($this->warehouse);

            return [
                ['location_id' => $link->location_id, 'counts' => [['location_org_stock_id' => $link->id, 'quantity' => 8, 'reason' => OrgStockMovementReasonEnum::COUNT_SHORT->value]], 'versions' => [(string) $link->id => (int) $link->refresh()->version], 'client_request_id' => offlineStockRequestId()],
                fn () => expect(offlineStockQuantity($link))->toBe(8.0),
                'LocationOrgStock',
                'Location',
            ];
        })(),
        'audit_org_stock' => (function () {
            [$orgStock, $link] = offlineStockPair($this->warehouse);

            return [
                ['org_stock_id' => $orgStock->id, 'counts' => [['location_org_stock_id' => $link->id, 'quantity' => 12, 'reason' => OrgStockMovementReasonEnum::COUNT_GAIN->value]], 'versions' => [(string) $link->id => (int) $link->refresh()->version], 'client_request_id' => offlineStockRequestId()],
                fn () => expect(offlineStockQuantity($link))->toBe(12.0),
                'OrgStock',
                'OrgStock',
            ];
        })(),
    };

    offlineStockUser($this);
    $device  = (string) Str::uuid();
    $headers = ['X-Aiku-App-Key' => 'mobile-test-key', 'X-Aiku-Device' => $device, 'X-Aiku-Platform' => 'android', 'X-Aiku-App-Version' => '1.4.2 (52)'];

    $response = postJson(route('grp.api.mobile.warehouses.offline.sync', $this->warehouse->id), ['actions' => [[
        'sequence'     => 1,
        'operation'    => $operation,
        'performed_at' => now()->subMinutes(5)->toIso8601ZuluString('millisecond'),
        'time_source'  => 'server',
        'seen_at'      => now()->subMinutes(30)->toIso8601ZuluString('millisecond'),
        'payload'      => $payload,
    ]]], $headers)->assertOk();

    $check();

    $result  = $response->json('data.results.0');
    $journal = MobileOfflineOperation::findOrFail($result['operation_id']);

    expect($result['status'])->toBe('applied')
        ->and($result['operation'])->toBe($operation)
        ->and($response->json('data.open_count'))->toBe(0)
        ->and($journal->state)->toBe('applied')
        ->and($journal->subject_type)->toBe($subjectType)
        ->and($journal->parent_type)->toBe($parentType)
        ->and($journal->effects()->count())->toBeGreaterThan(0);

    $retry = postJson(route('grp.api.mobile.warehouses.offline.sync', $this->warehouse->id), ['actions' => [['sequence' => 2, 'operation' => $operation, 'performed_at' => now()->toIso8601ZuluString('millisecond'), 'time_source' => 'server', 'seen_at' => null, 'payload' => $payload]]], $headers)->assertOk();
    expect($retry->json('data.results.0.status'))->toBe('applied')
        ->and($retry->json('data.results.0.message'))->toBe('Already done.');
    $check();
})->with('offline stock handlers through the sync endpoint');

test('an audit conflict lists what was counted next to what Aiku has', function () {
    [, $link] = offlineStockPair($this->warehouse);
    $seen = (int) $link->refresh()->version;
    $link->update(['quantity' => 9]);

    offlineStockUser($this);
    $headers = ['X-Aiku-App-Key' => 'mobile-test-key', 'X-Aiku-Device' => (string) Str::uuid(), 'X-Aiku-Platform' => 'android', 'X-Aiku-App-Version' => '1.4.2 (52)'];

    $sync = postJson(route('grp.api.mobile.warehouses.offline.sync', $this->warehouse->id), ['actions' => [[
        'sequence'     => 1,
        'operation'    => 'audit_location',
        'performed_at' => now()->subMinutes(5)->toIso8601ZuluString('millisecond'),
        'time_source'  => 'server',
        'seen_at'      => now()->subMinutes(30)->toIso8601ZuluString('millisecond'),
        'payload'      => ['location_id' => $link->location_id, 'counts' => [['location_org_stock_id' => $link->id, 'quantity' => 8, 'reason' => OrgStockMovementReasonEnum::COUNT_SHORT->value]], 'versions' => [(string) $link->id => $seen], 'client_request_id' => offlineStockRequestId()],
    ]]], $headers)->assertOk();

    expect($sync->json('data.results.0.status'))->toBe('conflict');

    $open = getJson(route('grp.api.mobile.offline.operations.index').'?state=open', $headers)->assertOk();

    expect($open->json('data.0.counted_rows'))->toEqual([[
        'location_code' => $link->location->code,
        'sko_code'      => $link->orgStock->code,
        'sko_name'      => $link->orgStock->name,
        'counted'       => 8.0,
        'quantity'      => 9.0,
    ]])
        ->and($open->json('data.0.item'))->toMatchArray([
            'sko_code'         => null,
            'record_type'      => 'location',
            'record_reference' => $link->location->code,
            'quantities'       => ['stock_now' => 9.0],
        ]);
});

test('a sync still answers when live messages cannot be sent', function () {
    [, $source, $target] = offlineStockPair($this->warehouse, 2, 1);
    offlineStockUser($this);
    $headers = ['X-Aiku-App-Key' => 'mobile-test-key', 'X-Aiku-Device' => (string) Str::uuid(), 'X-Aiku-Platform' => 'android', 'X-Aiku-App-Version' => '1.4.2 (52)'];
    $tasks   = StaffTask::count();

    app()->forgetInstance(BroadcastManager::class);
    Broadcast::clearResolvedInstances();
    Broadcast::extend('failing', fn () => new class () extends Broadcaster {
        public function auth($request)
        {
            return null;
        }

        public function validAuthenticationResponse($request, $result)
        {
            return null;
        }

        public function broadcast(array $channels, $event, array $payload = [])
        {
            throw new BroadcastException('Websocket server unreachable.');
        }
    });
    config(['broadcasting.connections.failing' => ['driver' => 'failing'], 'broadcasting.default' => 'failing']);

    $sync = postJson(route('grp.api.mobile.warehouses.offline.sync', $this->warehouse->id), ['actions' => [[
        'sequence'     => 1,
        'operation'    => 'move_stock',
        'performed_at' => now()->subMinutes(5)->toIso8601ZuluString('millisecond'),
        'time_source'  => 'server',
        'seen_at'      => now()->subMinutes(30)->toIso8601ZuluString('millisecond'),
        'payload'      => ['location_org_stock_id' => $source->id, 'target_location_org_stock_id' => $target->id, 'quantity' => 3, 'client_request_id' => offlineStockRequestId()],
    ]]], $headers)->assertOk();

    expect($sync->json('data.results.0.status'))->toBe('refused')
        ->and($sync->json('data.results.0.reason'))->toBe('not_enough_stock')
        ->and(StaffTask::count())->toBe($tasks + 1)
        ->and(offlineStockQuantity($source))->toBe(2.0);
});
