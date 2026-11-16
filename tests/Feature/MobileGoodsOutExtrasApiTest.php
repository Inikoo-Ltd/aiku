<?php

/*
 * Author Louis Perez
 * Created on 13-11-2026-15h-47m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Feature;

use App\Actions\Chat\Staff\SendStaffMessage;
use App\Actions\Chat\Staff\TranslateStaffMessage;
use App\Actions\Dispatching\PickedBay\AttachDeliveryNoteToPickedBay;
use App\Actions\Dispatching\PickedBay\StorePickedBay;
use App\Actions\Dispatching\Picking\StorePicking;
use App\Actions\Dispatching\PickingSession\StartPickPickingSession;
use App\Actions\Dispatching\PickingSession\StorePickingSession;
use App\Actions\Dispatching\Shipment\StoreShipment;
use App\Actions\Dispatching\Shipper\StoreShipper;
use App\Actions\Dispatching\Trolley\AttachTrolleyToPickingSession;
use App\Actions\Dispatching\Trolley\StoreTrolley;
use App\Actions\Inventory\Warehouse\StoreWarehouse;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Enums\Dispatching\Picking\PickingTypeEnum;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Events\StaffConversationRead;
use App\Events\StaffMessageSent;
use App\Models\Chat\StaffConversation;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Dispatching\Picking;
use App\Models\Inventory\PickingSession;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

require_once __DIR__.'/../Support/MobileApiHelpers.php';
require_once __DIR__.'/../Support/MobileGoodsOutHelpers.php';
require_once __DIR__.'/../Support/DispatchingHelpers.php';
require_once __DIR__.'/../Support/ChatHelpers.php';

function mobileExtrasPick(DeliveryNoteItem $line, User $picker, float $quantity): void
{
    StorePicking::make()->action($line, $picker, [
        'picker_user_id'        => $picker->id,
        'location_org_stock_id' => goodsOutLineLocation($line)->id,
        'quantity'              => $quantity,
    ]);
}

function mobileExtrasSession(object $ctx, DeliveryNote $deliveryNote, User $user): PickingSession
{
    return StorePickingSession::make()->handle($ctx->warehouse, [
        'delivery_notes' => [$deliveryNote->id],
        'user_id'        => $user->id,
    ])->refresh();
}

/**
 * @return array<string, mixed>
 */
function mobileExtrasAddress(): array
{
    return [
        'address_line_1'      => '12 High Street',
        'address_line_2'      => null,
        'sorting_code'        => null,
        'postal_code'         => 'S1 2AB',
        'locality'            => 'Sheffield',
        'dependent_locality'  => null,
        'administrative_area' => null,
        'country_code'        => 'GB',
    ];
}

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    [$this->organisation, $this->user, $this->shop] = createShop();
    $this->group            = $this->organisation->group;
    $this->product          = createProduct($this->shop)[1];
    $this->customer         = createCustomer($this->shop);
    $this->order            = createOrder($this->customer, $this->product);
    $this->warehouse        = StoreWarehouse::make()->action($this->organisation, Warehouse::factory()->definition());
    $this->originalSettings = $this->organisation->settings;
    $this->originalEngine   = $this->shop->engine;
});

afterEach(function () {
    $this->organisation->update(['settings' => $this->originalSettings]);
    $this->shop->update(['engine' => $this->originalEngine]);
});

test('mobile picked bays carry the notes in them and how many of those wait', function () {
    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    $bay       = StorePickedBay::make()->handle($this->warehouse, ['code' => 'A'.Str::random(6)]);
    [$waiting] = goodsOutPickingNote($this);
    [$picked]  = goodsOutPickingNote($this);
    $waiting->update(['state' => DeliveryNoteStateEnum::HANDLING_BLOCKED]);
    $picked->update(['state' => DeliveryNoteStateEnum::PICKED]);
    AttachDeliveryNoteToPickedBay::run($bay, $waiting);
    AttachDeliveryNoteToPickedBay::run($bay, $picked);

    $row = collect(getGoodsOut('grp.api.mobile.warehouses.picked_bays', $this->warehouse->id)->assertOk()->json('data'))->firstWhere('id', $bay->id);

    expect($row)->toBe([
        'id'                    => $bay->id,
        'code'                  => $bay->code,
        'number_delivery_notes' => 2,
        'number_waiting'        => 1,
        'delivery_notes'        => collect([
            ['id' => $waiting->id, 'reference' => $waiting->reference, 'state' => 'handling_blocked', 'state_label' => 'Waiting'],
            ['id' => $picked->id, 'reference' => $picked->reference, 'state' => 'picked', 'state_label' => 'Picked'],
        ])->sortBy('reference')->values()->all(),
    ]);
});

test('mobile offers cancel to supervisors only and cancels with a return of what was picked, or without one', function () {
    $clerk = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    [$withReturn, [$line]]           = goodsOutPickingNote($this, [3], $clerk);
    [$withoutReturn, [$otherLine]]   = goodsOutPickingNote($this, [3], $clerk);
    mobileExtrasPick($line, $clerk, 3);
    mobileExtrasPick($otherLine, $clerk, 3);
    $reference = $withReturn->reference;

    expect(goodsOutActionKeys($withReturn))->not->toContain('cancel');
    postGoodsOut('grp.api.mobile.delivery_notes.cancel', $withReturn->id)->assertForbidden();

    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_SUPERVISOR]);
    expect(collect(getGoodsOut('grp.api.mobile.delivery_notes.show', $withReturn->id)->json('data.available_actions'))->firstWhere('key', 'cancel'))
        ->toBe(['key' => 'cancel', 'label' => 'Cancel delivery note']);
    postGoodsOut('grp.api.mobile.delivery_notes.cancel', $withReturn->id, ['create_return' => 'maybe'])->assertUnprocessable()->assertJsonValidationErrors(['create_return']);

    $cancelled = postGoodsOut('grp.api.mobile.delivery_notes.cancel', $withReturn->id)->assertOk()
        ->assertJsonPath('result', ['status' => 'done', 'message' => 'Delivery note cancelled.'])
        ->assertJsonPath('data.state', 'cancelled')
        ->assertJsonPath('data.reference', $reference.'-CANCELLED')
        ->assertJsonPath('data.available_actions', [])
        ->assertJsonPath('data.return_delivery_note.state_label', 'Received');
    expect($cancelled->json('data.return_delivery_note.reference'))->toContain($reference)
        ->and(Picking::where('delivery_note_item_id', $line->id)->where('type', PickingTypeEnum::PICK)->exists())->toBeTrue();

    postGoodsOut('grp.api.mobile.delivery_notes.cancel', $withReturn->id)->assertUnprocessable()->assertJsonValidationErrors(['state']);

    postGoodsOut('grp.api.mobile.delivery_notes.cancel', $withoutReturn->id, ['create_return' => false])->assertOk()
        ->assertJsonPath('data.state', 'cancelled')
        ->assertJsonPath('data.return_delivery_note', null);
    expect(Picking::where('delivery_note_item_id', $otherLine->id)->where('type', PickingTypeEnum::PICK)->exists())->toBeFalse();
});

test('mobile does not cancel a finalised note nor a Faire order, but cancels a Faire replacement', function () {
    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_SUPERVISOR]);
    [$finalised] = finalisedDeliveryNote($this);

    expect(goodsOutActionKeys($finalised))->not->toContain('cancel');
    postGoodsOut('grp.api.mobile.delivery_notes.cancel', $finalised->id)->assertUnprocessable()->assertJsonValidationErrors(['state']);
    expect($finalised->refresh()->state)->toBe(DeliveryNoteStateEnum::FINALISED);

    [$order]     = goodsOutPickingNote($this);
    $replacement = goodsOutDeliveryNote($this->order, $this->warehouse, ['type' => DeliveryNoteTypeEnum::REPLACEMENT]);
    $this->shop->update(['engine' => ShopEngineEnum::FAIRE]);

    expect(goodsOutActionKeys($order))->not->toContain('cancel')
        ->and(goodsOutActionKeys($replacement))->toContain('cancel');
    postGoodsOut('grp.api.mobile.delivery_notes.cancel', $order->id)->assertUnprocessable()->assertJsonValidationErrors(['state']);
    postGoodsOut('grp.api.mobile.delivery_notes.cancel', $replacement->id)->assertOk()->assertJsonPath('data.state', 'cancelled');
    expect($order->refresh()->state)->toBe(DeliveryNoteStateEnum::UNASSIGNED);
});

test('cancel writes one not-pick per picking, and StoreNotPickPicking resets its quantity to what is left, hiding the picked_quantity typo of CancelDeliveryNote', function () {
    $supervisor = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_SUPERVISOR]);
    $cancelled  = function (float $picked, bool $createReturn) use ($supervisor): array {
        [$deliveryNote, [$line]] = goodsOutPickingNote($this, [3], $supervisor);
        if ($picked > 0) {
            mobileExtrasPick($line, $supervisor, $picked);
        }

        postGoodsOut('grp.api.mobile.delivery_notes.cancel', $deliveryNote->id, ['create_return' => $createReturn])->assertOk();
        $line->refresh();

        return [
            $line->quantity_picked === null ? null : (float) $line->quantity_picked,
            $line->quantity_not_picked === null ? null : (float) $line->quantity_not_picked,
            Picking::where('delivery_note_item_id', $line->id)->orderBy('id')->get()->map(fn (Picking $picking) => [$picking->type->value, (float) $picking->quantity])->all(),
        ];
    };

    expect($cancelled(3, true))->toBe([3.0, 0.0, [['pick', 3.0], ['not-pick', 0.0]]])
        ->and($cancelled(1, true))->toBe([1.0, 2.0, [['pick', 1.0], ['not-pick', 2.0]]])
        ->and($cancelled(1, false))->toBe([0.0, 3.0, [['not-pick', 3.0]]])
        ->and($cancelled(0, true))->toBe([null, null, []]);
});

test('mobile saves the warehouse and shipping notes for whoever the web lets write them', function () {
    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    [$deliveryNote] = goodsOutPickingNote($this);
    $save           = fn (array $body) => postGoodsOut('grp.api.mobile.delivery_notes.notes', $deliveryNote->id, $body);

    $save(['field' => 'warehouse', 'text' => 'Check the box'])->assertOk()
        ->assertJsonPath('result', ['status' => 'done', 'message' => 'Note saved.'])
        ->assertJsonPath('data.notes.warehouse', ['text' => 'Check the box', 'editable' => true]);
    expect($deliveryNote->orders()->first()->private_warehouse_note)->toBe('Check the box');

    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_VIEWER], ["orders.{$this->shop->id}.edit"]);
    getGoodsOut('grp.api.mobile.delivery_notes.show', $deliveryNote->id)->assertOk()
        ->assertJsonPath('data.is_editable', false)
        ->assertJsonPath('data.notes.shipping.editable', true);
    $save(['field' => 'shipping', 'text' => 'Leave with neighbour'])->assertOk()
        ->assertJsonPath('data.notes.shipping.text', 'Leave with neighbour');
    $save(['field' => 'warehouse', 'text' => null])->assertOk()
        ->assertJsonPath('data.notes.warehouse.text', '');
    $save(['field' => 'customer', 'text' => 'No'])->assertUnprocessable()->assertJsonValidationErrors(['field']);
    $save(['field' => 'shipping', 'text' => str_repeat('a', 4001)])->assertUnprocessable()->assertJsonValidationErrors(['text']);

    $deliveryNote->update(['handled_in_aurora' => true]);
    $save(['field' => 'shipping', 'text' => 'Locked'])->assertUnprocessable()->assertJsonValidationErrors(['delivery_note']);
    $deliveryNote->update(['handled_in_aurora' => false]);

    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_VIEWER]);
    $save(['field' => 'shipping', 'text' => 'Viewer'])->assertForbidden();
    expect($deliveryNote->refresh()->shipping_notes)->toBe('Leave with neighbour');
});

test('mobile changes the packer of a packing note to one of the pickers, unless the note is in a picking session', function () {
    $picker = goodsOutPickerUser($this);
    $clerk  = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    [$packing]   = goodsOutPickingNote($this, [2], $clerk);
    [$inSession] = goodsOutPickingNote($this);
    $packing->update(['state' => DeliveryNoteStateEnum::PACKING, 'packer_user_id' => $clerk->id]);
    $pickingSession = mobileExtrasSession($this, $inSession, $clerk);
    $inSession->refresh()->update(['state' => DeliveryNoteStateEnum::PACKING, 'packer_user_id' => $clerk->id]);
    $change = fn (DeliveryNote $deliveryNote, mixed $userId) => postGoodsOut('grp.api.mobile.delivery_notes.packer', $deliveryNote->id, ['user_id' => $userId]);

    expect(goodsOutActionKeys($packing))->toContain('change_packer')
        ->and(goodsOutActionKeys($inSession))->not->toContain('change_packer');

    $change($packing, $clerk->id)->assertUnprocessable()->assertJsonValidationErrors(['user_id']);
    $change($packing, $picker->id)->assertOk()
        ->assertJsonPath('result', ['status' => 'done', 'message' => 'Packer changed.'])
        ->assertJsonPath('data.packer', ['id' => $picker->id, 'name' => $picker->contact_name ?? $picker->username]);
    $change($packing, $picker->id)->assertOk()->assertJsonPath('result.status', 'nothing_changed');
    $change($inSession, $picker->id)->assertUnprocessable()
        ->assertJsonValidationErrors(['state' => "This delivery note is in picking session $pickingSession->reference."]);

    $packing->update(['state' => DeliveryNoteStateEnum::PICKED]);
    $change($packing, $clerk->id)->assertUnprocessable()->assertJsonValidationErrors(['state']);

    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_VIEWER]);
    $change($inSession, $picker->id)->assertForbidden();
});

test('mobile does not change the picker of a note in a picking session', function () {
    $picker = goodsOutPickerUser($this);
    $clerk  = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    [$deliveryNote] = goodsOutPickingNote($this);
    $pickingSession = mobileExtrasSession($this, $deliveryNote, $clerk);

    expect($deliveryNote->refresh()->state)->toBe(DeliveryNoteStateEnum::QUEUED)
        ->and(goodsOutActionKeys($deliveryNote))->toBe(['start_picking', 'edit_delivery_address']);
    postGoodsOut('grp.api.mobile.delivery_notes.picker', $deliveryNote->id, ['user_id' => $picker->id])->assertUnprocessable()
        ->assertJsonValidationErrors(['state' => "This delivery note is in picking session $pickingSession->reference."]);
    postGoodsOut('grp.api.mobile.delivery_notes.picker', $deliveryNote->id, ['user_id' => null])->assertUnprocessable()->assertJsonValidationErrors(['state']);
    expect($deliveryNote->refresh()->picker_user_id)->toBe($clerk->id);
});

test('mobile fixes the delivery address until a shipment exists, and the contact details only for warehouse staff', function () {
    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    [$deliveryNote] = goodsOutPickingNote($this);
    $address        = mobileExtrasAddress();
    $save           = fn (array $body) => postGoodsOut('grp.api.mobile.delivery_notes.delivery_address', $deliveryNote->id, $body);

    getGoodsOut('grp.api.mobile.delivery_notes.show', $deliveryNote->id)->assertOk()->assertJsonPath('data.can_edit_contact', true);
    expect(goodsOutActionKeys($deliveryNote))->toContain('edit_delivery_address');

    $save(['address' => $address, 'contact_name' => 'Jane Doe', 'phone' => '+44 114 000 0000'])->assertOk()
        ->assertJsonPath('result', ['status' => 'done', 'message' => 'Delivery address saved.'])
        ->assertJsonPath('data.delivery_address.country_code', 'GB')
        ->assertJsonPath('data.delivery_address.fields.address_line_1', '12 High Street')
        ->assertJsonPath('data.delivery_address.fields.postal_code', 'S1 2AB')
        ->assertJsonPath('data.delivery_address.fields.contact_name', 'Jane Doe')
        ->assertJsonPath('data.delivery_address.fields.phone', '+44 114 000 0000');
    expect($deliveryNote->refresh()->deliveryAddress->locality)->toBe('Sheffield');

    $save(['address' => [...$address, 'locality' => null]])->assertUnprocessable()->assertJsonValidationErrors(['address']);
    $save(['address' => [...$address, 'country_code' => 'ZZ']])->assertUnprocessable()->assertJsonValidationErrors(['address']);
    $save(['address' => $address, 'email' => 'not an email'])->assertUnprocessable()->assertJsonValidationErrors(['email']);
    expect($deliveryNote->refresh()->email)->not->toBe('not an email');

    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_VIEWER], ["orders.{$this->shop->id}.edit"]);
    getGoodsOut('grp.api.mobile.delivery_notes.show', $deliveryNote->id)->assertOk()->assertJsonPath('data.can_edit_contact', false);
    expect(goodsOutActionKeys($deliveryNote))->toBe(['edit_delivery_address', 'cancel']);
    $save(['address' => $address, 'contact_name' => 'Someone Else'])->assertForbidden();
    $save(['address' => [...$address, 'address_line_1' => '1 New Road']])->assertOk()
        ->assertJsonPath('data.delivery_address.fields.address_line_1', '1 New Road')
        ->assertJsonPath('data.delivery_address.fields.contact_name', 'Jane Doe');

    $deliveryNote->update(['parcels' => [['weight' => 1, 'dimensions' => [10, 10, 10]]]]);
    $shipper = StoreShipper::make()->action($this->organisation, ['code' => 'MX'.Str::upper(Str::random(4)), 'name' => 'Manual', 'trade_as' => 'manual']);
    StoreShipment::make()->action($deliveryNote->refresh(), $shipper, ['tracking' => 'TRK'.Str::random(6)]);

    expect(goodsOutActionKeys($deliveryNote))->not->toContain('edit_delivery_address');
    $save(['address' => $address])->assertUnprocessable()->assertJsonValidationErrors(['address']);

    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_VIEWER]);
    $save(['address' => $address])->assertForbidden();
});

test('mobile asks CRM about a note or a picking session in a staff chat, polls it and keeps outsiders out', function () {
    Event::fake([StaffMessageSent::class, StaffConversationRead::class]);
    fakeActionJobs(TranslateStaffMessage::class);
    $clerk          = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    [$deliveryNote] = goodsOutPickingNote($this);

    $opened = postGoodsOut('grp.api.mobile.delivery_notes.chat', $deliveryNote->id)->assertOk()
        ->assertJsonPath('data.context_label', $deliveryNote->reference)
        ->assertJsonPath('data.unread_count', 0);
    $ulid = $opened->json('data.ulid');

    expect(array_keys($opened->json('data')))->toBe(['ulid', 'name', 'context_label', 'participants', 'unread_count'])
        ->and(collect($opened->json('data.participants'))->firstWhere('id', $clerk->id))->toBe(['id' => $clerk->id, 'name' => $clerk->chatName()])
        ->and(collect($opened->json('data.participants'))->every(fn (array $participant) => array_keys($participant) === ['id', 'name']))->toBeTrue()
        ->and(postGoodsOut('grp.api.mobile.delivery_notes.chat', $deliveryNote->id)->assertOk()->json('data.ulid'))->toBe($ulid);

    $conversation = StaffConversation::where('ulid', $ulid)->firstOrFail();
    $crm          = createMobileAppUser($this->group);
    $conversation->addParticipants([$crm->id]);
    $question = SendStaffMessage::make()->handle($conversation, $crm, ['body' => 'Which colour?']);

    postGoodsOut('grp.api.mobile.delivery_notes.chat', $deliveryNote->id)->assertOk()->assertJsonPath('data.unread_count', 1);

    $answer = postGoodsOut('grp.api.mobile.chat.messages.store', $ulid, ['body' => 'The blue one is out of stock'])->assertOk()
        ->assertJsonPath('data.is_mine', true)
        ->assertJsonPath('data.user', ['id' => $clerk->id, 'name' => $clerk->chatName()])
        ->assertJsonPath('data.body', 'The blue one is out of stock')
        ->assertJsonPath('data.image_url', null)
        ->json('data');
    expect(array_keys($answer))->toBe(['id', 'user', 'is_mine', 'body', 'image_url', 'created_at']);
    postGoodsOut('grp.api.mobile.chat.messages.store', $ulid, [])->assertUnprocessable()->assertJsonValidationErrors(['body']);

    $messages = getGoodsOut('grp.api.mobile.chat.messages.index', $ulid)->assertOk()->assertJsonPath('has_more', false);
    expect(collect($messages->json('data'))->pluck('id')->all())->toBe([$question->id, $answer['id']])
        ->and($messages->json('data.0.is_mine'))->toBeFalse()
        ->and($messages->json('data.0.user'))->toBe(['id' => $crm->id, 'name' => $crm->chatName()]);
    postGoodsOut('grp.api.mobile.delivery_notes.chat', $deliveryNote->id)->assertOk()->assertJsonPath('data.unread_count', 0);

    $later = SendStaffMessage::make()->handle($conversation, $crm, ['body' => 'Send the green one']);
    expect(collect(getGoodsOut('grp.api.mobile.chat.messages.index', $ulid, ['after_id' => $answer['id']])->assertOk()->json('data'))->pluck('id')->all())->toBe([$later->id])
        ->and(getGoodsOut('grp.api.mobile.chat.messages.index', $ulid, ['after_id' => $later->id])->assertOk()->json('data'))->toBe([])
        ->and(collect(getGoodsOut('grp.api.mobile.chat.messages.index', $ulid, ['before_id' => $later->id])->assertOk()->json('data'))->pluck('id')->all())->toBe([$question->id, $answer['id']]);

    foreach (range(1, 30) as $number) {
        SendStaffMessage::make()->handle($conversation, $crm, ['body' => "Line $number"]);
    }
    $page = getGoodsOut('grp.api.mobile.chat.messages.index', $ulid)->assertOk()->assertJsonPath('has_more', true)->assertJsonCount(30, 'data');
    expect($page->json('data.29.body'))->toBe('Line 30');

    $pickingSession = mobileExtrasSession($this, goodsOutPickingNote($this)[0], $clerk);
    postGoodsOut('grp.api.mobile.picking_sessions.chat', $pickingSession->id)->assertOk()
        ->assertJsonPath('data.context_label', $pickingSession->reference);

    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    getGoodsOut('grp.api.mobile.chat.messages.index', $ulid)->assertForbidden();
    postGoodsOut('grp.api.mobile.chat.messages.store', $ulid, ['body' => 'Hello'])->assertForbidden();

    actingAsGoodsOutUser($this->group, $this->warehouse, permissions: ["stocks.{$this->warehouse->id}.view"]);
    postGoodsOut('grp.api.mobile.delivery_notes.chat', $deliveryNote->id)->assertForbidden();
    postGoodsOut('grp.api.mobile.picking_sessions.chat', $pickingSession->id)->assertForbidden();
});

test('mobile lists who is picking and packing now, with their notes, trolleys and picking sessions', function () {
    $packer = goodsOutPickerUser($this);
    $clerk  = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    [$picking]   = goodsOutPickingNote($this, [2], $clerk);
    [$inSession] = goodsOutPickingNote($this);
    [$packing]   = goodsOutPickingNote($this);
    $packing->update(['state' => DeliveryNoteStateEnum::PACKING, 'packer_user_id' => $packer->id]);

    $pickingSession = mobileExtrasSession($this, $inSession, $clerk);
    StartPickPickingSession::make()->handle($pickingSession, []);
    $noteTrolley    = StoreTrolley::make()->handle($this->warehouse, ['name' => 'B'.Str::random(6)]);
    $sessionTrolley = StoreTrolley::make()->handle($this->warehouse, ['name' => 'A'.Str::random(6)]);
    $noteTrolley->update(['current_delivery_note_id' => $picking->id]);
    AttachTrolleyToPickingSession::make()->handle($sessionTrolley, $pickingSession);

    getGoodsOut('grp.api.mobile.warehouses.dispatch_personnel', $this->warehouse->id, ['role' => 'pickers'])->assertOk()
        ->assertJsonPath('data', [[
            'user'             => ['id' => $clerk->id, 'name' => $clerk->contact_name ?? $clerk->username],
            'delivery_notes'   => collect([$picking, $inSession])->sortBy('reference')->map(fn (DeliveryNote $deliveryNote) => ['id' => $deliveryNote->id, 'reference' => $deliveryNote->reference])->values()->all(),
            'trolleys'         => [['id' => $sessionTrolley->id, 'name' => $sessionTrolley->name], ['id' => $noteTrolley->id, 'name' => $noteTrolley->name]],
            'picking_sessions' => [['id' => $pickingSession->id, 'reference' => $pickingSession->reference]],
        ]]);
    getGoodsOut('grp.api.mobile.warehouses.dispatch_personnel', $this->warehouse->id, ['role' => 'packers'])->assertOk()
        ->assertJsonPath('data', [[
            'user'             => ['id' => $packer->id, 'name' => $packer->contact_name ?? $packer->username],
            'delivery_notes'   => [['id' => $packing->id, 'reference' => $packing->reference]],
            'trolleys'         => [],
            'picking_sessions' => [],
        ]]);
    getGoodsOut('grp.api.mobile.warehouses.dispatch_personnel', $this->warehouse->id)->assertUnprocessable()->assertJsonValidationErrors(['role']);

    actingAsGoodsOutUser($this->group, $this->warehouse, permissions: ["stocks.{$this->warehouse->id}.view"]);
    getGoodsOut('grp.api.mobile.warehouses.dispatch_personnel', $this->warehouse->id, ['role' => 'pickers'])->assertForbidden();
});
