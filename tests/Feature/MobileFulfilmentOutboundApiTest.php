<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-11h-10m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Feature;

use App\Actions\Dispatching\Shipment\DetachShipmentFromPalletReturn;
use App\Actions\Dispatching\Shipment\StoreShipment;
use App\Actions\Dispatching\Shipper\StoreShipper;
use App\Actions\Fulfilment\PalletReturn\Notifications\SendPalletReturnNotification;
use App\Actions\Fulfilment\PalletReturnItem\PickPalletReturnItemByScan;
use App\Actions\Fulfilment\PalletStoredItem\RunPalletStoredItemQuantity;
use App\Enums\Dispatching\DeliveryNoteLeaflet\DeliveryNoteLeafletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnItemStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Enums\Fulfilment\PalletStoredItem\PalletStoredItemStateEnum;
use App\Enums\Fulfilment\StoredItem\StoredItemStateEnum;
use App\Enums\Catalogue\Leaflet\LeafletTypeEnum;
use App\Models\Dispatching\Shipment;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\PalletReturnItem;
use App\Models\Inventory\PickingSession;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/../Support/MobileFulfilmentOutboundHelpers.php';

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $this->organisation = createOrganisation();
    $this->group        = $this->organisation->group;
    $this->warehouse    = createWarehouse();
    $this->fulfilment   = mfoFulfilment($this->organisation, $this->warehouse);
    $this->customer     = mfoCustomer($this->fulfilment);
    $this->scanSetting  = (bool) data_get($this->organisation->settings, 'orders.allow_scan_to_pick', false);

    SendPalletReturnNotification::shouldRun()->andReturn();
});

afterEach(function () {
    setGoodsOutPickingSettings($this->organisation->refresh(), ['allow_scan_to_pick' => $this->scanSetting]);
});

test('mobile pallet returns list the active returns of the warehouse, by type group and search, with live counts', function () {
    $otherWarehouse = mfoOtherWarehouse($this->organisation);
    mfoWorker($this);
    $countsBefore = mfoGet('pallet_returns.index', ['warehouse' => $this->warehouse->id])->assertOk()->json('counts');

    $palletReturn = mfoPalletReturn($this->customer, $this->warehouse, [mfoPallet($this->customer, $this->warehouse, mfoLocation($this->warehouse))]);
    $stock        = mfoStock(mfoPallet($this->customer, $this->warehouse, mfoLocation($this->warehouse)), mfoStoredItem($this->customer), 10);
    $itemsReturn  = mfoItemsReturn($this->customer, $this->warehouse, [[$stock, 2]]);
    $dispatched   = mfoPalletReturn($this->customer, $this->warehouse, [mfoPallet($this->customer, $this->warehouse)]);
    $dispatched->update(['state' => PalletReturnStateEnum::DISPATCHED, 'dispatched_at' => now()]);
    $elsewhere = mfoPalletReturn($this->customer, $this->warehouse, [mfoPallet($this->customer, $this->warehouse)]);
    $elsewhere->update(['warehouse_id' => $otherWarehouse->id]);

    $customerName = $this->customer->customer->name;
    $references   = fn (array $query) => collect(mfoGet('pallet_returns.index', ['warehouse' => $this->warehouse->id], ['q' => $customerName, ...$query])->assertOk()->json('data'))->pluck('reference')->sort()->values()->all();

    expect($references([]))->toBe(collect([$palletReturn->reference, $itemsReturn->reference])->sort()->values()->all())
        ->and($references(['type' => 'pallets']))->toBe([$palletReturn->reference])
        ->and($references(['type' => 'items']))->toBe([$itemsReturn->reference])
        ->and($references(['state' => 'dispatched']))->toBe([$dispatched->reference]);

    $response = mfoGet('pallet_returns.index', ['warehouse' => $this->warehouse->id], ['q' => $itemsReturn->reference])->assertOk();
    expect($response->json('data'))->toHaveCount(1)
        ->and(array_keys($response->json('data.0')))->toBe(['id', 'reference', 'customer_reference', 'type', 'type_group', 'type_label', 'platform', 'state', 'state_label', 'customer', 'number_pallets', 'number_items', 'date', 'picker', 'is_collection'])
        ->and($response->json('data.0'))->toMatchArray([
            'id'             => $itemsReturn->id,
            'type'           => 'stored_item',
            'type_group'     => 'items',
            'type_label'     => 'Stored items',
            'state'          => 'confirmed',
            'customer'       => ['id' => $this->customer->id, 'name' => $customerName],
            'number_pallets' => 1,
            'number_items'   => 1,
            'picker'         => null,
        ]);

    $counts = mfoGet('pallet_returns.index', ['warehouse' => $this->warehouse->id])->assertOk()->json('counts');
    expect($counts['confirmed'] - $countsBefore['confirmed'])->toBe(2)
        ->and($counts['active'] - $countsBefore['active'])->toBe(2)
        ->and($counts['dispatched'] - $countsBefore['dispatched'])->toBe(1)
        ->and($counts['types']['pallets'] - $countsBefore['types']['pallets'])->toBe(1)
        ->and($counts['types']['items'] - $countsBefore['types']['items'])->toBe(1);

    $palletCounts = mfoGet('pallet_returns.index', ['warehouse' => $this->warehouse->id], ['type' => 'pallets', 'state' => 'dispatched'])->assertOk()->json('counts');
    expect(array_keys($palletCounts))->toBe(['confirmed', 'picking', 'picked', 'dispatched', 'cancel', 'active', 'types'])
        ->and($palletCounts['types']['pallets'] + $palletCounts['types']['items'])->toBe($counts['dispatched'])
        ->and($palletCounts['confirmed'])->toBeLessThanOrEqual($counts['confirmed']);

    mfoViewer($this);
    mfoGet('pallet_returns.index', ['warehouse' => $this->warehouse->id])->assertOk();
    actingAsGoodsOutUser($this->group, $this->warehouse, [], ["fulfilment-shop.{$this->fulfilment->id}.edit"]);
    mfoGet('pallet_returns.index', ['warehouse' => $this->warehouse->id])->assertForbidden();
});

test('mobile pallet returns sort by customer name like the web and refuse an unknown sort', function () {
    mfoWorker($this);
    $suffix = Str::upper(Str::random(8));
    $zed    = mfoCustomer($this->fulfilment);
    $zed->customer->update(['name' => 'Zed MFOSORT'.$suffix]);
    $abe = mfoCustomer($this->fulfilment);
    $abe->customer->update(['name' => 'Abe MFOSORT'.$suffix]);

    $zedReturn = mfoPalletReturn($zed, $this->warehouse, [mfoPallet($zed, $this->warehouse, mfoLocation($this->warehouse))]);
    $abeReturn = mfoPalletReturn($abe, $this->warehouse, [mfoPallet($abe, $this->warehouse, mfoLocation($this->warehouse))]);

    $references = fn (array $query) => collect(mfoGet('pallet_returns.index', ['warehouse' => $this->warehouse->id], ['q' => 'MFOSORT'.$suffix, ...$query])->assertOk()->json('data'))->pluck('reference')->all();

    expect($references(['sort' => 'customer']))->toBe([$abeReturn->reference, $zedReturn->reference])
        ->and($references(['sort' => '-customer']))->toBe([$zedReturn->reference, $abeReturn->reference]);

    mfoGet('pallet_returns.index', ['warehouse' => $this->warehouse->id], ['sort' => 'cust_name'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sort']);
});

test('mobile pallet return detail lists pallets in walk order with their items, sends no money and offers each action to the right person', function () {
    $later      = mfoPallet($this->customer, $this->warehouse, mfoLocation($this->warehouse, 'MFB'));
    $sooner     = mfoPallet($this->customer, $this->warehouse, mfoLocation($this->warehouse, 'MFA'));
    $storedItem = mfoStoredItem($this->customer);
    mfoStock($later, $storedItem, 7);
    $palletReturn = mfoPalletReturn($this->customer, $this->warehouse, [$later, $sooner]);
    $elsewhere    = mfoPalletReturn($this->customer, $this->warehouse, [mfoPallet($this->customer, $this->warehouse)]);
    $elsewhere->update(['warehouse_id' => mfoOtherWarehouse($this->organisation)->id]);
    $route = mfoReturnRoute($this->warehouse, $palletReturn);

    mfoViewer($this);
    $detail = mfoGet('pallet_returns.show', $route)->assertOk();
    expect(array_keys($detail->json('data')))->toBe([
        'id', 'reference', 'customer_reference', 'type', 'type_group', 'type_label', 'platform', 'state', 'state_label', 'customer',
        'is_collection', 'collection_notes', 'customer_notes', 'public_notes', 'internal_notes', 'picker', 'packer', 'estimated_delivery_date',
        'in_picking_session', 'timeline', 'counts', 'pallets', 'stored_items', 'inserts', 'shipments', 'parcels', 'shipment_required',
        'dispatch_blocked', 'can_scan', 'available_actions',
    ])
        ->and($detail->json('data.type_group'))->toBe('pallets')
        ->and($detail->json('data.type_label'))->toBe('Pallets')
        ->and($detail->json('data.stored_items'))->toBe([])
        ->and(collect($detail->json('data.pallets'))->pluck('pallet.id')->all())->toBe([$sooner->id, $later->id])
        ->and(array_keys($detail->json('data.pallets.0')))->toBe(['line_id', 'line_state', 'line_state_label', 'waiting_customer_service', 'pallet', 'location', 'stored_items', 'available_actions'])
        ->and($detail->json('data.pallets.1.stored_items'))->toBe([['reference' => $storedItem->reference, 'name' => $storedItem->name, 'quantity' => 7]])
        ->and($detail->json('data.pallets.0.available_actions'))->toBe([])
        ->and($detail->json('data.available_actions'))->toBe([])
        ->and($detail->json('data.counts'))->toBe(['lines' => 2, 'picked' => 0, 'not_picked' => 0, 'to_pick' => 2, 'waiting_customer_service' => 0])
        ->and(collect($detail->json('data.timeline'))->pluck('state')->all())->toBe(['confirmed', 'picking', 'picked', 'dispatched'])
        ->and($detail->json('data.inserts'))->toBe(['total' => 0, 'unprinted' => 0])
        ->and($detail->json('data.dispatch_blocked'))->toBeNull()
        ->and(Str::contains($detail->getContent(), ['amount', 'price', 'invoice', 'recurring', 'rental']))->toBeFalse();
    mfoGet('pallet_returns.show', mfoReturnRoute($this->warehouse, $elsewhere))->assertNotFound();
    mfoPost('pallet_returns.start_picking', $route)->assertForbidden();

    mfoShopClerk($this);
    expect(mfoActionKeys(mfoGet('pallet_returns.show', $route)->assertOk()->json('data.available_actions')))->toBe(['start_picking']);

    $worker  = mfoWorker($this);
    $started = mfoPost('pallet_returns.start_picking', $route)->assertOk()
        ->assertJsonPath('result.status', 'done')
        ->assertJsonPath('data.state', 'picking')
        ->assertJsonPath('data.picker', ['id' => $worker->id, 'name' => $worker->contact_name ?: $worker->username]);
    expect(mfoActionKeys($started->json('data.pallets.0.available_actions')))->toBe(['pick', 'not_picked'])
        ->and(mfoActionKeys($started->json('data.available_actions')))->toBe(['add_shipment'])
        ->and($started->json('data.timeline.1.at'))->not->toBeNull();
    mfoPost('pallet_returns.start_picking', $route)->assertUnprocessable()->assertJsonValidationErrors('state');

    mfoShopClerk($this);
    mfoPost('pallet_returns.lines.pick', mfoReturnRoute($this->warehouse, $palletReturn, ['palletReturnItem' => $started->json('data.pallets.0.line_id')]))->assertForbidden();
});

test('mobile stored item return detail groups lines per item in walk order, with quantities and the other pallets while picking', function () {
    $storedItem = mfoStoredItem($this->customer, ['barcode' => 'MFOEAN'.Str::upper(Str::random(8))]);
    $palletB    = mfoPallet($this->customer, $this->warehouse, mfoLocation($this->warehouse, 'MFB'));
    $palletA    = mfoPallet($this->customer, $this->warehouse, mfoLocation($this->warehouse, 'MFA'));
    $spare      = mfoPallet($this->customer, $this->warehouse, mfoLocation($this->warehouse, 'MFC'));
    $stockB     = mfoStock($palletB, $storedItem, 10);
    $stockA     = mfoStock($palletA, $storedItem, 5);
    $spareStock = mfoStock($spare, $storedItem, 6);
    $palletReturn = mfoItemsReturn($this->customer, $this->warehouse, [[$stockB, 3], [$stockA, 2]]);
    $route        = mfoReturnRoute($this->warehouse, $palletReturn);

    mfoWorker($this);
    $confirmed = mfoGet('pallet_returns.show', $route)->assertOk()->json('data');
    expect($confirmed['pallets'])->toBe([])
        ->and($confirmed['type_label'])->toBe('Stored items')
        ->and($confirmed['stored_items'][0]['other_pallets'])->toBe([])
        ->and($confirmed['stored_items'][0]['lines'][0]['available_actions'])->toBe([]);

    $item = mfoPost('pallet_returns.start_picking', $route)->assertOk()->json('data.stored_items.0');
    expect(array_keys($item))->toBe(['stored_item', 'quantity_ordered', 'quantity_picked', 'quantity_not_picked', 'quantity_to_pick', 'lines', 'other_pallets'])
        ->and($item['stored_item'])->toBe(['id' => $storedItem->id, 'reference' => $storedItem->reference, 'name' => $storedItem->name, 'barcode' => $storedItem->barcode])
        ->and([$item['quantity_ordered'], $item['quantity_picked'], $item['quantity_not_picked'], $item['quantity_to_pick']])->toBe([5, 0, 0, 5])
        ->and(collect($item['lines'])->pluck('pallet.id')->all())->toBe([$palletA->id, $palletB->id])
        ->and(array_keys($item['lines'][0]))->toBe(['line_id', 'pallet', 'location', 'quantity_ordered', 'quantity_picked', 'quantity_not_picked', 'quantity_to_pick', 'quantity_on_pallet', 'state', 'state_label', 'waiting_customer_service', 'available_actions'])
        ->and($item['lines'][0])->toMatchArray(['quantity_ordered' => 2, 'quantity_to_pick' => 2, 'quantity_on_pallet' => 5])
        ->and($item['lines'][1])->toMatchArray(['quantity_ordered' => 3, 'quantity_to_pick' => 3, 'quantity_on_pallet' => 10])
        ->and(mfoActionKeys($item['lines'][0]['available_actions']))->toBe(['pick', 'not_picked'])
        ->and($item['other_pallets'])->toBe([[
            'pallet_stored_item_id' => $spareStock->id,
            'pallet'                => ['id' => $spare->id, 'reference' => $spare->reference, 'customer_reference' => $spare->customer_reference],
            'location'              => ['id' => $spare->location_id, 'code' => $spare->location->code],
            'quantity_available'    => 6,
        ]]);
});

test('mobile refuses every write on a return in a picking session and lists no action there', function () {
    $stock        = mfoStock(mfoPallet($this->customer, $this->warehouse, mfoLocation($this->warehouse)), mfoStoredItem($this->customer), 8);
    $palletReturn = mfoItemsReturn($this->customer, $this->warehouse, [[$stock, 3]]);
    $route        = mfoReturnRoute($this->warehouse, $palletReturn);
    $worker       = mfoWorker($this);
    mfoPost('pallet_returns.start_picking', $route)->assertOk();

    $session = PickingSession::create([
        'group_id'        => $this->warehouse->group_id,
        'organisation_id' => $this->warehouse->organisation_id,
        'warehouse_id'    => $this->warehouse->id,
        'user_id'         => $worker->id,
        'reference'       => 'MFO-'.Str::upper(Str::random(8)),
    ]);
    $line = PalletReturnItem::where('pallet_return_id', $palletReturn->id)->sole();
    $line->update(['picking_session_id' => $session->id]);

    $detail = mfoGet('pallet_returns.show', $route)->assertOk()->json('data');
    expect($detail['in_picking_session'])->toBeTrue()
        ->and($detail['available_actions'])->toBe([])
        ->and($detail['stored_items'][0]['lines'][0]['available_actions'])->toBe([])
        ->and($detail['can_scan'])->toBeFalse();

    mfoPost('pallet_returns.lines.pick', [...$route, 'palletReturnItem' => $line->id], ['quantity' => 1, 'client_request_id' => (string) Str::uuid()])
        ->assertUnprocessable()
        ->assertJsonPath('errors.state.0', 'This return is in a picking session; work it on the web.');
    mfoPost('pallet_returns.finish_picking', $route)->assertUnprocessable()->assertJsonPath('errors.state.0', 'This return is in a picking session; work it on the web.');
    expect((float) $line->refresh()->quantity_picked)->toBe(0.0);
});

test('mobile picks whole pallets, parks a not picked pallet for customer service, undoes and sets the pallet return as picked', function () {
    $first        = mfoPallet($this->customer, $this->warehouse, mfoLocation($this->warehouse, 'MFA'));
    $second       = mfoPallet($this->customer, $this->warehouse, mfoLocation($this->warehouse, 'MFB'));
    $palletReturn = mfoPalletReturn($this->customer, $this->warehouse, [$first, $second]);
    $route        = mfoReturnRoute($this->warehouse, $palletReturn);
    $worker       = mfoWorker($this);
    mfoPost('pallet_returns.start_picking', $route)->assertOk();

    $lines = PalletReturnItem::where('pallet_return_id', $palletReturn->id)->get()->keyBy('pallet_id');
    $line  = fn (Pallet $pallet) => [...$route, 'palletReturnItem' => $lines[$pallet->id]->id];

    $picked = mfoPost('pallet_returns.lines.pick', $line($first))->assertOk()
        ->assertJsonPath('result.message', "Pallet $first->reference picked.")
        ->assertJsonPath('data.pallets.0.line_state', 'picked')
        ->assertJsonPath('data.pallets.0.pallet.state', 'picked')
        ->assertJsonPath('data.counts.picked', 1);
    expect(mfoActionKeys($picked->json('data.pallets.0.available_actions')))->toBe(['undo'])
        ->and(mfoActionKeys($picked->json('data.available_actions')))->not->toContain('finish_picking');
    mfoPost('pallet_returns.lines.pick', $line($first))->assertUnprocessable()->assertJsonValidationErrors('state');

    $parked = mfoPost('pallet_returns.lines.not_picked', $line($second))->assertOk()
        ->assertJsonPath('data.pallets.1.waiting_customer_service', true)
        ->assertJsonPath('data.pallets.1.line_state', 'picking')
        ->assertJsonPath('data.counts.waiting_customer_service', 1);
    expect($parked->json('result.message'))->toContain('Customer service must resolve it')
        ->and(mfoActionKeys($parked->json('data.pallets.1.available_actions')))->toBe(['undo'])
        ->and(mfoActionKeys($parked->json('data.available_actions')))->not->toContain('finish_picking');
    mfoPost('pallet_returns.finish_picking', $route)->assertUnprocessable()->assertJsonPath('errors.state.0', 'Customer service must resolve the pallets set as not picked first.');
    mfoPost('pallet_returns.lines.pick', $line($second))->assertUnprocessable()->assertJsonPath('errors.state.0', 'This line is waiting for customer service.');

    mfoPost('pallet_returns.lines.undo', $line($second))->assertOk()->assertJsonPath('data.pallets.1.waiting_customer_service', false);
    mfoPost('pallet_returns.lines.undo', $line($first))->assertOk()->assertJsonPath('data.pallets.0.line_state', 'picking');
    mfoPost('pallet_returns.lines.undo', $line($first))->assertUnprocessable()->assertJsonPath('errors.state.0', 'There is nothing to undo on this line.');
    mfoPost('pallet_returns.lines.pick', $line($first))->assertOk();
    $ready = mfoPost('pallet_returns.lines.pick', $line($second))->assertOk();
    expect(mfoActionKeys($ready->json('data.available_actions')))->toBe(['finish_picking', 'add_shipment']);

    mfoPost('pallet_returns.finish_picking', $route)->assertOk()
        ->assertJsonPath('data.state', 'picked')
        ->assertJsonPath('data.packer.id', $worker->id)
        ->assertJsonPath('result.message', 'Return set as picked.');
    mfoPost('pallet_returns.lines.undo', $line($first))->assertUnprocessable()->assertJsonValidationErrors('state');
});

test('mobile picks a stored item line by quantity as a new total, refuses more than is left and never picks one request twice', function () {
    $stock        = mfoStock(mfoPallet($this->customer, $this->warehouse, mfoLocation($this->warehouse)), mfoStoredItem($this->customer), 8);
    $palletReturn = mfoItemsReturn($this->customer, $this->warehouse, [[$stock, 5]]);
    $worker       = mfoWorker($this);
    mfoPost('pallet_returns.start_picking', mfoReturnRoute($this->warehouse, $palletReturn))->assertOk();
    $route     = mfoReturnRoute($this->warehouse, $palletReturn, ['palletReturnItem' => PalletReturnItem::where('pallet_return_id', $palletReturn->id)->sole()->id]);
    $requestId = (string) Str::uuid();

    mfoPost('pallet_returns.lines.pick', $route, ['quantity' => 2])->assertUnprocessable()->assertJsonValidationErrors('client_request_id');
    mfoPost('pallet_returns.lines.pick', $route, ['client_request_id' => $requestId])->assertUnprocessable()->assertJsonValidationErrors('quantity');
    mfoPost('pallet_returns.lines.pick', $route, ['quantity' => 2, 'client_request_id' => $requestId])->assertOk()
        ->assertJsonPath('result.status', 'done')
        ->assertJsonPath('data.stored_items.0.lines.0.quantity_picked', 2)
        ->assertJsonPath('data.stored_items.0.lines.0.quantity_to_pick', 3)
        ->assertJsonPath('data.stored_items.0.lines.0.quantity_on_pallet', 6)
        ->assertJsonPath('data.packer.id', $worker->id);
    mfoPost('pallet_returns.lines.pick', $route, ['quantity' => 2, 'client_request_id' => $requestId])->assertOk()
        ->assertJsonPath('result.status', 'nothing_changed')
        ->assertJsonPath('data.stored_items.0.lines.0.quantity_picked', 2);
    mfoPost('pallet_returns.lines.pick', $route, ['quantity' => 4, 'client_request_id' => (string) Str::uuid()])->assertUnprocessable()
        ->assertJsonPath('errors.quantity.0', 'Only 3 left to pick on this line.');

    $stock->refresh()->update(['quantity' => 1]);
    mfoPost('pallet_returns.lines.pick', $route, ['quantity' => 2, 'client_request_id' => (string) Str::uuid()])->assertUnprocessable()
        ->assertJsonPath('errors.quantity.0', 'Only 1 left on this pallet.');
    RunPalletStoredItemQuantity::run($stock->refresh());

    $all = mfoPost('pallet_returns.lines.pick', $route, ['all' => true, 'client_request_id' => (string) Str::uuid()])->assertOk()
        ->assertJsonPath('data.stored_items.0.lines.0.quantity_picked', 5)
        ->assertJsonPath('data.stored_items.0.quantity_to_pick', 0);
    expect(mfoActionKeys($all->json('data.stored_items.0.lines.0.available_actions')))->toBe(['undo'])
        ->and(mfoActionKeys($all->json('data.available_actions')))->toContain('finish_picking')
        ->and((float) $stock->refresh()->quantity)->toBe(3.0);
    mfoPost('pallet_returns.lines.pick', $route, ['all' => true, 'client_request_id' => (string) Str::uuid()])->assertUnprocessable()->assertJsonValidationErrors('state');
});

test('mobile sets stored item quantities as not picked and undoes a stored item line', function () {
    $stock        = mfoStock(mfoPallet($this->customer, $this->warehouse, mfoLocation($this->warehouse)), mfoStoredItem($this->customer), 8);
    $palletReturn = mfoItemsReturn($this->customer, $this->warehouse, [[$stock, 5]]);
    mfoWorker($this);
    mfoPost('pallet_returns.start_picking', mfoReturnRoute($this->warehouse, $palletReturn))->assertOk();
    $route = mfoReturnRoute($this->warehouse, $palletReturn, ['palletReturnItem' => PalletReturnItem::where('pallet_return_id', $palletReturn->id)->sole()->id]);

    mfoPost('pallet_returns.lines.pick', $route, ['quantity' => 2, 'client_request_id' => (string) Str::uuid()])->assertOk();
    mfoPost('pallet_returns.lines.not_picked', $route, ['quantity' => 4])->assertUnprocessable()->assertJsonPath('errors.quantity.0', 'Only 3 left to pick on this line.');
    mfoPost('pallet_returns.lines.not_picked', $route, ['quantity' => 1])->assertOk()
        ->assertJsonPath('data.stored_items.0.lines.0.quantity_not_picked', 1)
        ->assertJsonPath('data.stored_items.0.lines.0.quantity_to_pick', 2);
    $rest = mfoPost('pallet_returns.lines.not_picked', $route)->assertOk()
        ->assertJsonPath('data.stored_items.0.lines.0.quantity_not_picked', 3)
        ->assertJsonPath('data.stored_items.0.lines.0.quantity_to_pick', 0)
        ->assertJsonPath('data.counts.picked', 1);
    expect(mfoActionKeys($rest->json('data.stored_items.0.lines.0.available_actions')))->toBe(['undo']);
    mfoPost('pallet_returns.lines.not_picked', $route)->assertUnprocessable()->assertJsonValidationErrors('state');

    mfoPost('pallet_returns.lines.undo', $route)->assertOk()
        ->assertJsonPath('data.stored_items.0.lines.0.quantity_picked', 0)
        ->assertJsonPath('data.stored_items.0.lines.0.quantity_not_picked', 0)
        ->assertJsonPath('data.stored_items.0.lines.0.quantity_on_pallet', 8);
    mfoPost('pallet_returns.lines.undo', $route)->assertUnprocessable()->assertJsonPath('errors.state.0', 'There is nothing to undo on this line.');
});

test('mobile picks a stored item from another pallet up to what is left, sets the shortfall off the short line and touches no other item on that pallet', function () {
    $storedItem   = mfoStoredItem($this->customer);
    $otherItem    = mfoStoredItem($this->customer);
    $shortPallet  = mfoPallet($this->customer, $this->warehouse, mfoLocation($this->warehouse));
    $sparePallet  = mfoPallet($this->customer, $this->warehouse, mfoLocation($this->warehouse));
    $shortStock   = mfoStock($shortPallet, $storedItem, 3);
    $spareStock   = mfoStock($sparePallet, $storedItem, 5);
    $otherStock   = mfoStock($sparePallet, $otherItem, 4);
    $palletReturn = mfoItemsReturn($this->customer, $this->warehouse, [[$shortStock, 3], [$otherStock, 1]]);
    mfoWorker($this);

    $picking  = mfoPost('pallet_returns.start_picking', mfoReturnRoute($this->warehouse, $palletReturn))->assertOk()->json('data.stored_items');
    $itemRows = collect($picking)->keyBy('stored_item.id');
    expect(collect($itemRows[$storedItem->id]['other_pallets'])->pluck('pallet_stored_item_id')->all())->toBe([$spareStock->id])
        ->and($itemRows[$otherItem->id]['other_pallets'])->toBe([]);

    $shortLine = PalletReturnItem::where('pallet_return_id', $palletReturn->id)->where('pallet_stored_item_id', $shortStock->id)->sole();
    $otherLine = PalletReturnItem::where('pallet_return_id', $palletReturn->id)->where('pallet_stored_item_id', $otherStock->id)->sole();
    $route     = mfoReturnRoute($this->warehouse, $palletReturn, ['palletStoredItem' => $spareStock->id]);

    mfoPost('pallet_returns.pallet_stored_items.pick', $route, ['quantity' => 4, 'client_request_id' => (string) Str::uuid()])->assertUnprocessable()
        ->assertJsonPath('errors.quantity.0', 'Only 3 can be picked from this pallet.');
    mfoPost('pallet_returns.pallet_stored_items.pick', $route, ['quantity' => 1, 'from_line_id' => $otherLine->id, 'client_request_id' => (string) Str::uuid()])->assertUnprocessable()
        ->assertJsonValidationErrors('from_line_id');
    mfoPost('pallet_returns.pallet_stored_items.pick', mfoReturnRoute($this->warehouse, $palletReturn, ['palletStoredItem' => $otherStock->id]), ['quantity' => 1, 'client_request_id' => (string) Str::uuid()])->assertUnprocessable()
        ->assertJsonValidationErrors('state');

    $requestId = (string) Str::uuid();
    $picked    = mfoPost('pallet_returns.pallet_stored_items.pick', $route, ['quantity' => 2, 'from_line_id' => $shortLine->id, 'client_request_id' => $requestId])->assertOk()
        ->assertJsonPath('result.status', 'done');
    $newLine = PalletReturnItem::where('pallet_return_id', $palletReturn->id)->where('pallet_stored_item_id', $spareStock->id)->sole();
    $itemRow = collect($picked->json('data.stored_items'))->firstWhere('stored_item.id', $storedItem->id);
    expect((float) $newLine->quantity_ordered)->toBe(2.0)
        ->and((float) $newLine->quantity_picked)->toBe(2.0)
        ->and((float) $otherLine->refresh()->quantity_picked)->toBe(0.0)
        ->and((float) $shortLine->refresh()->quantity_not_picked)->toBe(2.0)
        ->and($itemRow['quantity_to_pick'])->toBe(1)
        ->and($itemRow['other_pallets'])->toBe([])
        ->and((float) $spareStock->refresh()->quantity)->toBe(3.0);

    mfoPost('pallet_returns.pallet_stored_items.pick', $route, ['quantity' => 2, 'from_line_id' => $shortLine->id, 'client_request_id' => $requestId])->assertOk()
        ->assertJsonPath('result.status', 'nothing_changed');
    expect(PalletReturnItem::where('pallet_return_id', $palletReturn->id)->where('pallet_stored_item_id', $spareStock->id)->count())->toBe(1);
});

test('mobile scan to pick needs the organisation setting, picks the scanned stored item and never repeats a request', function () {
    $storedItem   = mfoStoredItem($this->customer, ['barcode' => 'MFOEAN'.Str::upper(Str::random(8))]);
    $stock        = mfoStock(mfoPallet($this->customer, $this->warehouse, mfoLocation($this->warehouse)), $storedItem, 8);
    $palletReturn = mfoItemsReturn($this->customer, $this->warehouse, [[$stock, 3]]);
    $route        = mfoReturnRoute($this->warehouse, $palletReturn);
    mfoWorker($this);

    setGoodsOutPickingSettings($this->organisation->refresh(), ['allow_scan_to_pick' => false]);
    mfoPost('pallet_returns.start_picking', $route)->assertOk()->assertJsonPath('data.can_scan', false);
    mfoPost('pallet_returns.scan', $route, ['barcode' => $storedItem->reference, 'client_request_id' => (string) Str::uuid()])->assertForbidden()
        ->assertJsonPath('message', 'Scan to pick is not enabled for this organisation.');

    setGoodsOutPickingSettings($this->organisation->refresh(), ['allow_scan_to_pick' => true]);
    mfoGet('pallet_returns.show', $route)->assertOk()->assertJsonPath('data.can_scan', true);
    $line      = PalletReturnItem::where('pallet_return_id', $palletReturn->id)->sole();
    $requestId = (string) Str::uuid();

    mfoPost('pallet_returns.scan', $route, ['barcode' => Str::lower($storedItem->reference), 'client_request_id' => $requestId])->assertOk()
        ->assertJsonPath('result.status', 'picked')
        ->assertJsonPath('result.line_id', $line->id)
        ->assertJsonPath('data.stored_items.0.quantity_picked', 1);
    mfoPost('pallet_returns.scan', $route, ['barcode' => $storedItem->reference, 'client_request_id' => $requestId])->assertOk()
        ->assertJsonPath('result.status', 'nothing_changed')
        ->assertJsonPath('data.stored_items.0.quantity_picked', 1);
    mfoPost('pallet_returns.scan', $route, ['barcode' => 'NOT-IN-THIS-RETURN', 'client_request_id' => (string) Str::uuid()])->assertOk()
        ->assertJsonPath('result.status', 'not_found')
        ->assertJsonPath('result.line_id', null);
    mfoPost('pallet_returns.scan', $route, ['barcode' => $storedItem->barcode, 'quantity' => 5, 'client_request_id' => (string) Str::uuid()])->assertOk()
        ->assertJsonPath('result.status', 'picked')
        ->assertJsonPath('data.stored_items.0.quantity_picked', 3);
    mfoPost('pallet_returns.scan', $route, ['barcode' => $storedItem->reference, 'client_request_id' => (string) Str::uuid()])->assertOk()
        ->assertJsonPath('result.status', 'already_picked');
    mfoPost('pallet_returns.scan', $route, ['quantity' => 0, 'client_request_id' => (string) Str::uuid()])->assertUnprocessable()
        ->assertJsonValidationErrors(['barcode', 'quantity']);

    mfoViewer($this);
    mfoPost('pallet_returns.scan', $route, ['barcode' => $storedItem->reference, 'client_request_id' => (string) Str::uuid()])->assertForbidden();
});

test('mobile scan picks a whole pallet but never one waiting for customer service, and the web scan picks as before', function () {
    setGoodsOutPickingSettings($this->organisation->refresh(), ['allow_scan_to_pick' => true]);
    $first        = mfoPallet($this->customer, $this->warehouse, mfoLocation($this->warehouse, 'MFA'));
    $second       = mfoPallet($this->customer, $this->warehouse, mfoLocation($this->warehouse, 'MFB'));
    $palletReturn = mfoPalletReturn($this->customer, $this->warehouse, [$first, $second]);
    $route        = mfoReturnRoute($this->warehouse, $palletReturn);
    $worker       = mfoWorker($this);
    mfoPost('pallet_returns.scan', $route, ['barcode' => $first->reference, 'client_request_id' => (string) Str::uuid()])->assertOk()
        ->assertJsonPath('result.status', 'wrong_state')
        ->assertJsonPath('data.state', 'confirmed');
    mfoPost('pallet_returns.start_picking', $route)->assertOk();
    $lines = PalletReturnItem::where('pallet_return_id', $palletReturn->id)->get()->keyBy('pallet_id');

    mfoPost('pallet_returns.lines.not_picked', [...$route, 'palletReturnItem' => $lines[$second->id]->id])->assertOk();
    mfoPost('pallet_returns.scan', $route, ['barcode' => $second->reference, 'client_request_id' => (string) Str::uuid()])->assertOk()
        ->assertJsonPath('result.status', 'not_found')
        ->assertJsonPath('result.line_id', $lines[$second->id]->id)
        ->assertJsonPath('result.message', "$second->reference is waiting for customer service, it can not be picked.");
    expect($lines[$second->id]->refresh()->state)->toBe(PalletReturnItemStateEnum::PICKING);

    mfoPost('pallet_returns.scan', $route, ['barcode' => $first->customer_reference, 'client_request_id' => (string) Str::uuid()])->assertOk()
        ->assertJsonPath('result.status', 'picked')
        ->assertJsonPath('result.line_id', $lines[$first->id]->id)
        ->assertJsonPath('data.pallets.0.line_state', 'picked');

    mfoPost('pallet_returns.lines.undo', [...$route, 'palletReturnItem' => $lines[$first->id]->id])->assertOk();
    $webScan = PickPalletReturnItemByScan::make()->handle($palletReturn->refresh(), $worker, ['barcode' => $first->reference]);
    expect($webScan['status'])->toBe('picked')
        ->and($webScan['item']['id'])->toBe($lines[$first->id]->id)
        ->and($lines[$first->id]->refresh()->state)->toBe(PalletReturnItemStateEnum::PICKED);
});

test('mobile sets a stored item return as picked once every line is done, without the web 419', function () {
    $stock        = mfoStock(mfoPallet($this->customer, $this->warehouse, mfoLocation($this->warehouse)), mfoStoredItem($this->customer), 8);
    $palletReturn = mfoItemsReturn($this->customer, $this->warehouse, [[$stock, 3]]);
    $route        = mfoReturnRoute($this->warehouse, $palletReturn);
    mfoWorker($this);
    mfoPost('pallet_returns.start_picking', $route)->assertOk();
    mfoPost('pallet_returns.finish_picking', $route)->assertUnprocessable()->assertJsonPath('errors.state.0', '1 line is still to pick or set as not picked.');

    $lineRoute = [...$route, 'palletReturnItem' => PalletReturnItem::where('pallet_return_id', $palletReturn->id)->sole()->id];
    mfoPost('pallet_returns.lines.pick', $lineRoute, ['quantity' => 2, 'client_request_id' => (string) Str::uuid()])->assertOk();
    mfoPost('pallet_returns.lines.not_picked', $lineRoute)->assertOk();

    mfoPost('pallet_returns.finish_picking', $route)->assertOk()
        ->assertJsonPath('data.state', 'picked')
        ->assertJsonPath('result.message', 'Return set as picked.');
    expect($palletReturn->refresh()->state)->toBe(PalletReturnStateEnum::PICKED)
        ->and($palletReturn->picked_at)->not->toBeNull();
    mfoPost('pallet_returns.finish_picking', $route)->assertUnprocessable()->assertJsonValidationErrors('state');
});

test('mobile dispatch waits for the customer inserts and a shipment, and a collection needs no shipment', function () {
    $pallet       = mfoPallet($this->customer, $this->warehouse, mfoLocation($this->warehouse));
    $palletReturn = mfoPalletReturn($this->customer, $this->warehouse, [$pallet]);
    $route        = mfoReturnRoute($this->warehouse, $palletReturn);
    mfoWorker($this);
    mfoPost('pallet_returns.start_picking', $route)->assertOk();
    mfoPost('pallet_returns.lines.pick', [...$route, 'palletReturnItem' => PalletReturnItem::where('pallet_return_id', $palletReturn->id)->sole()->id])->assertOk();
    $picked = mfoPost('pallet_returns.finish_picking', $route)->assertOk()->json('data');

    expect($picked['dispatch_blocked'])->toBe('Add a shipment first.')
        ->and($picked['shipment_required'])->toBeTrue()
        ->and(mfoActionKeys($picked['available_actions']))->toBe(['add_shipment']);

    $leaflet = $palletReturn->leaflets()->create([
        'group_id'        => $palletReturn->group_id,
        'organisation_id' => $palletReturn->organisation_id,
        'type'            => LeafletTypeEnum::PERSONALISED_MESSAGE,
        'name'            => 'Thank you',
        'message'         => 'Thank you for your order',
        'copies'          => 1,
        'state'           => DeliveryNoteLeafletStateEnum::PENDING_PRINT,
    ]);
    mfoGet('pallet_returns.show', $route)->assertOk()
        ->assertJsonPath('data.dispatch_blocked', 'Print the customer inserts first.')
        ->assertJsonPath('data.inserts', ['total' => 1, 'unprinted' => 1]);
    mfoPost('pallet_returns.dispatch', $route)->assertUnprocessable()->assertJsonPath('errors.state.0', 'Print the customer inserts first.');
    mfoPost('pallet_returns.inserts.print', $route)->assertUnprocessable()->assertJsonValidationErrors('printer');

    $leaflet->update(['state' => DeliveryNoteLeafletStateEnum::PRINTED]);
    mfoPost('pallet_returns.dispatch', $route)->assertUnprocessable()->assertJsonPath('errors.state.0', 'Add a shipment first.');

    $shipper = StoreShipper::make()->action($this->organisation, ['code' => 'MFM'.Str::upper(Str::random(4)), 'name' => 'Manual', 'trade_as' => 'manual']);
    $shipped = mfoPost('pallet_returns.shipments.store', $route, ['shipper_id' => $shipper->id, 'tracking' => 'TRK'.Str::random(6)])->assertOk()
        ->assertJsonPath('data.dispatch_blocked', null);
    expect(mfoActionKeys($shipped->json('data.available_actions')))->toBe(['dispatch', 'add_shipment']);

    mfoPost('pallet_returns.dispatch', $route)->assertOk()
        ->assertJsonPath('data.state', 'dispatched')
        ->assertJsonPath('data.available_actions', [])
        ->assertJsonPath('result.message', 'Return dispatched.');
    expect($pallet->refresh()->status)->toBe(PalletStatusEnum::RETURNED);
    mfoPost('pallet_returns.dispatch', $route)->assertUnprocessable()->assertJsonValidationErrors('state');

    $collected      = mfoPalletReturn($this->customer, $this->warehouse, [mfoPallet($this->customer, $this->warehouse, mfoLocation($this->warehouse))], isCollection: true);
    $collectedRoute = mfoReturnRoute($this->warehouse, $collected);
    mfoPost('pallet_returns.start_picking', $collectedRoute)->assertOk();
    mfoPost('pallet_returns.lines.pick', [...$collectedRoute, 'palletReturnItem' => PalletReturnItem::where('pallet_return_id', $collected->id)->sole()->id])->assertOk();
    $ready = mfoPost('pallet_returns.finish_picking', $collectedRoute)->assertOk()->json('data');
    expect($ready['shipment_required'])->toBeFalse()
        ->and($ready['dispatch_blocked'])->toBeNull()
        ->and($ready['available_actions'])->toBe([['key' => 'dispatch', 'label' => 'Set as collected']]);
    mfoGet('pallet_returns.shippers', $collectedRoute)->assertUnprocessable()->assertJsonValidationErrors('state');
    mfoPost('pallet_returns.dispatch', $collectedRoute)->assertOk()->assertJsonPath('result.message', 'Return set as collected.');
});

test('mobile adds, prints and removes the shipments of a pallet return with its own rules', function () {
    $foreignShipper = StoreShipper::make()->action(mfoOtherOrganisation($this->organisation), ['code' => 'MFF'.Str::upper(Str::random(4)), 'name' => 'Foreign', 'trade_as' => 'foreign']);
    $palletReturn   = mfoPalletReturn($this->customer, $this->warehouse, [mfoPallet($this->customer, $this->warehouse, mfoLocation($this->warehouse))]);
    $route          = mfoReturnRoute($this->warehouse, $palletReturn);
    mfoWorker($this);
    mfoGet('pallet_returns.shippers', $route)->assertUnprocessable()->assertJsonValidationErrors('state');
    mfoPost('pallet_returns.start_picking', $route)->assertOk();

    $manual = StoreShipper::make()->action($this->organisation, ['code' => 'MFM'.Str::upper(Str::random(4)), 'name' => 'Manual', 'trade_as' => 'manual']);
    $api    = StoreShipper::make()->action($this->organisation, ['code' => 'MFA'.Str::upper(Str::random(4)), 'name' => 'Api', 'trade_as' => 'api', 'api_shipper' => 'apc-gb']);
    $ctt    = StoreShipper::make()->action($this->organisation, ['code' => 'MFC'.Str::upper(Str::random(4)), 'name' => 'Ctt', 'trade_as' => 'ctt', 'api_shipper' => 'ctt-es']);

    $shippers = collect(mfoGet('pallet_returns.shippers', $route)->assertOk()->json('data'))->keyBy('id');
    expect($shippers->keys()->all())->toContain($manual->id, $api->id)
        ->and($shippers->has($ctt->id))->toBeFalse()
        ->and($shippers->has($foreignShipper->id))->toBeFalse()
        ->and(array_keys($shippers[$manual->id]))->toBe(['id', 'code', 'name', 'trade_as', 'api_shipper', 'type', 'needs_tracking', 'is_locked_out'])
        ->and($shippers[$api->id]['type'])->toBe('api');

    mfoPost('pallet_returns.shipments.store', $route, ['shipper_id' => $foreignShipper->id])->assertUnprocessable()->assertJsonValidationErrors('shipper_id');
    mfoPost('pallet_returns.shipments.store', $route, ['shipper_id' => $ctt->id])->assertUnprocessable()->assertJsonValidationErrors('shipper_id');
    mfoPost('pallet_returns.shipments.store', $route, ['shipper_id' => $api->id])->assertUnprocessable()->assertJsonPath('errors.parcels.0', 'Add the parcels first.');

    mfoPost('pallet_returns.parcels', $route, ['parcels' => [['weight_kg' => 0, 'dimensions_cm' => [1, 2]]]])->assertUnprocessable()->assertJsonValidationErrors(['parcels.0.weight_kg', 'parcels.0.dimensions_cm']);
    mfoPost('pallet_returns.parcels', $route, ['parcels' => [['weight_kg' => 12.5, 'dimensions_cm' => [120, 80, 100]]]])->assertOk()
        ->assertJsonPath('data.parcels', [['weight' => 12.5, 'dimensions' => [120, 80, 100]]]);

    $added = mfoPost('pallet_returns.shipments.store', $route, ['shipper_id' => $manual->id, 'tracking' => 'TRK123'])->assertOk()
        ->assertJsonPath('result.message', 'Shipment added.');
    $row = $added->json('data.shipments.0');
    expect(array_keys($row))->toBe(['id', 'shipper', 'shipper_code', 'tracking', 'trackings', 'tracking_urls', 'is_printable', 'label', 'can_delete'])
        ->and($row['tracking'])->toBe('TRK123')
        ->and($row['can_delete'])->toBeTrue()
        ->and($row['label'])->toBe(['kind' => 'none', 'url' => null]);
    $shipmentRoute = [...$route, 'shipment' => $row['id']];

    $otherReturn     = mfoPalletReturn($this->customer, $this->warehouse, [mfoPallet($this->customer, $this->warehouse)]);
    $foreignShipment = StoreShipment::run($otherReturn, $manual, ['tracking' => 'TRK999']);
    mfoDelete('pallet_returns.shipments.delete', [...$route, 'shipment' => $foreignShipment->id])->assertNotFound();
    mfoGet('pallet_returns.shipments.label', $shipmentRoute)->assertNotFound();
    mfoPost('pallet_returns.shipments.print_label', $shipmentRoute)->assertUnprocessable()->assertJsonValidationErrors('printer');

    mfoViewer($this);
    mfoDelete('pallet_returns.shipments.delete', $shipmentRoute)->assertForbidden();
    mfoWorker($this);
    mfoDelete('pallet_returns.shipments.delete', $shipmentRoute)->assertOk()->assertJsonPath('data.shipments', []);
    expect(Shipment::find($row['id']))->toBeNull()
        ->and(Shipment::find($foreignShipment->id))->not->toBeNull();

    $kept = mfoPost('pallet_returns.shipments.store', $route, ['shipper_id' => $manual->id, 'tracking' => 'TRK456'])->assertOk()->json('data.shipments.0.id');
    mfoPost('pallet_returns.lines.pick', [...$route, 'palletReturnItem' => PalletReturnItem::where('pallet_return_id', $palletReturn->id)->sole()->id])->assertOk();
    mfoPost('pallet_returns.finish_picking', $route)->assertOk();
    mfoPost('pallet_returns.dispatch', $route)->assertOk()->assertJsonPath('data.shipments.0.can_delete', false);
    mfoDelete('pallet_returns.shipments.delete', [...$route, 'shipment' => $kept])->assertUnprocessable()->assertJsonValidationErrors('state');
    mfoPost('pallet_returns.shipments.store', $route, ['shipper_id' => $manual->id])->assertUnprocessable()->assertJsonValidationErrors('state');
});

test('mobile stored items list only the items on pallets of this warehouse, with their quantity there', function () {
    $otherOrganisation = mfoOtherOrganisation($this->organisation);
    $otherWarehouse    = mfoOtherWarehouse($otherOrganisation);
    $otherCustomer     = mfoCustomer(mfoFulfilment($otherOrganisation, $otherWarehouse));
    $foreignItem       = mfoStoredItem($otherCustomer);
    mfoStock(mfoPallet($otherCustomer, $otherWarehouse), $foreignItem, 9);

    mfoWorker($this);
    $countsBefore = mfoGet('stored_items.index', ['warehouse' => $this->warehouse->id])->assertOk()->json('counts');

    $storedItem = mfoStoredItem($this->customer, ['barcode' => 'MFOB'.Str::upper(Str::random(8)), 'name' => 'Gift box '.Str::random(6)]);
    $palletA    = mfoPallet($this->customer, $this->warehouse, mfoLocation($this->warehouse, 'MFA'));
    $palletB    = mfoPallet($this->customer, $this->warehouse, mfoLocation($this->warehouse, 'MFB'));
    mfoStock($palletB, $storedItem, 6);
    mfoStock($palletA, $storedItem, 4);
    $discontinuing = mfoStoredItem($this->customer);
    mfoStock(mfoPallet($this->customer, $this->warehouse), $discontinuing, 2);
    $discontinuing->update(['state' => StoredItemStateEnum::DISCONTINUING]);
    $notStored = mfoStoredItem($this->customer);

    $references = fn (array $query) => collect(mfoGet('stored_items.index', ['warehouse' => $this->warehouse->id], ['fulfilment_customer_id' => $this->customer->id, ...$query])->assertOk()->json('data'))->pluck('reference')->all();
    expect($references([]))->toBe([$storedItem->reference])
        ->and($references(['state' => 'discontinuing']))->toBe([$discontinuing->reference])
        ->and($references(['state' => 'all']))->toEqualCanonicalizing([$storedItem->reference, $discontinuing->reference])
        ->and($references(['pallet_id' => $palletB->id]))->toBe([$storedItem->reference])
        ->and($references(['q' => $storedItem->barcode]))->toBe([$storedItem->reference])
        ->and(mfoGet('stored_items.index', ['warehouse' => $this->warehouse->id], ['q' => $foreignItem->reference, 'state' => 'all'])->assertOk()->json('data'))->toBe([]);

    expect(mfoGet('stored_items.index', ['warehouse' => $this->warehouse->id], ['q' => $storedItem->reference])->assertOk()->json('data'))->toBe([[
        'id'             => $storedItem->id,
        'reference'      => $storedItem->reference,
        'name'           => $storedItem->name,
        'barcode'        => $storedItem->barcode,
        'state'          => 'active',
        'state_label'    => 'Active',
        'customer'       => ['id' => $this->customer->id, 'name' => $this->customer->customer->name],
        'quantity'       => 10,
        'number_pallets' => 2,
    ]]);

    $counts = mfoGet('stored_items.index', ['warehouse' => $this->warehouse->id])->assertOk()->json('counts');
    expect($counts['active'] - $countsBefore['active'])->toBe(1)
        ->and($counts['discontinuing'] - $countsBefore['discontinuing'])->toBe(1)
        ->and($counts['all'] - $countsBefore['all'])->toBe(2);

    $detail = mfoGet('stored_items.show', ['warehouse' => $this->warehouse->id, 'storedItem' => $storedItem->id])->assertOk()->json('data');
    expect(collect($detail['pallets'])->pluck('pallet.id')->all())->toBe([$palletA->id, $palletB->id])
        ->and($detail['pallets'][0])->toBe([
            'pallet'   => ['id' => $palletA->id, 'reference' => $palletA->reference, 'customer_reference' => $palletA->customer_reference, 'state' => 'storing', 'state_label' => 'Storing'],
            'location' => ['id' => $palletA->location_id, 'code' => $palletA->location->code],
            'quantity' => 4,
        ])
        ->and($detail['quantity'])->toBe(10);
    mfoGet('stored_items.show', ['warehouse' => $this->warehouse->id, 'storedItem' => $foreignItem->id])->assertNotFound();
    mfoGet('stored_items.show', ['warehouse' => $this->warehouse->id, 'storedItem' => $notStored->id])->assertNotFound();
});

test('mobile audits the stored items of a pallet: start or resume, count, add, add an item by barcode, remove a count and complete once', function () {
    $pallet       = mfoPallet($this->customer, $this->warehouse, mfoLocation($this->warehouse));
    $onPallet     = mfoStoredItem($this->customer);
    $stock        = mfoStock($pallet, $onPallet, 10);
    $newItem      = mfoStoredItem($this->customer, ['barcode' => 'MFOEAN'.Str::upper(Str::random(8))]);
    $candidate    = mfoStoredItem($this->customer);
    $discontinued = mfoStoredItem($this->customer, ['state' => StoredItemStateEnum::DISCONTINUED]);
    $sharedCode   = 'MFODUP'.Str::upper(Str::random(6));
    mfoStoredItem($this->customer, ['barcode' => $sharedCode]);
    mfoStoredItem($this->customer, ['barcode' => $sharedCode]);
    $foreignItem    = mfoStoredItem(mfoCustomer($this->fulfilment));
    $otherPallet    = mfoPallet($this->customer, $this->warehouse);
    $otherStock     = mfoStock($otherPallet, $candidate, 1);
    $foreignPallet  = mfoPallet($this->customer, $this->warehouse);
    $foreignPallet->update(['warehouse_id' => mfoOtherWarehouse($this->organisation)->id]);
    $palletRoute    = ['warehouse' => $this->warehouse->id, 'pallet' => $pallet->id];

    mfoViewer($this);
    mfoPost('pallets.audit', $palletRoute)->assertForbidden();

    mfoWorker($this);
    $started = mfoPost('pallets.audit', $palletRoute)->assertOk()->assertJsonPath('result.message', 'Audit started.');
    $auditId = $started->json('data.id');
    expect(array_keys($started->json('data')))->toBe(['id', 'reference', 'state', 'state_label', 'pallet', 'customer', 'lines', 'counts', 'available_actions'])
        ->and($started->json('data.lines'))->toBe([[
            'stored_item'      => ['id' => $onPallet->id, 'reference' => $onPallet->reference, 'name' => $onPallet->name, 'barcode' => $onPallet->barcode],
            'quantity'         => 10,
            'audited_quantity' => null,
            'delta_id'         => null,
            'audit_type'       => null,
            'audit_type_label' => null,
            'is_new_item'      => false,
        ]])
        ->and(mfoActionKeys($started->json('data.available_actions')))->toBe(['count', 'add_item'])
        ->and($started->json('data.pallet'))->toBe(['id' => $pallet->id, 'reference' => $pallet->reference, 'customer_reference' => $pallet->customer_reference, 'location' => ['id' => $pallet->location_id, 'code' => $pallet->location->code]])
        ->and($started->json('data.customer'))->toBe(['id' => $this->customer->id, 'name' => $this->customer->customer->name]);
    mfoPost('pallets.audit', $palletRoute)->assertOk()->assertJsonPath('result.message', 'Audit resumed.')->assertJsonPath('data.id', $auditId);

    $auditRoute = ['warehouse' => $this->warehouse->id, 'storedItemAudit' => $auditId];
    mfoGet('stored_item_audits.show', $auditRoute)->assertOk()->assertJsonPath('data.state', 'in_process');
    mfoPost('stored_item_audits.complete', $auditRoute)->assertUnprocessable()->assertJsonPath('errors.state.0', 'Count at least one item before completing the audit.');

    $counted = mfoPost('stored_item_audits.count', $auditRoute, ['stored_item_id' => $onPallet->id, 'audited_quantity' => 8])->assertOk()
        ->assertJsonPath('data.lines.0.audited_quantity', 8)
        ->assertJsonPath('data.lines.0.audit_type', 'subtraction');
    $deltaId = $counted->json('data.lines.0.delta_id');
    mfoPost('stored_item_audits.count', $auditRoute, ['stored_item_id' => $onPallet->id, 'audited_quantity' => 12])->assertOk()
        ->assertJsonPath('data.lines.0.delta_id', $deltaId)
        ->assertJsonPath('data.lines.0.audit_type', 'addition')
        ->assertJsonPath('data.lines.0.audit_type_label', 'Addition');
    $requestId = (string) Str::uuid();
    mfoPost('stored_item_audits.count', $auditRoute, ['stored_item_id' => $onPallet->id, 'add' => 3, 'client_request_id' => $requestId])->assertOk()
        ->assertJsonPath('data.lines.0.audited_quantity', 15);
    mfoPost('stored_item_audits.count', $auditRoute, ['stored_item_id' => $onPallet->id, 'add' => 3, 'client_request_id' => $requestId])->assertOk()
        ->assertJsonPath('result.status', 'nothing_changed')
        ->assertJsonPath('data.lines.0.audited_quantity', 15);
    mfoPost('stored_item_audits.count', $auditRoute, ['stored_item_id' => $onPallet->id, 'add' => 1])->assertUnprocessable()->assertJsonValidationErrors('client_request_id');

    mfoPost('stored_item_audits.count', $auditRoute, ['stored_item_id' => $foreignItem->id, 'audited_quantity' => 1])->assertUnprocessable()->assertJsonValidationErrors('stored_item_id');
    mfoPost('stored_item_audits.count', $auditRoute, ['barcode' => 'NO-SUCH-CODE', 'audited_quantity' => 1])->assertUnprocessable()->assertJsonValidationErrors('barcode');
    mfoPost('stored_item_audits.count', $auditRoute, ['barcode' => $sharedCode, 'audited_quantity' => 1])->assertUnprocessable()
        ->assertJsonPath('errors.barcode.0', "Several items of this customer have the code $sharedCode; pick the item from the list.");
    mfoPost('stored_item_audits.count', $auditRoute, ['barcode' => $discontinued->reference, 'audited_quantity' => 1])->assertUnprocessable()->assertJsonValidationErrors('barcode');

    $candidates = collect(mfoGet('stored_item_audits.stored_items', $auditRoute)->assertOk()->json('data'))->pluck('id');
    expect($candidates)->toContain($newItem->id, $candidate->id)
        ->and($candidates)->not->toContain($onPallet->id)
        ->and($candidates)->not->toContain($discontinued->id)
        ->and($candidates)->not->toContain($foreignItem->id);

    $withNew = mfoPost('stored_item_audits.count', $auditRoute, ['barcode' => Str::lower($newItem->barcode), 'audited_quantity' => 4])->assertOk();
    $newLine = collect($withNew->json('data.lines'))->firstWhere('stored_item.id', $newItem->id);
    expect($newLine)->toMatchArray(['quantity' => 0, 'audited_quantity' => 4, 'audit_type' => 'set_up', 'is_new_item' => true])
        ->and($withNew->json('data.counts'))->toBe(['lines' => 2, 'counted' => 2, 'differences' => 2])
        ->and(mfoActionKeys($withNew->json('data.available_actions')))->toBe(['count', 'add_item', 'complete'])
        ->and(collect(mfoGet('stored_item_audits.stored_items', $auditRoute, ['q' => $newItem->reference])->assertOk()->json('data'))->pluck('id'))->not->toContain($newItem->id);

    $otherDelta = $otherStock->pallet->storedItemAudits()->sole()->deltas()->sole();
    mfoDelete('stored_item_audits.deltas.delete', [...$auditRoute, 'storedItemAuditDelta' => $otherDelta->id])->assertNotFound();
    mfoDelete('stored_item_audits.deltas.delete', [...$auditRoute, 'storedItemAuditDelta' => $newLine['delta_id']])->assertOk()
        ->assertJsonPath('data.counts.lines', 1)
        ->assertJsonPath('result.message', 'Count removed.');

    mfoPost('stored_item_audits.complete', $auditRoute)->assertOk()
        ->assertJsonPath('data.state', 'completed')
        ->assertJsonPath('data.available_actions', []);
    expect((float) $stock->refresh()->quantity)->toBe(15.0);
    mfoPost('stored_item_audits.complete', $auditRoute)->assertUnprocessable()->assertJsonPath('errors.state.0', 'This audit is already completed.');
    mfoPost('stored_item_audits.count', $auditRoute, ['stored_item_id' => $onPallet->id, 'audited_quantity' => 1])->assertUnprocessable()->assertJsonValidationErrors('state');

    $pallet->update(['status' => PalletStatusEnum::RETURNING]);
    mfoPost('pallets.audit', $palletRoute)->assertUnprocessable()->assertJsonPath('errors.state.0', 'Only a pallet in storage can be audited.');
    mfoPost('pallets.audit', ['warehouse' => $this->warehouse->id, 'pallet' => $foreignPallet->id])->assertNotFound();
});

test('a scan never picks a line waiting for customer service, and says why without touching the picked quantity', function () {
    $storedItem   = mfoStoredItem($this->customer);
    $stock        = mfoStock(mfoPallet($this->customer, $this->warehouse, mfoLocation($this->warehouse)), $storedItem, 8);
    $palletReturn = mfoItemsReturn($this->customer, $this->warehouse, [[$stock, 3]]);
    $worker       = mfoWorker($this);
    mfoPost('pallet_returns.start_picking', mfoReturnRoute($this->warehouse, $palletReturn))->assertOk();
    $line = PalletReturnItem::where('pallet_return_id', $palletReturn->id)->sole();
    $line->update(['has_waiting_crm' => true]);

    $outcome = PickPalletReturnItemByScan::make()->handle($palletReturn->refresh(), $worker, ['barcode' => $storedItem->reference]);

    expect($outcome['status'])->toBe('not_found')
        ->and($outcome['message'])->toBe(PickPalletReturnItemByScan::make()->blockedReason($line->refresh()))
        ->and($outcome['message'])->toContain('waiting for customer service')
        ->and((float) $line->refresh()->quantity_picked)->toBe(0.0);

    $line->update(['has_waiting_crm' => false]);
    expect(PickPalletReturnItemByScan::make()->blockedReason($line->refresh()))->toBeNull()
        ->and(PickPalletReturnItemByScan::make()->handle($palletReturn->refresh(), $worker, ['barcode' => $storedItem->reference])['status'])->toBe('picked')
        ->and((float) $line->refresh()->quantity_picked)->toBe(1.0);
});

test('undoing a stored item pick puts a returned pallet stored item with stock back to active', function () {
    $stock        = mfoStock(mfoPallet($this->customer, $this->warehouse, mfoLocation($this->warehouse)), mfoStoredItem($this->customer), 8);
    $palletReturn = mfoItemsReturn($this->customer, $this->warehouse, [[$stock, 3]]);
    mfoWorker($this);
    mfoPost('pallet_returns.start_picking', mfoReturnRoute($this->warehouse, $palletReturn))->assertOk();
    $route = mfoReturnRoute($this->warehouse, $palletReturn, ['palletReturnItem' => PalletReturnItem::where('pallet_return_id', $palletReturn->id)->sole()->id]);

    mfoPost('pallet_returns.lines.pick', $route, ['quantity' => 3, 'client_request_id' => (string) Str::uuid()])->assertOk();
    $stock->refresh()->update(['state' => PalletStoredItemStateEnum::RETURNED]);

    mfoPost('pallet_returns.lines.undo', $route)->assertOk();
    expect($stock->refresh()->state)->toBe(PalletStoredItemStateEnum::ACTIVE)
        ->and((float) $stock->quantity)->toBe(8.0);
});

test('a shipment can only be detached from the pallet return it belongs to', function () {
    $shipper      = StoreShipper::make()->action($this->organisation, ['code' => 'MFD'.Str::upper(Str::random(4)), 'name' => 'Detach', 'trade_as' => 'detach']);
    $palletReturn = mfoPalletReturn($this->customer, $this->warehouse, [mfoPallet($this->customer, $this->warehouse)]);
    $otherReturn  = mfoPalletReturn($this->customer, $this->warehouse, [mfoPallet($this->customer, $this->warehouse)]);
    $own          = StoreShipment::run($palletReturn, $shipper, ['tracking' => 'DET1']);
    $foreign      = StoreShipment::run($otherReturn, $shipper, ['tracking' => 'DET2']);
    $detach       = fn (Shipment $shipment) => fn () => DetachShipmentFromPalletReturn::make()->handle($palletReturn->refresh(), $shipment);

    expect($detach($foreign))->toThrow(ValidationException::class, 'This shipment does not belong to this return.')
        ->and(Shipment::find($foreign->id))->not->toBeNull();

    DetachShipmentFromPalletReturn::make()->handle($palletReturn->refresh(), $own);
    expect(Shipment::find($own->id))->toBeNull()
        ->and(Shipment::find($foreign->id))->not->toBeNull();
});
