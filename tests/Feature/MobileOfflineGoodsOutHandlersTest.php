<?php

/*
 * Author Louis Perez
 * Created on 20-11-2026-11h-40m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Feature;

use App\Actions\Api\Group\GoodsOut\MobileClientRequest;
use App\Actions\Api\Group\Offline\MobileOfflineHandlerRegistry;
use App\Actions\Api\Group\Offline\MobileOfflineOutcome;
use App\Actions\Dispatching\DeliveryNote\UpdateState\StartPackingDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\UpdateState\UndoWaitingDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\UpdateState\UpdateDeliveryNoteStateToPicked;
use App\Actions\Dispatching\Picking\SetAsWaitingWarehouse;
use App\Actions\Dispatching\Picking\StorePicking;
use App\Actions\Dispatching\Picking\UndoSetAsWaitingWarehouse;
use App\Actions\Dispatching\PickingSession\StorePickingSession;
use App\Actions\Inventory\Warehouse\StoreWarehouse;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\Picking\PickingTypeEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Dispatching\Packing;
use App\Models\Dispatching\Picking;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\OrgStockMovement;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\MobileOfflineOperation;
use App\Models\SysAdmin\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\MobileOfflineTestContext;

require_once __DIR__.'/../Support/MobileApiHelpers.php';
require_once __DIR__.'/../Support/MobileGoodsOutHelpers.php';

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    [$this->organisation, , $this->shop] = createShop();
    $this->group            = $this->organisation->group;
    $this->customer         = createCustomer($this->shop);
    $this->order            = createOrder($this->customer, createProduct($this->shop)[1]);
    $this->warehouse        = StoreWarehouse::make()->action($this->organisation, Warehouse::factory()->definition());
    $this->originalSettings = $this->organisation->settings;
    setGoodsOutPickingSettings($this->organisation, ['allow_waiting' => true, 'allow_picker_set_not_picked' => true, 'allow_picker_choose_location' => true]);
});

afterEach(function () {
    $this->organisation->update(['settings' => $this->originalSettings]);
});

function offlineGoContext(User $user, Warehouse $warehouse, ?CarbonImmutable $performedAt = null): MobileOfflineTestContext
{
    return new MobileOfflineTestContext($user, $warehouse->refresh(), $performedAt);
}

/**
 * @param array<string, mixed> $extra
 * @return array<string, mixed>
 */
function offlineGoPayload(DeliveryNoteItem $line, array $extra): array
{
    return [
        'delivery_note_id'      => $line->delivery_note_id,
        'delivery_note_item_id' => $line->id,
        'client_request_id'     => (string) Str::uuid(),
        ...$extra,
    ];
}

/**
 * @param array<string, mixed> $payload
 */
function offlineGoApply(MobileOfflineTestContext $context, string $operation, array $payload): MobileOfflineOutcome
{
    $handler   = app(MobileOfflineHandlerRegistry::class)->for($operation);
    $validated = $handler->validatePayload($payload);

    return DB::transaction(fn () => $handler->apply($context, $validated));
}

/**
 * @param array<string, mixed> $payload
 */
function offlineGoJournal(MobileOfflineTestContext $context, string $operation, array $payload, MobileOfflineOutcome $outcome): MobileOfflineOperation
{
    $operationRow = MobileOfflineOperation::create([
        'group_id'           => $context->user()->group_id,
        'organisation_id'    => $context->warehouse()->organisation_id,
        'warehouse_id'       => $context->warehouse()->id,
        'user_id'            => $context->user()->id,
        'device_uuid'        => (string) Str::uuid(),
        'device_sequence'    => 1,
        'operation'          => $operation,
        'kind'               => 'rules',
        'payload'            => $payload,
        'requested_quantity' => $outcome->requestedQuantity,
        'applied_quantity'   => $outcome->appliedQuantity,
        'performed_at'       => $context->performedAt(),
        'synced_at'          => now(),
        'counted_since'      => $outcome->countedSince,
        'state'              => $outcome->state,
    ]);

    foreach ($context->created as $model) {
        $operationRow->effects()->create(['model_type' => class_basename($model), 'model_id' => $model->getKey(), 'effect' => 'created']);
    }
    foreach ($context->updated as $update) {
        $operationRow->effects()->create(['model_type' => class_basename($update['model']), 'model_id' => $update['model']->getKey(), 'effect' => 'updated', 'before' => $update['before'], 'after' => $update['after']]);
    }

    return $operationRow->refresh();
}

function offlineGoStock(LocationOrgStock $locationOrgStock): float
{
    return (float) LocationOrgStock::whereKey($locationOrgStock->id)->value('quantity');
}

/**
 * @param array<int, float> $quantities
 * @return array{0: DeliveryNote, 1: array<int, DeliveryNoteItem>}
 */
function offlineGoPackingNote(object $ctx, User $packer, array $quantities = [2]): array
{
    [$deliveryNote, $lines] = goodsOutPickingNote($ctx, $quantities, $packer);

    foreach ($lines as $line) {
        StorePicking::make()->action($line, $packer, [
            'picker_user_id'        => $packer->id,
            'location_org_stock_id' => goodsOutLineLocation($line)->id,
            'quantity'              => (float) $line->quantity_required,
        ]);
    }

    UpdateDeliveryNoteStateToPicked::run($deliveryNote->refresh());
    StartPackingDeliveryNote::make()->action($deliveryNote->refresh(), $packer);

    return [$deliveryNote->refresh(), array_map(fn (DeliveryNoteItem $line) => $line->refresh(), $lines)];
}

test('offline pick adds a new picking, writes its stock movement in the same transaction and answers a resend as already done', function () {
    $picker   = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    [, [$line]] = goodsOutPickingNote($this, [5], $picker);
    $location = goodsOutLineLocation($line);
    $stock    = offlineGoStock($location);
    $context  = offlineGoContext($picker, $this->warehouse);
    $handler  = app(MobileOfflineHandlerRegistry::class)->for('pick');
    $payload  = offlineGoPayload($line, ['location_org_stock_id' => $location->id, 'quantity' => 2]);

    [$outcome, $movementInside] = DB::transaction(fn () => [
        $handler->apply($context, $handler->validatePayload($payload)),
        Picking::where('delivery_note_item_id', $line->id)->value('org_stock_movement_id'),
    ]);

    expect($outcome->state)->toBe(MobileOfflineOutcome::APPLIED)
        ->and($outcome->appliedQuantity)->toBe(2.0)
        ->and($outcome->countedSince)->toBeFalse()
        ->and($outcome->message)->toBe('Picked 2 at '.$location->location->code.'.')
        ->and($movementInside)->not->toBeNull()
        ->and($context->createdOf(Picking::class))->toHaveCount(1)
        ->and($context->createdOf(OrgStockMovement::class))->toHaveCount(1)
        ->and($context->updated[0]['before']['quantity_picked'])->toBe(0.0)
        ->and($context->updated[0]['after']['quantity_picked'])->toBe(2.0)
        ->and(Cache::has(MobileClientRequest::key($picker, $payload['client_request_id'])))->toBeTrue()
        ->and($handler->subject($payload))->toBe(['subject_type' => 'DeliveryNoteItem', 'subject_id' => $line->id, 'parent_type' => 'DeliveryNote', 'parent_id' => $line->delivery_note_id])
        ->and($handler->touchedRows($payload))->toBe([['t' => 'DeliveryNoteItem', 'id' => $line->id], ['t' => 'LocationOrgStock', 'id' => $location->id]])
        ->and($handler->kind())->toBe('rules');

    $again = offlineGoApply(offlineGoContext($picker, $this->warehouse), 'pick', offlineGoPayload($line, ['location_org_stock_id' => $location->id, 'quantity' => 1]));
    $resend = offlineGoApply(offlineGoContext($picker, $this->warehouse), 'pick', $payload);

    expect($again->state)->toBe(MobileOfflineOutcome::APPLIED)
        ->and($resend->state)->toBe(MobileOfflineOutcome::APPLIED)
        ->and($resend->message)->toBe('Already done.')
        ->and($line->pickings()->pluck('quantity')->map(fn ($quantity) => (float) $quantity)->all())->toBe([2.0, 1.0])
        ->and((float) $line->refresh()->quantity_picked)->toBe(3.0)
        ->and(offlineGoStock($location))->toBe($stock - 3);

    expect(fn () => $handler->validatePayload(['delivery_note_id' => $line->delivery_note_id, 'delivery_note_item_id' => $line->id, 'location_org_stock_id' => $location->id]))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('offline pick walk-through A: a line another picker finished is refused with put back and who picked it, a bigger line takes both', function () {
    $picker    = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    $colleague = createMobileAppUser($this->group, ['contact_name' => 'Colleague B']);
    [, [$line, $bigLine]] = goodsOutPickingNote($this, [3, 6], $picker);
    $location  = goodsOutLineLocation($line);

    foreach ([$line, $bigLine] as $pickedLine) {
        StorePicking::make()->action($pickedLine, $colleague, ['location_org_stock_id' => goodsOutLineLocation($pickedLine)->id, 'quantity' => 3, 'picker_user_id' => $colleague->id]);
    }
    $stock = offlineGoStock($location);

    $outcome = offlineGoApply(offlineGoContext($picker, $this->warehouse), 'pick', offlineGoPayload($line, ['location_org_stock_id' => $location->id, 'quantity' => 3]));

    expect($outcome->state)->toBe(MobileOfflineOutcome::REFUSED)
        ->and($outcome->reason)->toBe('over_required')
        ->and($outcome->instruction)->toBe(MobileOfflineOutcome::INSTRUCTION_PUT_BACK)
        ->and($outcome->conflictingUserId)->toBe($colleague->id)
        ->and($outcome->message)->toStartWith('Colleague B picked this line at ')
        ->and($outcome->message)->toEndWith('Put your 3 back at '.$location->location->code.'.')
        ->and($outcome->appliedQuantity)->toBe(0.0)
        ->and($line->pickings()->count())->toBe(1)
        ->and(offlineGoStock($location))->toBe($stock);

    $both = offlineGoApply(offlineGoContext($picker, $this->warehouse), 'pick', offlineGoPayload($bigLine, ['location_org_stock_id' => goodsOutLineLocation($bigLine)->id, 'quantity' => 3]));
    expect($both->state)->toBe(MobileOfflineOutcome::APPLIED)
        ->and((float) $bigLine->refresh()->quantity_picked)->toBe(6.0);
});

test('offline pick over what is left records what the line needs and asks to put the rest back', function () {
    $picker   = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    [, [$line]] = goodsOutPickingNote($this, [3], $picker);
    $location = goodsOutLineLocation($line);
    $stock    = offlineGoStock($location);

    $outcome = offlineGoApply(offlineGoContext($picker, $this->warehouse), 'pick', offlineGoPayload($line, ['location_org_stock_id' => $location->id, 'quantity' => 5]));

    expect($outcome->state)->toBe(MobileOfflineOutcome::PARTLY_APPLIED)
        ->and($outcome->reason)->toBe('over_required')
        ->and($outcome->instruction)->toBe(MobileOfflineOutcome::INSTRUCTION_PUT_BACK)
        ->and($outcome->requestedQuantity)->toBe(5.0)
        ->and($outcome->appliedQuantity)->toBe(3.0)
        ->and($outcome->message)->toBe('The line needed only 3. Put the other 2 back at '.$location->location->code.'.')
        ->and((float) $line->refresh()->quantity_picked)->toBe(3.0)
        ->and(offlineGoStock($location))->toBe($stock - 3);
});

test('offline pick walk-through B: an empty or short location is refused with keep in tote and a stock check, and applies without a stock change once counted', function () {
    $picker      = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    [, [$line]]  = goodsOutPickingNote($this, [2], $picker);
    $location    = goodsOutLineLocation($line);
    $performedAt = CarbonImmutable::now()->subMinutes(60);
    $location->update(['quantity' => 0]);

    $context = offlineGoContext($picker, $this->warehouse, $performedAt);
    $payload = offlineGoPayload($line, ['location_org_stock_id' => $location->id, 'quantity' => 2]);
    $empty   = offlineGoApply($context, 'pick', $payload);

    expect($empty->state)->toBe(MobileOfflineOutcome::REFUSED)
        ->and($empty->reason)->toBe('location_empty')
        ->and($empty->instruction)->toBe(MobileOfflineOutcome::INSTRUCTION_KEEP_IN_TOTE)
        ->and($context->stockChecks)->toHaveCount(1)
        ->and($context->stockChecks[0]['model']->is($location))->toBeTrue()
        ->and($line->pickings()->count())->toBe(0)
        ->and(Cache::has(MobileClientRequest::key($picker, $payload['client_request_id'])))->toBeFalse();

    $location->update(['quantity' => 1]);
    $shortContext = offlineGoContext($picker, $this->warehouse, $performedAt);
    $short        = offlineGoApply($shortContext, 'pick', $payload);
    expect($short->state)->toBe(MobileOfflineOutcome::REFUSED)
        ->and($short->reason)->toBe('not_enough_stock')
        ->and($short->instruction)->toBe(MobileOfflineOutcome::INSTRUCTION_KEEP_IN_TOTE)
        ->and($shortContext->stockChecks)->toHaveCount(1)
        ->and(offlineGoStock($location))->toBe(1.0);

    $location->update(['quantity' => 0, 'audited_at' => now()]);
    $retryContext = offlineGoContext($picker, $this->warehouse, $performedAt);
    $retry        = offlineGoApply($retryContext, 'pick', $payload);
    $picking      = Picking::where('delivery_note_item_id', $line->id)->firstOrFail();

    expect($retry->state)->toBe(MobileOfflineOutcome::APPLIED)
        ->and($retry->countedSince)->toBeTrue()
        ->and($retryContext->stockChecks)->toBe([])
        ->and((float) $line->refresh()->quantity_picked)->toBe(2.0)
        ->and(offlineGoStock($location))->toBe(0.0)
        ->and($picking->org_stock_movement_id)->not->toBeNull()
        ->and(collect($retryContext->createdOf(OrgStockMovement::class))->map(fn (OrgStockMovement $movement) => [$movement->type, (float) $movement->quantity])->all())
        ->toBe([[OrgStockMovementTypeEnum::CANCEL_PICKED, 2.0], [OrgStockMovementTypeEnum::PICKED, -2.0]]);
});

test('offline goods out handlers refuse what the online guards refuse, each with its reason and instruction', function () {
    $picker    = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    $colleague = createMobileAppUser($this->group);
    $context   = fn () => offlineGoContext($picker, $this->warehouse);
    $pick      = fn (DeliveryNoteItem $line, ?array $payload = null) => offlineGoApply($context(), 'pick', $payload ?? offlineGoPayload($line, ['location_org_stock_id' => goodsOutLineLocation($line)->id, 'quantity' => 1]));

    [, [$toDoLine]]  = goodsOutPickingNote($this, [2]);
    [, [$theirLine]] = goodsOutPickingNote($this, [2], $colleague);
    [$cancelled, [$cancelledLine]] = goodsOutPickingNote($this, [2], $picker);
    $cancelled->update(['state' => DeliveryNoteStateEnum::CANCELLED]);
    [$mine, [$mineLine]] = goodsOutPickingNote($this, [2], $picker);

    [$inSession, [$sessionLine]] = goodsOutPickingNote($this, [2]);
    StorePickingSession::make()->handle($this->warehouse, ['delivery_notes' => [$inSession->id], 'user_id' => $picker->id]);
    $inSession->refresh()->update(['state' => DeliveryNoteStateEnum::HANDLING, 'picker_user_id' => $picker->id]);

    $refusals = [
        'wrong_state'        => $pick($toDoLine),
        'not_handler'        => $pick($theirLine),
        'note_cancelled'     => $pick($cancelledLine),
        'in_picking_session' => $pick($sessionLine),
        'not_found'          => $pick($mineLine, offlineGoPayload($mineLine, ['delivery_note_id' => $cancelled->id, 'location_org_stock_id' => goodsOutLineLocation($mineLine)->id, 'quantity' => 1])),
    ];

    foreach ($refusals as $reason => $outcome) {
        expect($outcome->state)->toBe(MobileOfflineOutcome::REFUSED)
            ->and($outcome->reason)->toBe($reason)
            ->and($outcome->instruction)->toBe(MobileOfflineOutcome::INSTRUCTION_PUT_BACK)
            ->and($outcome->message)->not->toBeEmpty();
    }
    expect($refusals['not_handler']->conflictingUserId)->toBe($colleague->id)
        ->and(Picking::whereIn('delivery_note_item_id', [$toDoLine->id, $theirLine->id, $cancelledLine->id, $sessionLine->id, $mineLine->id])->count())->toBe(0);

    $outsider = createMobileAppUser($this->group);
    $noRight  = offlineGoApply(offlineGoContext($outsider, $this->warehouse), 'pick', offlineGoPayload($mineLine, ['location_org_stock_id' => goodsOutLineLocation($mineLine)->id, 'quantity' => 1]));
    expect($noRight->reason)->toBe('no_right');

    $wrongShelf = offlineGoApply($context(), 'pick', offlineGoPayload($mineLine, ['location_org_stock_id' => goodsOutLineLocation($theirLine)->id, 'quantity' => 1]));
    expect($wrongShelf->reason)->toBe('not_found');

    setGoodsOutPickingSettings($this->organisation, ['allow_waiting' => false]);
    $waitingOff = offlineGoApply($context(), 'set_waiting_warehouse', offlineGoPayload($mineLine, ['quantity' => 1]));
    $crmOff     = offlineGoApply($context(), 'set_waiting_crm', offlineGoPayload($mineLine, ['quantity' => 1]));
    setGoodsOutPickingSettings($this->organisation, ['allow_waiting' => true, 'allow_picker_set_not_picked' => false, 'allow_picker_choose_location' => false]);
    $notPickedOff = offlineGoApply($context(), 'not_picked', offlineGoPayload($mineLine, ['quantity' => 1, 'reason' => 'out-of-stock']));
    expect([$waitingOff->reason, $crmOff->reason, $notPickedOff->reason])->toBe(['setting_off', 'setting_off', 'setting_off'])
        ->and($notPickedOff->instruction)->toBe(MobileOfflineOutcome::INSTRUCTION_NONE)
        ->and((float) $mineLine->refresh()->quantity_waiting_warehouse)->toBe(0.0)
        ->and($mine->refresh()->state)->toBe(DeliveryNoteStateEnum::HANDLING);

    $invalid = fn () => app(MobileOfflineHandlerRegistry::class)->for('not_picked')->validatePayload(offlineGoPayload($mineLine, ['quantity' => 1, 'reason' => 'nope']));
    expect($invalid)->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('offline not picked records up to what is outstanding, refuses when nothing is left, and rolls back by deleting its mark', function () {
    $picker  = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    [, [$line]] = goodsOutPickingNote($this, [3], $picker);
    $handler = app(MobileOfflineHandlerRegistry::class)->for('not_picked');

    $firstContext = offlineGoContext($picker, $this->warehouse);
    $firstPayload = offlineGoPayload($line, ['quantity' => 2, 'reason' => 'out-of-stock', 'note' => 'Shelf empty']);
    $first        = offlineGoApply($firstContext, 'not_picked', $firstPayload);
    $partly       = offlineGoApply(offlineGoContext($picker, $this->warehouse), 'not_picked', offlineGoPayload($line, ['quantity' => 5, 'reason' => 'out-of-stock']));
    $nothing      = offlineGoApply(offlineGoContext($picker, $this->warehouse), 'not_picked', offlineGoPayload($line, ['quantity' => 1, 'reason' => 'out-of-stock']));

    expect($first->state)->toBe(MobileOfflineOutcome::APPLIED)
        ->and($first->message)->toBe('2 marked as not picked.')
        ->and($partly->state)->toBe(MobileOfflineOutcome::PARTLY_APPLIED)
        ->and($partly->appliedQuantity)->toBe(1.0)
        ->and($nothing->state)->toBe(MobileOfflineOutcome::REFUSED)
        ->and($nothing->reason)->toBe('over_required')
        ->and((float) $line->refresh()->quantity_not_picked)->toBe(3.0)
        ->and($line->pickings()->where('type', PickingTypeEnum::NOT_PICK)->count())->toBe(2);

    $journal = offlineGoJournal($firstContext, 'not_picked', $firstPayload, $first);
    expect($handler->canRollback($journal))->toBeTrue();

    $handler->rollback($journal, $picker);
    expect((float) $line->refresh()->quantity_not_picked)->toBe(1.0)
        ->and($handler->canRollback($journal))->toBeFalse()
        ->and(fn () => $handler->rollback($journal, $picker))->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('offline waiting for the warehouse replaces the waiting up to what is outstanding, and passing to customer services adds to it', function () {
    $picker = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    [, [$line, $crmLine]] = goodsOutPickingNote($this, [4, 4], $picker);

    $waiting = offlineGoApply(offlineGoContext($picker, $this->warehouse), 'set_waiting_warehouse', offlineGoPayload($line, ['quantity' => 3, 'note' => 'Behind the pallet']));
    expect($waiting->state)->toBe(MobileOfflineOutcome::APPLIED)
        ->and((float) $line->refresh()->quantity_waiting_warehouse)->toBe(3.0)
        ->and($line->notes)->toBe('Behind the pallet');

    $capped = offlineGoApply(offlineGoContext($picker, $this->warehouse), 'set_waiting_warehouse', offlineGoPayload($line, ['quantity' => 6]));
    expect($capped->state)->toBe(MobileOfflineOutcome::PARTLY_APPLIED)
        ->and($capped->requestedQuantity)->toBe(6.0)
        ->and($capped->appliedQuantity)->toBe(4.0)
        ->and((float) $line->refresh()->quantity_waiting_warehouse)->toBe(4.0);

    $crm = offlineGoApply(offlineGoContext($picker, $this->warehouse), 'set_waiting_crm', offlineGoPayload($crmLine, ['quantity' => 1]));
    $crmCapped = offlineGoApply(offlineGoContext($picker, $this->warehouse), 'set_waiting_crm', offlineGoPayload($crmLine, ['quantity' => 10]));
    expect($crm->state)->toBe(MobileOfflineOutcome::APPLIED)
        ->and($crmCapped->state)->toBe(MobileOfflineOutcome::PARTLY_APPLIED)
        ->and($crmCapped->appliedQuantity)->toBe(3.0)
        ->and((float) $crmLine->refresh()->quantity_waiting_crm)->toBe(4.0);

    $crmContext = offlineGoContext($picker, $this->warehouse);
    $nothing    = offlineGoApply($crmContext, 'set_waiting_crm', offlineGoPayload($crmLine, ['quantity' => 1]));
    expect($nothing->state)->toBe(MobileOfflineOutcome::REFUSED)
        ->and($nothing->reason)->toBe('over_required')
        ->and(app(MobileOfflineHandlerRegistry::class)->for('set_waiting_crm')->canRollback(offlineGoJournal($crmContext, 'set_waiting_crm', [], $crm)))->toBeFalse();
});

test('offline waiting walk-through D: a waiting done before the line\'s waiting was cleared is refused with pick again', function () {
    $picker    = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    [$deliveryNote, [$line, $otherLine]] = goodsOutPickingNote($this, [2, 2], $picker);
    $performedAt = CarbonImmutable::now()->subMinutes(20);

    SetAsWaitingWarehouse::make()->action($line, $picker, ['quantity' => 2]);
    UndoSetAsWaitingWarehouse::run($line->refresh());
    expect($line->refresh()->getRawOriginal('waiting_warehouse_cleared_at'))->not->toBeNull();

    $late = offlineGoApply(offlineGoContext($picker, $this->warehouse, $performedAt), 'set_waiting_warehouse', offlineGoPayload($line, ['quantity' => 2]));
    expect($late->state)->toBe(MobileOfflineOutcome::REFUSED)
        ->and($late->reason)->toBe('waiting_cleared')
        ->and($late->instruction)->toBe(MobileOfflineOutcome::INSTRUCTION_PICK_AGAIN)
        ->and($late->message)->toStartWith('The waiting on this line was cleared at ')
        ->and((float) $line->refresh()->quantity_waiting_warehouse)->toBe(0.0);

    $afterTheClear = offlineGoApply(offlineGoContext($picker, $this->warehouse, CarbonImmutable::now()->addMinute()), 'set_waiting_warehouse', offlineGoPayload($line, ['quantity' => 2]));
    expect($afterTheClear->state)->toBe(MobileOfflineOutcome::APPLIED);

    SetAsWaitingWarehouse::make()->action($otherLine, $picker, ['quantity' => 1]);
    $deliveryNote->refresh()->update(['state' => DeliveryNoteStateEnum::HANDLING_BLOCKED]);
    UndoWaitingDeliveryNote::make()->action($deliveryNote->refresh());
    expect($otherLine->refresh()->getRawOriginal('waiting_warehouse_cleared_at'))->not->toBeNull()
        ->and((float) $otherLine->quantity_waiting_warehouse)->toBe(0.0);
});

test('offline waiting for the warehouse rolls back while its amount is unchanged, passing to customer services never does', function () {
    $picker  = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    [, [$line, $changedLine]] = goodsOutPickingNote($this, [4, 4], $picker);
    $handler = app(MobileOfflineHandlerRegistry::class)->for('set_waiting_warehouse');

    $context = offlineGoContext($picker, $this->warehouse);
    $payload = offlineGoPayload($line, ['quantity' => 2]);
    $journal = offlineGoJournal($context, 'set_waiting_warehouse', $payload, offlineGoApply($context, 'set_waiting_warehouse', $payload));

    $changedContext = offlineGoContext($picker, $this->warehouse);
    $changedPayload = offlineGoPayload($changedLine, ['quantity' => 2]);
    $changedJournal = offlineGoJournal($changedContext, 'set_waiting_warehouse', $changedPayload, offlineGoApply($changedContext, 'set_waiting_warehouse', $changedPayload));
    SetAsWaitingWarehouse::make()->action($changedLine->refresh(), $picker, ['quantity' => 3]);

    expect($handler->canRollback($journal))->toBeTrue()
        ->and($handler->canRollback($changedJournal))->toBeFalse();

    $handler->rollback($journal, $picker);
    expect((float) $line->refresh()->quantity_waiting_warehouse)->toBe(0.0)
        ->and(app(MobileOfflineHandlerRegistry::class)->for('set_waiting_crm')->canRollback($journal))->toBeFalse();
});

test('offline pick rolls back by deleting its picking while the note is being picked, giving the stock back', function () {
    $picker   = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    [$deliveryNote, [$line]] = goodsOutPickingNote($this, [5], $picker);
    $location = goodsOutLineLocation($line);
    $stock    = offlineGoStock($location);
    $handler  = app(MobileOfflineHandlerRegistry::class)->for('pick');

    $context = offlineGoContext($picker, $this->warehouse);
    $payload = offlineGoPayload($line, ['location_org_stock_id' => $location->id, 'quantity' => 2]);
    $journal = offlineGoJournal($context, 'pick', $payload, offlineGoApply($context, 'pick', $payload));

    expect($handler->canRollback($journal))->toBeTrue();
    $handler->rollback($journal, $picker);

    expect((float) $line->refresh()->quantity_picked)->toBe(0.0)
        ->and(offlineGoStock($location))->toBe($stock)
        ->and($handler->canRollback($journal))->toBeFalse();

    $context = offlineGoContext($picker, $this->warehouse);
    $payload = offlineGoPayload($line, ['location_org_stock_id' => $location->id, 'quantity' => 2]);
    $journal = offlineGoJournal($context, 'pick', $payload, offlineGoApply($context, 'pick', $payload));
    $deliveryNote->update(['state' => DeliveryNoteStateEnum::PICKED]);
    expect($handler->canRollback($journal))->toBeFalse();
});

test('offline pack records up to what is left to pack, refuses over-packing with unpack, needs the note in packing and its packer, and rolls back', function () {
    $packer    = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    $colleague = createMobileAppUser($this->group);
    [, [$line, $rollbackLine]] = offlineGoPackingNote($this, $packer, [2, 2]);
    $handler   = app(MobileOfflineHandlerRegistry::class)->for('pack');
    $pack      = fn (DeliveryNoteItem $packedLine, float $quantity) => offlineGoApply(offlineGoContext($packer, $this->warehouse), 'pack', offlineGoPayload($packedLine, ['quantity' => $quantity]));

    $first  = $pack($line, 1);
    $partly = $pack($line, 5);
    $over   = $pack($line, 1);

    expect($first->state)->toBe(MobileOfflineOutcome::APPLIED)
        ->and($first->message)->toBe('1 packed.')
        ->and($partly->state)->toBe(MobileOfflineOutcome::PARTLY_APPLIED)
        ->and($partly->reason)->toBe('over_packed')
        ->and($partly->instruction)->toBe(MobileOfflineOutcome::INSTRUCTION_UNPACK)
        ->and($partly->appliedQuantity)->toBe(1.0)
        ->and($partly->message)->toBe('Only 1 was left to pack on this line. Unpack the other 4.')
        ->and($over->state)->toBe(MobileOfflineOutcome::REFUSED)
        ->and($over->reason)->toBe('over_packed')
        ->and($over->instruction)->toBe(MobileOfflineOutcome::INSTRUCTION_UNPACK)
        ->and((float) Packing::where('delivery_note_item_id', $line->id)->sum('quantity'))->toBe(2.0);

    $context = offlineGoContext($packer, $this->warehouse);
    $payload = offlineGoPayload($rollbackLine, ['quantity' => 2]);
    $journal = offlineGoJournal($context, 'pack', $payload, offlineGoApply($context, 'pack', $payload));
    expect($handler->canRollback($journal))->toBeTrue()
        ->and($handler->canRollback(offlineGoJournal(offlineGoContext($packer, $this->warehouse), 'pack', offlineGoPayload($line, ['quantity' => 1]), $first)))->toBeFalse();
    $handler->rollback($journal, $packer);
    expect(Packing::where('delivery_note_item_id', $rollbackLine->id)->count())->toBe(0);

    [, [$pickingLine]] = goodsOutPickingNote($this, [2], $packer);
    [, [$theirLine]]   = offlineGoPackingNote($this, $colleague, [2]);
    $wrongState = $pack($pickingLine, 1);
    $notHandler = $pack($theirLine, 1);

    expect([$wrongState->reason, $notHandler->reason])->toBe(['wrong_state', 'not_handler'])
        ->and($wrongState->instruction)->toBe(MobileOfflineOutcome::INSTRUCTION_UNPACK)
        ->and($notHandler->conflictingUserId)->toBe($colleague->id);
});

test('online goods out writes take an optional client request id, and the offline handlers share its key both ways', function () {
    $picker   = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    [$deliveryNote, [$line, $waitingLine, $notPickedLine]] = goodsOutPickingNote($this, [5, 3, 3], $picker);
    $location = goodsOutLineLocation($line);
    $id       = (string) Str::uuid();
    $pick     = fn (array $body) => postGoodsOut('grp.api.mobile.delivery_notes.lines.pick', [$deliveryNote->id, $line->id], $body);

    $pick(['location_org_stock_id' => $location->id, 'quantity' => 2, 'client_request_id' => $id])->assertOk()->assertJsonPath('result.status', 'done');
    $pick(['location_org_stock_id' => $location->id, 'quantity' => 4, 'client_request_id' => $id])->assertOk()
        ->assertJsonPath('result', ['status' => 'nothing_changed', 'message' => 'Already done.']);
    $pick(['location_org_stock_id' => $location->id, 'quantity' => 1, 'client_request_id' => 'not-a-uuid'])->assertUnprocessable()->assertJsonValidationErrors(['client_request_id']);

    $offline = offlineGoApply(offlineGoContext($picker, $this->warehouse), 'pick', offlineGoPayload($line, ['location_org_stock_id' => $location->id, 'quantity' => 2, 'client_request_id' => $id]));
    expect($offline->message)->toBe('Already done.')
        ->and((float) $line->refresh()->quantity_picked)->toBe(2.0);

    $offlineId = (string) Str::uuid();
    offlineGoApply(offlineGoContext($picker, $this->warehouse), 'pick', offlineGoPayload($line, ['location_org_stock_id' => $location->id, 'quantity' => 1, 'client_request_id' => $offlineId]));
    $pick(['location_org_stock_id' => $location->id, 'quantity' => 5, 'client_request_id' => $offlineId])->assertOk()->assertJsonPath('result.message', 'Already done.');
    expect((float) $line->refresh()->quantity_picked)->toBe(3.0);

    $pick(['location_org_stock_id' => $location->id, 'quantity' => 4])->assertOk()->assertJsonPath('result.status', 'done');

    foreach ([
        ['grp.api.mobile.delivery_notes.lines.waiting', $waitingLine, ['kind' => 'warehouse', 'quantity' => 1]],
        ['grp.api.mobile.delivery_notes.lines.not_picked', $notPickedLine, ['quantity' => 3, 'reason' => 'out-of-stock']],
        ['grp.api.mobile.delivery_notes.lines.pick_all', $line, []],
    ] as [$route, $routeLine, $body]) {
        $requestId = (string) Str::uuid();
        postGoodsOut($route, [$deliveryNote->id, $routeLine->id], [...$body, 'client_request_id' => $requestId])->assertOk();
        postGoodsOut($route, [$deliveryNote->id, $routeLine->id], [...$body, 'client_request_id' => $requestId])->assertOk()
            ->assertJsonPath('result', ['status' => 'nothing_changed', 'message' => 'Already done.']);
    }

    expect((float) $notPickedLine->refresh()->quantity_not_picked)->toBe(3.0)
        ->and((float) $waitingLine->refresh()->quantity_waiting_warehouse)->toBe(1.0);

    [$packingNote, [$packingLine]] = offlineGoPackingNote($this, $picker, [2]);
    $packId = (string) Str::uuid();
    postGoodsOut('grp.api.mobile.delivery_notes.lines.pack', [$packingNote->id, $packingLine->id], ['quantity' => 1, 'client_request_id' => $packId])->assertOk();
    postGoodsOut('grp.api.mobile.delivery_notes.lines.pack', [$packingNote->id, $packingLine->id], ['quantity' => 1, 'client_request_id' => $packId])->assertOk()
        ->assertJsonPath('result.message', 'Already done.');
    expect((float) Packing::where('delivery_note_item_id', $packingLine->id)->sum('quantity'))->toBe(1.0);
});

test('mobile delivery note page sends the location versions, line barcodes, picking session flag and picking settings for offline work', function () {
    $picker   = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    [$deliveryNote, [$line]] = goodsOutPickingNote($this, [5], $picker);
    $location = goodsOutLineLocation($line);
    $location->update(['quantity' => 12]);
    StorePicking::make()->action($line, $picker, ['location_org_stock_id' => $location->id, 'quantity' => 1, 'picker_user_id' => $picker->id]);
    $version  = (int) LocationOrgStock::whereKey($location->id)->value('version');

    $response = getGoodsOut('grp.api.mobile.delivery_notes.show', $deliveryNote->id)->assertOk();
    $lineData = collect($response->json('data.lines'))->firstWhere('id', $line->id);

    expect($version)->toBeGreaterThan(1)
        ->and($lineData['locations'][0]['location_org_stock_id'])->toBe($location->id)
        ->and($lineData['locations'][0]['version'])->toBe($version)
        ->and($lineData['pickings'][0]['version'])->toBe($version)
        ->and($lineData['barcodes'])->toBe([$line->orgStock->barcode])
        ->and($lineData['in_picking_session'])->toBeFalse()
        ->and($response->json('data.picking_settings'))->toBe($response->json('data.settings'))
        ->and(array_keys($response->json('data.picking_settings')))->toBe(['allow_scan_to_pick', 'allow_waiting', 'allow_picker_set_not_picked', 'allow_picker_choose_location', 'allow_scan_to_pack']);
});

/**
 * @return array{picked: float, packed: float, waiting_warehouse: float, stock: float}
 */
function offlineGoScenario(object $ctx, User $picker, string $case, bool $offline): array
{
    $earlier = CarbonImmutable::now()->subMinutes(30);
    $context = fn (?CarbonImmutable $performedAt = null) => offlineGoContext($picker, $ctx->warehouse, $performedAt);

    if ($case === 'over-pack') {
        [$deliveryNote, [$line]] = offlineGoPackingNote($ctx, $picker, [2]);
        if ($offline) {
            offlineGoApply($context(), 'pack', offlineGoPayload($line, ['quantity' => 3]));
        } else {
            postGoodsOut('grp.api.mobile.delivery_notes.lines.pack', [$deliveryNote->id, $line->id], ['quantity' => 3])->assertUnprocessable();
            postGoodsOut('grp.api.mobile.delivery_notes.lines.pack', [$deliveryNote->id, $line->id], ['quantity' => 2])->assertOk();
        }
    } else {
        [$deliveryNote, [$line]] = goodsOutPickingNote($ctx, [$case === 'pick more than is left' ? 3 : 2], $picker);
        $location = goodsOutLineLocation($line);
        $location->update(['quantity' => $case === 'pick from an empty location' ? 0 : 10]);
        $pick = ['location_org_stock_id' => $location->id, 'quantity' => $case === 'pick more than is left' ? 5 : 2];

        match ($case) {
            'pick more than is left', 'pick from an empty location' => $offline
                ? offlineGoApply($context(), 'pick', offlineGoPayload($line, $pick))
                : postGoodsOut('grp.api.mobile.delivery_notes.lines.pick', [$deliveryNote->id, $line->id], $pick)->assertOk(),
            'pick after the location was counted' => $offline
                ? [$location->update(['quantity' => 8, 'audited_at' => now()]), offlineGoApply($context($earlier), 'pick', offlineGoPayload($line, $pick))]
                : [postGoodsOut('grp.api.mobile.delivery_notes.lines.pick', [$deliveryNote->id, $line->id], $pick)->assertOk(), $location->update(['quantity' => 8, 'audited_at' => now()])],
            'waiting after a clear' => $offline
                ? [SetAsWaitingWarehouse::make()->action($line, $picker, ['quantity' => 2]), UndoSetAsWaitingWarehouse::run($line->refresh()), offlineGoApply($context($earlier), 'set_waiting_warehouse', offlineGoPayload($line, ['quantity' => 2]))]
                : [postGoodsOut('grp.api.mobile.delivery_notes.lines.waiting', [$deliveryNote->id, $line->id], ['kind' => 'warehouse', 'quantity' => 2])->assertOk(), UndoSetAsWaitingWarehouse::run($line->refresh())],
        };
    }

    $line->refresh();

    return [
        'picked'            => (float) $line->quantity_picked,
        'packed'            => (float) Packing::where('delivery_note_item_id', $line->id)->sum('quantity'),
        'waiting_warehouse' => (float) $line->quantity_waiting_warehouse,
        'stock'             => offlineGoStock(goodsOutLineLocation($line)),
    ];
}

test('online mobile actions and offline handlers end with the same stock and line totals', function (string $case, array $expected) {
    $picker = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);

    $online  = offlineGoScenario($this, $picker, $case, false);
    $offline = offlineGoScenario($this, $picker, $case, true);

    expect($offline)->toBe($online)
        ->and($online)->toBe($expected);
})->with([
    'pick more than is left'              => ['pick more than is left', ['picked' => 3.0, 'packed' => 0.0, 'waiting_warehouse' => 0.0, 'stock' => 7.0]],
    'pick from an empty location'         => ['pick from an empty location', ['picked' => 0.0, 'packed' => 0.0, 'waiting_warehouse' => 0.0, 'stock' => 0.0]],
    'pick after the location was counted' => ['pick after the location was counted', ['picked' => 2.0, 'packed' => 0.0, 'waiting_warehouse' => 0.0, 'stock' => 8.0]],
    'waiting after a clear'               => ['waiting after a clear', ['picked' => 0.0, 'packed' => 0.0, 'waiting_warehouse' => 0.0, 'stock' => 10.0]],
    'over-pack'                           => ['over-pack', ['picked' => 2.0, 'packed' => 2.0, 'waiting_warehouse' => 0.0, 'stock' => 28.0]],
]);

/**
 * @param array<int, array<string, mixed>> $actions
 */
function offlineGoSync(Warehouse $warehouse, string $device, array $actions): \Illuminate\Testing\TestResponse
{
    return \Pest\Laravel\postJson(route('grp.api.mobile.warehouses.offline.sync', $warehouse->id), ['actions' => $actions], offlineGoHeaders($device));
}

/**
 * @return array<string, string>
 */
function offlineGoHeaders(string $device): array
{
    return [
        'X-Aiku-App-Key'     => 'mobile-test-key',
        'X-Aiku-Device'      => $device,
        'X-Aiku-Platform'    => 'android',
        'X-Aiku-App-Version' => '1.4.2 (52)',
    ];
}

/**
 * @param array<string, mixed> $payload
 * @return array<string, mixed>
 */
function offlineGoAction(int $sequence, string $operation, array $payload, ?CarbonImmutable $performedAt = null): array
{
    return [
        'sequence'     => $sequence,
        'operation'    => $operation,
        'performed_at' => ($performedAt ?? CarbonImmutable::now()->subMinutes(5))->toIso8601ZuluString('millisecond'),
        'time_source'  => 'server',
        'seen_at'      => CarbonImmutable::now()->subMinutes(30)->toIso8601ZuluString('millisecond'),
        'payload'      => $payload,
    ];
}

test('sync endpoint runs an offline pick, journals its picking and rolls it back', function () {
    $picker   = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    [, [$line, $fullLine]] = goodsOutPickingNote($this, [3, 1], $picker);
    $location = goodsOutLineLocation($line);
    $stock    = offlineGoStock($location);
    $device   = (string) Str::uuid();

    $response = offlineGoSync($this->warehouse, $device, [
        offlineGoAction(1, 'pick', offlineGoPayload($line, ['location_org_stock_id' => $location->id, 'quantity' => 5])),
        offlineGoAction(2, 'pick', offlineGoPayload($fullLine, ['location_org_stock_id' => goodsOutLineLocation($fullLine)->id, 'quantity' => 0])),
    ])->assertOk();

    expect($response->json('data.results.0'))->toMatchArray([
        'sequence'           => 1,
        'status'             => 'partly_applied',
        'operation'          => 'pick',
        'reason'             => 'over_required',
        'instruction'        => 'put_back',
        'requested_quantity' => 5,
        'applied_quantity'   => 3,
        'counted_since'      => false,
    ])
        ->and($response->json('data.results.1.status'))->toBe('refused')
        ->and($response->json('data.results.1.reason'))->toBe('invalid')
        ->and(collect($response->json('data.results.0.versions'))->firstWhere('location_org_stock_id', $location->id)['quantity'])->toEqual($stock - 3)
        ->and((float) $line->refresh()->quantity_picked)->toBe(3.0);

    $operation = MobileOfflineOperation::findOrFail($response->json('data.results.0.operation_id'));
    expect($operation->subject_type)->toBe('DeliveryNoteItem')
        ->and($operation->parent_type)->toBe('DeliveryNote')
        ->and($operation->effects()->where('model_type', 'Picking')->where('effect', 'created')->count())->toBe(1)
        ->and($operation->effects()->where('model_type', 'LocationOrgStock')->count())->toBe(1);

    \Pest\Laravel\postJson(route('grp.api.mobile.offline.operations.rollback', $operation->id), [], offlineGoHeaders($device))->assertOk();
    expect((float) $line->refresh()->quantity_picked)->toBe(0.0)
        ->and(offlineGoStock($location))->toBe($stock);
});

test('sync endpoint runs an offline not picked and an offline waiting for the warehouse refused after a clear', function () {
    $picker = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    [, [$line, $waitingLine]] = goodsOutPickingNote($this, [3, 2], $picker);
    SetAsWaitingWarehouse::make()->action($waitingLine, $picker, ['quantity' => 2]);
    UndoSetAsWaitingWarehouse::run($waitingLine->refresh());

    $response = offlineGoSync($this->warehouse, (string) Str::uuid(), [
        offlineGoAction(1, 'not_picked', offlineGoPayload($line, ['quantity' => 2, 'reason' => 'out-of-stock'])),
        offlineGoAction(2, 'set_waiting_warehouse', offlineGoPayload($waitingLine, ['quantity' => 2]), CarbonImmutable::now()->subMinutes(10)),
    ])->assertOk();

    expect($response->json('data.results.0.status'))->toBe('applied')
        ->and($response->json('data.results.0.message'))->toBe('2 marked as not picked.')
        ->and($response->json('data.results.1'))->toMatchArray(['status' => 'refused', 'reason' => 'waiting_cleared', 'instruction' => 'pick_again'])
        ->and($response->json('data.open_count'))->toBe(1)
        ->and((float) $line->refresh()->quantity_not_picked)->toBe(2.0)
        ->and((float) $waitingLine->refresh()->quantity_waiting_warehouse)->toBe(0.0);
});

test('sync endpoint runs an offline pass to customer services and an offline pick from an empty location with a stock check task', function () {
    $picker = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    [, [$crmLine, $emptyLine]] = goodsOutPickingNote($this, [3, 2], $picker);
    $emptyLocation = goodsOutLineLocation($emptyLine);
    $emptyLocation->update(['quantity' => 0]);

    $response = offlineGoSync($this->warehouse, (string) Str::uuid(), [
        offlineGoAction(1, 'set_waiting_crm', offlineGoPayload($crmLine, ['quantity' => 1, 'note' => 'Ask the customer'])),
        offlineGoAction(2, 'pick', offlineGoPayload($emptyLine, ['location_org_stock_id' => $emptyLocation->id, 'quantity' => 2])),
    ])->assertOk();

    expect($response->json('data.results.0.status'))->toBe('applied')
        ->and($response->json('data.results.1'))->toMatchArray(['status' => 'refused', 'reason' => 'location_empty', 'instruction' => 'keep_in_tote'])
        ->and($response->json('data.results.1.task_reference'))->toStartWith('TASK-')
        ->and((float) $crmLine->refresh()->quantity_waiting_crm)->toBe(1.0)
        ->and(MobileOfflineOperation::findOrFail($response->json('data.results.1.operation_id'))->staff_task_id)->not->toBeNull();
});

test('sync endpoint runs an offline pack and refuses the over-pack after it', function () {
    $packer = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    [, [$line]] = offlineGoPackingNote($this, $packer, [2, 2]);
    $device = (string) Str::uuid();

    $response = offlineGoSync($this->warehouse, $device, [
        offlineGoAction(1, 'pack', offlineGoPayload($line, ['quantity' => 2])),
        offlineGoAction(2, 'pack', offlineGoPayload($line, ['quantity' => 1])),
    ])->assertOk();

    expect($response->json('data.results.0.status'))->toBe('applied')
        ->and($response->json('data.results.1'))->toMatchArray(['status' => 'refused', 'reason' => 'over_packed', 'instruction' => 'unpack'])
        ->and((float) Packing::where('delivery_note_item_id', $line->id)->sum('quantity'))->toBe(2.0);

    \Pest\Laravel\postJson(route('grp.api.mobile.offline.operations.rollback', $response->json('data.results.0.operation_id')), [], offlineGoHeaders($device))->assertOk();
    expect(Packing::where('delivery_note_item_id', $line->id)->count())->toBe(0);
});
