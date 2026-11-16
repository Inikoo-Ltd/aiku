<?php

/*
 * Author Louis Perez
 * Created on 13-11-2026-15h-20m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Feature;

use App\Actions\Dispatching\DeliveryNote\UpdateState\StartPackingDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\UpdateState\UpdateDeliveryNoteStateToPicked;
use App\Actions\Dispatching\Picking\StorePicking;
use App\Actions\Dispatching\Shipment\StoreShipment;
use App\Actions\Dispatching\Shipper\StoreShipper;
use App\Actions\Dropshipping\CustomerSalesChannel\StoreCustomerSalesChannel;
use App\Actions\Inventory\Warehouse\StoreWarehouse;
use App\Enums\Catalogue\Leaflet\LeafletTypeEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNoteLeaflet\DeliveryNoteLeafletStateEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Models\Catalogue\PreferredShipping;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Dispatching\DeliveryNoteLeaflet;
use App\Models\Dispatching\Shipment;
use App\Models\Dispatching\Shipper;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

use function Pest\Laravel\getJson;

require_once __DIR__.'/../Support/MobileApiHelpers.php';
require_once __DIR__.'/../Support/MobileGoodsOutHelpers.php';
require_once __DIR__.'/../Support/DispatchingHelpers.php';
require_once __DIR__.'/../Support/InventoryHelpers.php';

/**
 * @param array<int, float> $quantities
 * @return array{0: DeliveryNote, 1: array<int, DeliveryNoteItem>}
 */
function mobilePickedNote(object $ctx, User $picker, array $quantities = [2]): array
{
    [$deliveryNote, $lines] = goodsOutPickingNote($ctx, $quantities, $picker);

    foreach ($lines as $line) {
        StorePicking::make()->action($line, $picker, [
            'picker_user_id'        => $picker->id,
            'location_org_stock_id' => goodsOutLineLocation($line)->id,
            'quantity'              => (float) $line->quantity_required,
        ]);
    }

    UpdateDeliveryNoteStateToPicked::run($deliveryNote->refresh());

    return [$deliveryNote->refresh(), array_map(fn (DeliveryNoteItem $line) => $line->refresh(), $lines)];
}

/**
 * @param array<int, float> $quantities
 * @return array{0: DeliveryNote, 1: array<int, DeliveryNoteItem>}
 */
function mobilePackingNote(object $ctx, User $packer, array $quantities = [2]): array
{
    [$deliveryNote, $lines] = mobilePickedNote($ctx, $packer, $quantities);
    StartPackingDeliveryNote::make()->action($deliveryNote, $packer);

    return [$deliveryNote->refresh(), array_map(fn (DeliveryNoteItem $line) => $line->refresh(), $lines)];
}

function mobileManualShipper(object $ctx, bool $supervisorOnly = false): Shipper
{
    $shipper = StoreShipper::make()->action($ctx->organisation, [
        'code'     => 'MP'.Str::upper(Str::random(6)),
        'name'     => 'Manual '.Str::random(6),
        'trade_as' => 'manual',
    ]);

    if ($supervisorOnly) {
        $shipper->update(['settings' => ['supervisor_only' => true]]);
    }

    return $shipper->refresh();
}

function mobileRequireBoxPackingList(Organisation $organisation, DeliveryNote $deliveryNote): void
{
    $settings = $organisation->settings;
    data_set($settings, 'dispatching.box_packing_list', true);
    data_set($settings, 'dispatching.box_packing_list_destinations', [[
        'country_id' => $deliveryNote->deliveryAddress->country_id,
        'postcode'   => $deliveryNote->deliveryAddress->postal_code,
    ]]);
    $organisation->update(['settings' => $settings]);
}

/**
 * @return array<string, mixed>
 */
function mobileApcResponse(bool $succeeds): array
{
    return $succeeds
        ? ['Orders' => [
            'Messages' => ['Code' => 'SUCCESS'],
            'Order'    => [
                'OrderNumber'     => 'APC123',
                'Label'           => ['Content' => base64_encode('label')],
                'ShipmentDetails' => ['NumberOfPieces' => 1, 'Items' => ['Item' => ['TrackingNumber' => 'TRKAPC']]],
            ],
        ]]
        : ['Orders' => ['Messages' => ['Code' => 'FAIL'], 'Order' => ['Messages' => ['ErrorFields' => ['ErrorField' => [['FieldName' => 'Delivery PostalCode', 'ErrorMessage' => 'bad']]]]]]];
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
});

afterEach(function () {
    $this->organisation->update(['settings' => $this->originalSettings]);
});

test('mobile delivery note offers the packing and dispatch actions of the web, by state and by user', function () {
    $clerk = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);

    [$picked] = mobilePickedNote($this, $clerk);
    expect(goodsOutActionKeys($picked))->toBe(['start_packing', 'undo_set_as_picked', 'change_trolley', 'change_picked_bay', 'edit_delivery_address']);

    [$nothingPicked] = goodsOutPickingNote($this, [2], $clerk);
    $nothingPicked->update(['state' => DeliveryNoteStateEnum::PICKED]);
    getGoodsOut('grp.api.mobile.delivery_notes.show', $nothingPicked->id)->assertOk()
        ->assertJsonPath('data.available_actions.0', ['key' => 'start_packing', 'label' => 'Start packing', 'disabled_reason' => DeliveryNote::nothingPickedMessage()]);

    [$packing] = mobilePackingNote($this, $clerk);
    getGoodsOut('grp.api.mobile.delivery_notes.show', $packing->id)->assertOk()
        ->assertJsonPath('data.can_handle', true)
        ->assertJsonPath('data.available_actions', [
            ['key' => 'set_as_packed', 'label' => 'Set as packed'],
            ['key' => 'undo_packing', 'label' => 'Undo packing'],
            ['key' => 'change_packer', 'label' => 'Change packer'],
            ['key' => 'edit_delivery_address', 'label' => 'Edit delivery address'],
        ]);

    [$packed]     = packedDeliveryNote($this, true);
    [$collection] = packedDeliveryNote($this, true);
    $collection->update(['collection_address_id' => $collection->address_id]);
    [$finalised] = finalisedDeliveryNote($this);

    expect(goodsOutActionKeys($packed))->toBe(['finalise_and_dispatch', 'add_shipment', 'unpack', 'change_packer', 'edit_delivery_address'])
        ->and(goodsOutActionKeys($collection))->toBe(['finalise', 'unpack', 'change_packer', 'edit_delivery_address']);
    getGoodsOut('grp.api.mobile.delivery_notes.show', $finalised->id)->assertOk()
        ->assertJsonPath('data.available_actions', [
            ['key' => 'dispatch', 'label' => 'Dispatch'],
            ['key' => 'add_shipment', 'label' => 'Add shipment'],
            ['key' => 'unpack', 'label' => 'Unpack'],
        ]);

    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    getGoodsOut('grp.api.mobile.delivery_notes.show', $packing->id)->assertOk()
        ->assertJsonPath('data.can_handle', false)
        ->assertJsonPath('data.available_actions', [
            ['key' => 'take_over', 'label' => 'Unlock to pack'],
            ['key' => 'undo_packing', 'label' => 'Undo packing'],
            ['key' => 'change_packer', 'label' => 'Change packer'],
            ['key' => 'edit_delivery_address', 'label' => 'Edit delivery address'],
        ]);

    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_VIEWER]);
    foreach ([$picked, $packing, $packed, $finalised] as $deliveryNote) {
        expect(goodsOutActionKeys($deliveryNote))->toBe([]);
    }
});

test('mobile delivery note page sends the packing, shipping and document fields', function () {
    setGoodsOutPickingSettings($this->organisation, ['allow_scan_to_pack' => true]);
    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    [$packed] = packedDeliveryNote($this, true);

    $response = getGoodsOut('grp.api.mobile.delivery_notes.show', $packed->id)->assertOk()
        ->assertJsonPath('data.settings.allow_scan_to_pack', true)
        ->assertJsonPath('data.parcels', [['weight_kg' => 1, 'dimensions_cm' => [30, 20, 10], 'boxes' => null]])
        ->assertJsonPath('data.parcels_editable', true)
        ->assertJsonPath('data.consumables', [])
        ->assertJsonPath('data.packaging', null)
        ->assertJsonPath('data.inserts', null)
        ->assertJsonPath('data.box_packing_list', null)
        ->assertJsonPath('data.shipping.can_add', true)
        ->assertJsonPath('data.shipping.can_print_labels', false)
        ->assertJsonPath('data.shipping.shipments_locked', false)
        ->assertJsonPath('data.shipping.external', null)
        ->assertJsonPath('data.shipments', [])
        ->assertJsonPath('data.documents', ['delivery_note_pdf_url' => route('grp.api.mobile.delivery_notes.pdf', $packed->id), 'gift_message_pdf_url' => null]);

    expect(collect($response->json('data.lines'))->firstWhere('quantity_picked', 10))
        ->toMatchArray(['is_done_packing' => true, 'is_packed' => true, 'quantity_to_pack_label' => '0', 'boxes' => []]);

    expect(array_keys($response->json('data.shipping')))->toBe([
        'can_add', 'can_print_labels', 'locked_shipper_id', 'preferred_shipper_id', 'locked_by', 'locked_scope', 'preferred_shipper',
        'unconfirmed_shipping_order', 'shipments_locked', 'tracking_channel', 'can_override_lock', 'external',
    ])
        ->and(array_keys($response->json('data.delivery_address.fields')))->toBe([
            'contact_name', 'company_name', 'email', 'phone', 'address_line_1', 'address_line_2', 'sorting_code', 'postal_code',
            'locality', 'dependent_locality', 'administrative_area', 'country_code',
        ]);
});

test('mobile start packing makes the user the packer and is refused outside picked or with nothing picked', function () {
    $clerk = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    [$picked] = mobilePickedNote($this, $clerk);

    postGoodsOut('grp.api.mobile.delivery_notes.start_packing', $picked->id)->assertOk()
        ->assertJsonPath('result', ['status' => 'done', 'message' => 'Packing started.'])
        ->assertJsonPath('data.state', 'packing')
        ->assertJsonPath('data.packer', ['id' => $clerk->id, 'name' => $clerk->contact_name ?? $clerk->username])
        ->assertJsonPath('data.can_handle', true);

    postGoodsOut('grp.api.mobile.delivery_notes.start_packing', $picked->id)->assertUnprocessable()->assertJsonValidationErrors(['state']);

    [$nothingPicked] = goodsOutPickingNote($this, [2], $clerk);
    $nothingPicked->update(['state' => DeliveryNoteStateEnum::PICKED]);
    postGoodsOut('grp.api.mobile.delivery_notes.start_packing', $nothingPicked->id)->assertUnprocessable()
        ->assertJsonValidationErrors(['state' => DeliveryNote::nothingPickedMessage()]);
    expect($nothingPicked->refresh()->state)->toBe(DeliveryNoteStateEnum::PICKED);
});

test('mobile packs a line in several goes, refuses more than was picked, unpacks it, and only for the handler', function () {
    $clerk = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    [$deliveryNote, [$line]] = mobilePackingNote($this, $clerk, [3, 1]);
    $code   = $line->orgStock->code;
    $pack   = fn (array $body = []) => postGoodsOut('grp.api.mobile.delivery_notes.lines.pack', [$deliveryNote->id, $line->id], $body);
    $lineOf = fn ($response) => collect($response->json('data.lines'))->firstWhere('id', $line->id);

    $first = $pack(['quantity' => 1])->assertOk()
        ->assertJsonPath('result', ['status' => 'done', 'message' => "Packed 1 x $code, 2 still to pack", 'warning' => null]);
    expect($lineOf($first))->toMatchArray(['quantity_packed' => 1, 'quantity_to_pack' => 2, 'quantity_to_pack_label' => '2', 'is_done_packing' => false, 'is_packed' => false]);

    $pack(['quantity' => 5])->assertUnprocessable()->assertJsonValidationErrors(['quantity']);
    $pack(['quantity' => 0])->assertUnprocessable()->assertJsonValidationErrors(['quantity']);

    $rest = $pack()->assertOk()
        ->assertJsonPath('result.message', "Packed 2 x $code")
        ->assertJsonPath('data.state', 'packing');
    expect($lineOf($rest))->toMatchArray(['quantity_packed' => 3, 'quantity_to_pack' => 0, 'is_done_packing' => true, 'is_packed' => true]);

    $unpacked = postGoodsOut('grp.api.mobile.delivery_notes.lines.unpack', [$deliveryNote->id, $line->id])->assertOk()
        ->assertJsonPath('result', ['status' => 'done', 'message' => 'Line unpacked.']);
    expect($lineOf($unpacked))->toMatchArray(['quantity_packed' => 0, 'quantity_to_pack' => 3]);

    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    $pack(['quantity' => 1])->assertForbidden();
    postGoodsOut('grp.api.mobile.delivery_notes.lines.unpack', [$deliveryNote->id, $line->id])->assertForbidden();
    expect((float) $line->refresh()->packings()->sum('quantity'))->toBe(0.0);
});

test('mobile scan to pack packs through the web scan and is behind its setting', function () {
    setGoodsOutPickingSettings($this->organisation, ['allow_scan_to_pack' => true]);
    $clerk = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    [$deliveryNote, [$line]] = mobilePackingNote($this, $clerk, [2, 1]);
    $scan = fn (array $body) => postGoodsOut('grp.api.mobile.delivery_notes.scan_pack', $deliveryNote->id, $body);

    $packed = $scan(['barcode' => $line->orgStock->barcode, 'quantity' => 1])->assertOk()
        ->assertJsonPath('result.status', 'packed')
        ->assertJsonPath('result.message', "Packed 1 x {$line->orgStock->code}, 1 still to pack")
        ->assertJsonPath('result.item.id', $line->id);
    expect(collect($packed->json('data.lines'))->firstWhere('id', $line->id)['quantity_packed'])->toEqual(1);
    $scan(['barcode' => 'NOTHERE'.Str::random(6)])->assertOk()
        ->assertJsonPath('result.status', 'not_found')
        ->assertJsonPath('result.item', null);
    $scan([])->assertUnprocessable()->assertJsonValidationErrors(['barcode']);

    setGoodsOutPickingSettings($this->organisation, ['allow_scan_to_pack' => false]);
    $scan(['barcode' => $line->orgStock->barcode])->assertForbidden();
});

test('mobile keeps the boxes of a line only where a packing list by box is required', function () {
    $clerk = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    [$deliveryNote, [$line]] = mobilePackingNote($this, $clerk, [3]);
    $boxes = fn (array $body) => postGoodsOut('grp.api.mobile.delivery_notes.lines.boxes', [$deliveryNote->id, $line->id], $body);

    $boxes(['boxes' => [['box' => 1, 'quantity' => 3]]])->assertUnprocessable()->assertJsonValidationErrors(['boxes']);
    getGoodsOut('grp.api.mobile.delivery_notes.packing_list_pdf', $deliveryNote->id)->assertNotFound();

    mobileRequireBoxPackingList($this->organisation, $deliveryNote);

    $response = $boxes(['boxes' => [['box' => 2, 'quantity' => 1], ['box' => 1, 'quantity' => 2]]])->assertOk()
        ->assertJsonPath('result.status', 'done')
        ->assertJsonPath('data.box_packing_list.required', true)
        ->assertJsonPath('data.box_packing_list.number_boxes', 2)
        ->assertJsonPath('data.box_packing_list.pdf_url', route('grp.api.mobile.delivery_notes.packing_list_pdf', $deliveryNote->id));
    expect(collect($response->json('data.lines'))->firstWhere('id', $line->id)['boxes'])->toEqual([['box' => 1, 'quantity' => 2], ['box' => 2, 'quantity' => 1]])
        ->and($response->json('data.box_packing_list.missing_message'))->toBeString();

    $boxes(['boxes' => [['box' => 1, 'quantity' => 5]]])->assertUnprocessable()->assertJsonValidationErrors(['boxes']);
    $boxes(['boxes' => [['box' => 0, 'quantity' => 1]]])->assertUnprocessable()->assertJsonValidationErrors(['boxes.0.box']);

    getGoodsOut('grp.api.mobile.delivery_notes.packing_list_pdf', $deliveryNote->id)->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

test('mobile parcels are validated, keep their boxes and are locked once a shipment exists', function () {
    $clerk = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    [$deliveryNote] = mobilePackingNote($this, $clerk);
    $parcels = fn (array $body) => postGoodsOut('grp.api.mobile.delivery_notes.parcels', $deliveryNote->id, $body);
    $valid   = ['parcels' => [
        ['weight_kg' => 1.5, 'dimensions_cm' => [30, 20, 10], 'boxes' => null],
        ['weight_kg' => 20, 'dimensions_cm' => [120, 100, 80], 'boxes' => 4],
    ]];

    $parcels($valid)->assertOk()
        ->assertJsonPath('result', ['status' => 'done', 'message' => 'Parcels saved.'])
        ->assertJsonPath('data.parcels', [
            ['weight_kg' => 1.5, 'dimensions_cm' => [30, 20, 10], 'boxes' => null],
            ['weight_kg' => 20, 'dimensions_cm' => [120, 100, 80], 'boxes' => 4],
        ])
        ->assertJsonPath('data.parcels_editable', true);
    expect($deliveryNote->refresh()->parcels[1])->toEqual(['weight' => 20, 'dimensions' => [120, 100, 80], 'boxes' => 4])
        ->and($deliveryNote->parcels[0])->not->toHaveKey('boxes');

    $parcels(['parcels' => [['weight_kg' => 1, 'dimensions_cm' => [30, 20]]]])->assertUnprocessable()->assertJsonValidationErrors(['parcels.0.dimensions_cm']);
    $parcels(['parcels' => [['weight_kg' => 1, 'dimensions_cm' => [30, 0, 10]]]])->assertUnprocessable()->assertJsonValidationErrors(['parcels.0.dimensions_cm.1']);
    $parcels(['parcels' => [['weight_kg' => 0, 'dimensions_cm' => [30, 20, 10]]]])->assertUnprocessable()->assertJsonValidationErrors(['parcels.0.weight_kg']);
    $parcels(['parcels' => [['weight_kg' => 1, 'dimensions_cm' => [30, 20, 10], 'boxes' => 0]]])->assertUnprocessable()->assertJsonValidationErrors(['parcels.0.boxes']);
    $parcels(['parcels' => []])->assertUnprocessable()->assertJsonValidationErrors(['parcels']);

    StoreShipment::make()->action($deliveryNote->refresh(), mobileManualShipper($this), ['tracking' => 'TRK'.Str::random(6)]);

    getGoodsOut('grp.api.mobile.delivery_notes.show', $deliveryNote->id)->assertOk()->assertJsonPath('data.parcels_editable', false);
    $parcels($valid)->assertUnprocessable()
        ->assertJsonValidationErrors(['parcels' => 'The parcels can not change once a shipment exists. Delete the shipment first.']);
});

test('mobile set as packed from packing needs parcel dimensions, makes the user the packer and is only for the handler', function () {
    $clerk = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    [$deliveryNote] = mobilePackingNote($this, $clerk, [2, 1]);
    [$someoneElses] = mobilePackingNote($this, createMobileAppUser($this->group));

    postGoodsOut('grp.api.mobile.delivery_notes.set_as_packed', $someoneElses->id)->assertForbidden();
    postGoodsOut('grp.api.mobile.delivery_notes.set_as_packed', $deliveryNote->id)->assertUnprocessable()->assertJsonValidationErrors(['parcels']);

    postGoodsOut('grp.api.mobile.delivery_notes.parcels', $deliveryNote->id, ['parcels' => [['weight_kg' => 2, 'dimensions_cm' => [30, 20, 10]]]])->assertOk();

    $response = postGoodsOut('grp.api.mobile.delivery_notes.set_as_packed', $deliveryNote->id)->assertOk()
        ->assertJsonPath('result', ['status' => 'done', 'message' => 'Delivery note packed.'])
        ->assertJsonPath('data.state', 'packed')
        ->assertJsonPath('data.packer.id', $clerk->id);
    expect(collect($response->json('data.lines'))->pluck('is_done_packing')->all())->toBe([true, true]);
});

test('mobile packing the last line sets the note as packed, or says why it stayed in packing', function () {
    $clerk = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);

    [$withParcels, [$line]] = mobilePackingNote($this, $clerk);
    giveParcelDimensions($withParcels);
    postGoodsOut('grp.api.mobile.delivery_notes.lines.pack', [$withParcels->id, $line->id])->assertOk()
        ->assertJsonPath('result.status', 'done')
        ->assertJsonPath('result.warning', null)
        ->assertJsonPath('data.state', 'packed');

    [$withoutParcels, [$otherLine]] = mobilePackingNote($this, $clerk);
    postGoodsOut('grp.api.mobile.delivery_notes.lines.pack', [$withoutParcels->id, $otherLine->id])->assertOk()
        ->assertJsonPath('result.status', 'done')
        ->assertJsonPath('result.warning', 'Enter the dimensions of every parcel before setting as packed')
        ->assertJsonPath('data.state', 'packing');
    expect((float) $otherLine->refresh()->packings()->sum('quantity'))->toBe(2.0);

    [$finalised, $item] = finalisedDeliveryNote($this);
    postGoodsOut('grp.api.mobile.delivery_notes.unpack', $finalised->id)->assertOk()->assertJsonPath('data.state', 'packing');
    $item->refresh()->update(['quantity_picked' => 5]);
    postGoodsOut('grp.api.mobile.delivery_notes.lines.pack', [$finalised->id, $item->id])->assertForbidden();
    postGoodsOut('grp.api.mobile.delivery_notes.take_over', $finalised->id)->assertOk()->assertJsonPath('data.can_handle', true);

    postGoodsOut('grp.api.mobile.delivery_notes.lines.pack', [$finalised->id, $item->id])->assertOk()
        ->assertJsonPath('result.status', 'done')
        ->assertJsonPath('result.warning', fn (?string $warning) => str_contains((string) $warning, 'repacking-a-finalised-delivery-note'))
        ->assertJsonPath('data.state', 'packing');
    expect((float) $item->refresh()->packings()->sum('quantity'))->toBe(5.0);
});

test('mobile undo packing goes back to picked keeping the packer, and unpack reopens a packed note', function () {
    $clerk = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    [$packing] = mobilePackingNote($this, $clerk);

    postGoodsOut('grp.api.mobile.delivery_notes.undo_packing', $packing->id)->assertOk()
        ->assertJsonPath('result.status', 'done')
        ->assertJsonPath('data.state', 'picked')
        ->assertJsonPath('data.packer.id', $clerk->id);
    postGoodsOut('grp.api.mobile.delivery_notes.undo_packing', $packing->id)->assertUnprocessable()->assertJsonValidationErrors(['state']);

    [$packed] = packedDeliveryNote($this, true);
    postGoodsOut('grp.api.mobile.delivery_notes.unpack', $packed->id)->assertOk()
        ->assertJsonPath('data.state', 'packing')
        ->assertJsonPath('data.packer', null)
        ->assertJsonPath('data.lines.0.quantity_packed', 0);
    postGoodsOut('grp.api.mobile.delivery_notes.unpack', $packed->id)->assertUnprocessable()->assertJsonValidationErrors(['state']);
});

test('mobile shippers list the active shippers of the organisation and hide supervisor-only ones from a clerk', function () {
    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    [$packed] = packedDeliveryNote($this, true);
    $manual   = mobileManualShipper($this);
    $backup   = mobileManualShipper($this, supervisorOnly: true);
    $inactive = mobileManualShipper($this);
    $inactive->update(['status' => false]);
    $api      = apiShipper($this, 'apc-gb');
    $shippers = fn () => getGoodsOut('grp.api.mobile.delivery_notes.shippers', $packed->id)->assertOk();

    $response = $shippers()->assertJsonPath('directive.can_override_lock', false);
    $listed   = collect($response->json('data'))->keyBy('id');
    expect($listed->keys()->all())->toContain($manual->id, $api->id)->not->toContain($backup->id)->not->toContain($inactive->id)
        ->and($listed[$api->id])->toBe(['id' => $api->id, 'code' => $api->code, 'name' => $api->name, 'trade_as' => 'apc-gb', 'api_shipper' => 'apc-gb', 'type' => 'api', 'needs_tracking' => false, 'is_locked_out' => false])
        ->and($listed[$manual->id]['type'])->toBe('manual')
        ->and(array_keys($response->json('directive')))->toBe(['locked_shipper_id', 'preferred_shipper_id', 'locked_by', 'can_override_lock', 'tracking_channel']);

    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_SUPERVISOR]);
    expect(collect($shippers()->assertJsonPath('directive.can_override_lock', true)->json('data'))->pluck('id')->all())->toContain($backup->id);

    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_VIEWER]);
    getGoodsOut('grp.api.mobile.delivery_notes.shippers', $packed->id)->assertForbidden();
});

test('mobile adds a manual shipment, asks eBay orders for the tracking, and deletes a shipment', function () {
    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    [$packed] = packedDeliveryNote($this, true);
    $manual   = mobileManualShipper($this);
    $backup   = mobileManualShipper($this, supervisorOnly: true);

    postGoodsOut('grp.api.mobile.delivery_notes.shipments.store', $packed->id, ['shipper_id' => 999999999])->assertUnprocessable()->assertJsonValidationErrors(['shipper_id']);
    postGoodsOut('grp.api.mobile.delivery_notes.shipments.store', $packed->id, ['shipper_id' => $backup->id, 'tracking' => 'BK1'])->assertUnprocessable()->assertJsonValidationErrors(['shipper']);

    $response = postGoodsOut('grp.api.mobile.delivery_notes.shipments.store', $packed->id, ['shipper_id' => $manual->id, 'tracking' => 'TRK123'])->assertOk()
        ->assertJsonPath('result', ['status' => 'done', 'message' => 'Shipment added.'])
        ->assertJsonPath('data.shipments.0.shipper', $manual->name)
        ->assertJsonPath('data.shipments.0.shipper_code', $manual->code)
        ->assertJsonPath('data.shipments.0.tracking', 'TRK123')
        ->assertJsonPath('data.shipments.0.trackings', ['TRK123'])
        ->assertJsonPath('data.shipments.0.label', ['kind' => 'none', 'url' => null])
        ->assertJsonPath('data.shipments.0.can_delete', true)
        ->assertJsonPath('data.parcels_editable', false);
    $shipmentId = $response->json('data.shipments.0.id');

    getGoodsOut('grp.api.mobile.delivery_notes.shipments.label', [$packed->id, $shipmentId])->assertNotFound();

    deleteGoodsOut('grp.api.mobile.delivery_notes.shipments.delete', [$packed->id, $shipmentId])->assertOk()
        ->assertJsonPath('result.status', 'done')
        ->assertJsonPath('data.shipments', []);
    expect(Shipment::find($shipmentId))->toBeNull();

    [$ebayNote] = packedDeliveryNote($this, true);
    $platform   = $this->group->platforms()->where('type', PlatformTypeEnum::EBAY)->firstOrFail();
    $channel    = StoreCustomerSalesChannel::make()->action($this->customer, $platform, ['reference' => 'mobile-packing-'.Str::random(6)]);
    $ebayNote->orders()->first()->updateQuietly(['customer_sales_channel_id' => $channel->id]);

    getGoodsOut('grp.api.mobile.delivery_notes.show', $ebayNote->id)->assertOk()->assertJsonPath('data.shipping.tracking_channel', 'eBay');
    expect(collect(getGoodsOut('grp.api.mobile.delivery_notes.shippers', $ebayNote->id)->json('data'))->firstWhere('id', $manual->id)['needs_tracking'])->toBeTrue();
    postGoodsOut('grp.api.mobile.delivery_notes.shipments.store', $ebayNote->id, ['shipper_id' => $manual->id, 'tracking' => null])->assertUnprocessable()
        ->assertJsonValidationErrors(['tracking'])
        ->assertJsonMissingValidationErrors(['tracking_number']);
    expect($ebayNote->shipments()->count())->toBe(0);
});

test('mobile passes a carrier address error through and retries the label with the fixed address', function () {
    Config::set('app.sandbox.shipper_apc_token', 'test-token');
    $succeeds = false;
    Http::fake(['*' => function () use (&$succeeds) {
        return Http::response(mobileApcResponse($succeeds));
    }]);
    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    [$packed] = packedDeliveryNote($this, true);
    $apc      = apiShipper($this, 'apc-gb');

    postGoodsOut('grp.api.mobile.delivery_notes.shipments.store', $packed->id, ['shipper_id' => $apc->id])->assertUnprocessable()
        ->assertJsonValidationErrors(['address', 'message']);
    expect($packed->shipments()->count())->toBe(0);

    postGoodsOut('grp.api.mobile.delivery_notes.shipments.retry_with_address', $packed->id, ['shipper_id' => $apc->id, 'address' => ['country_code' => 'ZZ']])->assertUnprocessable()
        ->assertJsonValidationErrors(['address']);

    $succeeds = true;
    $response = postGoodsOut('grp.api.mobile.delivery_notes.shipments.retry_with_address', $packed->id, [
        'shipper_id'   => $apc->id,
        'contact_name' => 'Jane Packer',
        'phone'        => '+441142000000',
        'address'      => ['address_line_1' => '1 High Street', 'locality' => 'Sheffield', 'postal_code' => 'S1 2AB', 'country_code' => 'GB'],
    ])->assertOk()
        ->assertJsonPath('result', ['status' => 'done', 'message' => 'Shipment added.'])
        ->assertJsonPath('data.delivery_address.fields.contact_name', 'Jane Packer')
        ->assertJsonPath('data.delivery_address.fields.postal_code', 'S1 2AB')
        ->assertJsonPath('data.delivery_address.country_code', 'GB')
        ->assertJsonPath('data.shipments.0.tracking', 'TRKAPC')
        ->assertJsonPath('data.shipments.0.is_printable', true)
        ->assertJsonPath('data.shipments.0.label.kind', 'download');

    $label = getJson($response->json('data.shipments.0.label.url'), ['X-Aiku-App-Key' => 'mobile-test-key'])->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename="label-'.$response->json('data.shipments.0.id').'.pdf"');
    expect($label->getContent())->toBe('label');
});

test('mobile asks a supervisor to confirm a shipper other than the one a shipping rule locks the note to', function () {
    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_SUPERVISOR]);
    [$packed] = packedDeliveryNote($this, true);
    $locked   = mobileManualShipper($this);
    $other    = mobileManualShipper($this);
    $rule     = PreferredShipping::create([
        'group_id'        => $packed->group_id,
        'organisation_id' => $packed->organisation_id,
        'shop_id'         => $packed->shop_id,
        'shipper_id'      => $locked->id,
        'country_id'      => $packed->deliveryAddress->country_id,
        'postcode'        => $packed->deliveryAddress->postal_code,
        'trade_scope'     => StoreShipment::make()->tradeScopeForShopType($packed->shop->type),
        'important'       => true,
    ]);
    $store    = fn (array $body) => postGoodsOut('grp.api.mobile.delivery_notes.shipments.store', $packed->id, ['shipper_id' => $other->id, 'tracking' => 'OVR1', ...$body]);
    $lockedOut = fn () => collect(getGoodsOut('grp.api.mobile.delivery_notes.shippers', $packed->id)->assertOk()->json('data'))->firstWhere('id', $other->id)['is_locked_out'];

    try {
        getGoodsOut('grp.api.mobile.delivery_notes.show', $packed->id)->assertOk()
            ->assertJsonPath('data.shipping.locked_shipper_id', $locked->id)
            ->assertJsonPath('data.shipping.locked_by', 'preferred_shipping')
            ->assertJsonPath('data.shipping.can_override_lock', true);
        expect($lockedOut())->toBeFalse();

        $store([])->assertUnprocessable()
            ->assertJsonValidationErrors(['override_lock' => "This delivery note is locked to $locked->name. Confirm to use another shipper."]);

        actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
        expect($lockedOut())->toBeTrue();
        $store(['override_lock' => true])->assertUnprocessable()->assertJsonValidationErrors(['shipper']);

        actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_SUPERVISOR]);
        $store(['override_lock' => true])->assertOk()->assertJsonPath('data.shipments.0.shipper', $other->name);
    } finally {
        $rule->delete();
    }
});

test('mobile finalises and dispatches a packed note once it has a shipment, and then locks its shipments', function () {
    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    [$packed] = packedDeliveryNote($this, true);

    postGoodsOut('grp.api.mobile.delivery_notes.finalise_and_dispatch', $packed->id)->assertUnprocessable()->assertJsonValidationErrors(['shipment']);
    expect($packed->refresh()->state)->toBe(DeliveryNoteStateEnum::PACKED);

    $shipmentId = postGoodsOut('grp.api.mobile.delivery_notes.shipments.store', $packed->id, ['shipper_id' => mobileManualShipper($this)->id, 'tracking' => 'FD1'])->assertOk()
        ->json('data.shipments.0.id');

    postGoodsOut('grp.api.mobile.delivery_notes.finalise', $packed->id)->assertUnprocessable()->assertJsonValidationErrors(['state']);
    postGoodsOut('grp.api.mobile.delivery_notes.finalise_and_dispatch', $packed->id)->assertOk()
        ->assertJsonPath('result', ['status' => 'done', 'message' => 'Delivery note dispatched.'])
        ->assertJsonPath('data.state', 'dispatched')
        ->assertJsonPath('data.available_actions', [])
        ->assertJsonPath('data.shipping.shipments_locked', true)
        ->assertJsonPath('data.shipments.0.can_delete', false);

    deleteGoodsOut('grp.api.mobile.delivery_notes.shipments.delete', [$packed->id, $shipmentId])->assertUnprocessable()->assertJsonValidationErrors(['shipment']);
    expect(Shipment::find($shipmentId))->not->toBeNull();

    [$finalised] = finalisedDeliveryNote($this);
    postGoodsOut('grp.api.mobile.delivery_notes.dispatch', $finalised->id)->assertOk()
        ->assertJsonPath('result.message', 'Delivery note dispatched.')
        ->assertJsonPath('data.state', 'dispatched');
});

test('mobile finalises a collection note and then sets it as collected', function () {
    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    [$collection] = packedDeliveryNote($this, true);
    $collection->update(['collection_address_id' => $collection->address_id]);

    getGoodsOut('grp.api.mobile.delivery_notes.show', $collection->id)->assertOk()
        ->assertJsonPath('data.is_collection', true)
        ->assertJsonPath('data.shipping.can_add', false)
        ->assertJsonPath('data.available_actions', [
            ['key' => 'finalise', 'label' => 'Finalise for collection'],
            ['key' => 'unpack', 'label' => 'Unpack'],
            ['key' => 'change_packer', 'label' => 'Change packer'],
            ['key' => 'edit_delivery_address', 'label' => 'Edit delivery address'],
        ]);
    postGoodsOut('grp.api.mobile.delivery_notes.dispatch', $collection->id)->assertUnprocessable()->assertJsonValidationErrors(['state']);
    postGoodsOut('grp.api.mobile.delivery_notes.shipments.store', $collection->id, ['shipper_id' => mobileManualShipper($this)->id, 'tracking' => 'C1'])
        ->assertUnprocessable()->assertJsonValidationErrors(['state']);

    postGoodsOut('grp.api.mobile.delivery_notes.finalise', $collection->id)->assertOk()
        ->assertJsonPath('result', ['status' => 'done', 'message' => 'Delivery note finalised.'])
        ->assertJsonPath('data.state', 'finalised')
        ->assertJsonPath('data.available_actions', [
            ['key' => 'dispatch', 'label' => 'Set as collected'],
            ['key' => 'unpack', 'label' => 'Unpack'],
            ['key' => 'edit_delivery_address', 'label' => 'Edit delivery address'],
        ]);

    postGoodsOut('grp.api.mobile.delivery_notes.dispatch', $collection->id)->assertOk()
        ->assertJsonPath('result.message', 'Set as collected.')
        ->assertJsonPath('data.state', 'dispatched');
});

test('mobile serves the delivery note and gift message PDFs to whoever can view the note', function () {
    $otherWarehouse = StoreWarehouse::make()->action($this->organisation, Warehouse::factory()->definition());
    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_VIEWER]);
    $here      = goodsOutDeliveryNote($this->order, $this->warehouse);
    $elsewhere = goodsOutDeliveryNote($this->order, $otherWarehouse);

    $pdf = getGoodsOut('grp.api.mobile.delivery_notes.pdf', $here->id)->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($pdf->headers->get('Content-Disposition'))->toStartWith('inline; filename=')
        ->and($pdf->getContent())->toStartWith('%PDF');
    getGoodsOut('grp.api.mobile.delivery_notes.pdf', $elsewhere->id)->assertNotFound();
    getGoodsOut('grp.api.mobile.delivery_notes.gift_message_pdf', $here->id)->assertNotFound();

    $here->update(['has_gift_message' => true]);
    $this->order->update(['gift_message' => 'Happy birthday']);
    getGoodsOut('grp.api.mobile.delivery_notes.show', $here->id)->assertOk()
        ->assertJsonPath('data.documents.gift_message_pdf_url', route('grp.api.mobile.delivery_notes.gift_message_pdf', $here->id));
    $giftMessage = getGoodsOut('grp.api.mobile.delivery_notes.gift_message_pdf', $here->id)->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($giftMessage->headers->get('Content-Disposition'))->toStartWith('inline; filename=');

    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::WAREHOUSE_VIEWER]);
    getGoodsOut('grp.api.mobile.delivery_notes.pdf', $here->id)->assertForbidden();
});

test('mobile opens an insert as an A6 PDF and counts it as printed', function () {
    $clerk          = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    [$deliveryNote] = goodsOutPickingNote($this, [1], $clerk);
    [$otherNote]    = goodsOutPickingNote($this, [1], $clerk);
    $leaflet        = DeliveryNoteLeaflet::create([
        'group_id'         => $deliveryNote->group_id,
        'organisation_id'  => $deliveryNote->organisation_id,
        'shop_id'          => $deliveryNote->shop_id,
        'delivery_note_id' => $deliveryNote->id,
        'type'             => LeafletTypeEnum::PERSONALISED_MESSAGE,
        'name'             => 'Personalised message',
        'message'          => 'Thank you for your order',
        'data'             => [],
    ]);

    postGoodsOut('grp.api.mobile.delivery_notes.inserts.pdf', [$otherNote->id, $leaflet->id])->assertNotFound();

    $pdf = postGoodsOut('grp.api.mobile.delivery_notes.inserts.pdf', [$deliveryNote->id, $leaflet->id])->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($pdf->getContent())->toStartWith('%PDF')
        ->and($leaflet->fresh()->state)->toBe(DeliveryNoteLeafletStateEnum::PRINTED)
        ->and($leaflet->fresh()->printed_by_user_id)->toBe($clerk->id);

    postGoodsOut('grp.api.mobile.delivery_notes.inserts.print', $deliveryNote->id)->assertUnprocessable()->assertJsonValidationErrors(['printer']);

    actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_VIEWER]);
    postGoodsOut('grp.api.mobile.delivery_notes.inserts.pdf', [$deliveryNote->id, $leaflet->id])->assertForbidden();
});

test('mobile packing writes are refused on a note locked in Aurora', function () {
    $clerk = actingAsGoodsOutUser($this->group, $this->warehouse, [RolesEnum::DISPATCH_CLERK]);
    [$picked]            = mobilePickedNote($this, $clerk);
    [$packing, [$line]]  = mobilePackingNote($this, $clerk);
    $picked->update(['handled_in_aurora' => true]);
    $packing->update(['handled_in_aurora' => true]);

    getGoodsOut('grp.api.mobile.delivery_notes.show', $packing->id)->assertOk()
        ->assertJsonPath('data.available_actions', [])
        ->assertJsonPath('data.parcels_editable', false);

    postGoodsOut('grp.api.mobile.delivery_notes.start_packing', $picked->id)->assertUnprocessable()->assertJsonValidationErrors(['delivery_note']);
    postGoodsOut('grp.api.mobile.delivery_notes.lines.pack', [$packing->id, $line->id])->assertUnprocessable()->assertJsonValidationErrors(['delivery_note']);
    postGoodsOut('grp.api.mobile.delivery_notes.parcels', $packing->id, ['parcels' => [['weight_kg' => 1, 'dimensions_cm' => [10, 10, 10]]]])
        ->assertUnprocessable()->assertJsonValidationErrors(['delivery_note']);

    expect($picked->refresh()->state)->toBe(DeliveryNoteStateEnum::PICKED)
        ->and((float) $line->refresh()->packings()->sum('quantity'))->toBe(0.0);
});
