<?php

use App\Transfers\Aurora\History\Parsers\ParseDeliveryNoteHistory;
use App\Transfers\Aurora\History\Parsers\ParseOrderHistory;

function makeHistoryRow(array $overrides = []): object
{
    return (object) array_merge([
        'History Key' => 1,
        'History Date' => '2020-01-01',
        'Direct Object' => 'Order',
        'Direct Object Key' => 1,
        'Indirect Object' => null,
        'Action' => 'created',
        'History Abstract' => '',
        'History Details' => '',
        'Subject' => null,
        'Subject Key' => null,
    ], $overrides);
}

test('order created classifies as created', function () {
    $row = makeHistoryRow(['Action' => 'created', 'History Abstract' => 'Order Created']);

    expect(ParseOrderHistory::classify($row))->toBe(['handling' => 'import', 'event' => 'created', 'field' => null]);

    $values = ParseOrderHistory::extractValues($row, 'created', null);
    expect($values)->toBe(['old_values' => [], 'new_values' => [], 'data' => []]);
});

test('order state transitions map to canonical tokens', function (string $abstract, string $token) {
    $row = makeHistoryRow(['Action' => 'edited', 'History Abstract' => $abstract]);

    $classification = ParseOrderHistory::classify($row);
    expect($classification)->toBe(['handling' => 'import', 'event' => 'state_change', 'field' => null]);

    $values = ParseOrderHistory::extractValues($row, 'state_change', null);
    expect($values['new_values'])->toBe(['state' => $token]);
    expect($values['data']['source_abstract'])->toBe($abstract);
})->with([
    ['Order submitted', 'submitted'],
    ['Order dispatched', 'dispatched'],
    ['Order invoiced', 'invoiced'],
    ['Order cancelled', 'cancelled'],
    ['Order packed and closed', 'packed'],
    ['send to warehouse', 'sent_to_warehouse'],
]);

test('order item added with quantity extracts product data', function () {
    $abstract = '3 <span onclick="change_view(\'products/0/456\')">SKU-456</span> added';
    $row = makeHistoryRow(['Action' => 'edited', 'History Abstract' => $abstract]);

    $classification = ParseOrderHistory::classify($row);
    expect($classification)->toBe(['handling' => 'import', 'event' => 'item_added', 'field' => null]);

    $values = ParseOrderHistory::extractValues($row, 'item_added', null);
    expect($values['data'])->toBe([
        'product_source_id' => '456',
        'product_code' => 'SKU-456',
        'quantity' => '3',
    ]);
});

test('order item removed without quantity extracts product data', function () {
    $abstract = '<span onclick="change_view(\'products/0/789\')">SKU-789</span> removed';
    $row = makeHistoryRow(['Action' => 'edited', 'History Abstract' => $abstract]);

    $classification = ParseOrderHistory::classify($row);
    expect($classification)->toBe(['handling' => 'import', 'event' => 'item_removed', 'field' => null]);

    $values = ParseOrderHistory::extractValues($row, 'item_removed', null);
    expect($values['data'])->toBe([
        'product_source_id' => '789',
        'product_code' => 'SKU-789',
        'quantity' => null,
    ]);
});

test('order abstract with updated by customer sets flag', function () {
    $abstract = 'Order submitted. Updated by customer';
    $row = makeHistoryRow(['Action' => 'edited', 'History Abstract' => $abstract]);

    $values = ParseOrderHistory::extractValues($row, 'state_change', null);
    expect($values['data']['by_customer'])->toBeTrue();
});

test('order unrecognised null indirect object abstract is skipped', function () {
    $row = makeHistoryRow(['Action' => 'edited', 'History Abstract' => 'Something unrelated happened']);

    expect(ParseOrderHistory::classify($row))->toBe(['handling' => 'skip', 'event' => null, 'field' => null]);
});

test('order named field edit maps and extracts via table', function () {
    $row = makeHistoryRow([
        'Action' => 'edited',
        'Indirect Object' => 'Order Sticky Note',
        'History Details' => '<tr><td>Old value</td><td>foo</td></tr><tr><td>New value</td><td>bar</td></tr>',
    ]);

    $classification = ParseOrderHistory::classify($row);
    expect($classification)->toBe(['handling' => 'import', 'event' => 'updated', 'field' => 'notes']);

    $values = ParseOrderHistory::extractValues($row, 'updated', 'notes');
    expect($values['old_values'])->toBe(['notes' => 'foo']);
    expect($values['new_values'])->toBe(['notes' => 'bar']);
});

test('order payment field indirect object is skipped', function () {
    $row = makeHistoryRow(['Action' => 'edited', 'Indirect Object' => 'Order Payment Method']);

    expect(ParseOrderHistory::classify($row))->toBe(['handling' => 'skip', 'event' => null, 'field' => null]);
});

test('order bookkeeping field indirect object is skipped', function () {
    $row = makeHistoryRow(['Action' => 'edited', 'Indirect Object' => 'Order Number Items']);

    expect(ParseOrderHistory::classify($row))->toBe(['handling' => 'skip', 'event' => null, 'field' => null]);
});

test('order unknown indirect object is skipped', function () {
    $row = makeHistoryRow(['Action' => 'edited', 'Indirect Object' => 'Order Mystery Field']);

    expect(ParseOrderHistory::classify($row))->toBe(['handling' => 'skip', 'event' => null, 'field' => null]);
});

test('order other action is skipped', function () {
    $row = makeHistoryRow(['Action' => 'deleted']);

    expect(ParseOrderHistory::classify($row))->toBe(['handling' => 'skip', 'event' => null, 'field' => null]);
});

test('delivery note created classifies as created', function () {
    $row = makeHistoryRow(['Direct Object' => 'Delivery Note', 'Action' => 'created', 'History Abstract' => 'Delivery Note Created']);

    expect(ParseDeliveryNoteHistory::classify($row))->toBe(['handling' => 'import', 'event' => 'created', 'field' => null]);

    $values = ParseDeliveryNoteHistory::extractValues($row, 'created', null);
    expect($values)->toBe(['old_values' => [], 'new_values' => [], 'data' => []]);
});

test('delivery note state transitions map to canonical tokens', function (string $abstract, string $token) {
    $row = makeHistoryRow(['Direct Object' => 'Delivery Note', 'Action' => 'edited', 'History Abstract' => $abstract]);

    $classification = ParseDeliveryNoteHistory::classify($row);
    expect($classification)->toBe(['handling' => 'import', 'event' => 'state_change', 'field' => null]);

    $values = ParseDeliveryNoteHistory::extractValues($row, 'state_change', null);
    expect($values['new_values'])->toBe(['state' => $token]);
    expect($values['data']['source_abstract'])->toBe($abstract);
})->with([
    ['Delivery note approved for dispatch', 'approved'],
    ['Delivery note packed and closed', 'packed'],
    ['Delivery note dispatched', 'dispatched'],
    ['Delivery note cancelled', 'cancelled'],
    ['Delivery note opened', 'opened'],
]);

test('delivery note undispatched with reason extracts state and reason', function () {
    $abstract = 'Delivery note un dispatched. Wrong address given';
    $row = makeHistoryRow(['Direct Object' => 'Delivery Note', 'Action' => 'edited', 'History Abstract' => $abstract]);

    $classification = ParseDeliveryNoteHistory::classify($row);
    expect($classification)->toBe(['handling' => 'import', 'event' => 'state_change', 'field' => null]);

    $values = ParseDeliveryNoteHistory::extractValues($row, 'state_change', null);
    expect($values['new_values'])->toBe(['state' => 'undispatched']);
    expect($values['data']['reason'])->toBe('Wrong address given');
});

test('delivery note replacement variant sets replacement flag', function () {
    $abstract = 'Replacement note opened';
    $row = makeHistoryRow(['Direct Object' => 'Delivery Note', 'Action' => 'edited', 'History Abstract' => $abstract]);

    $classification = ParseDeliveryNoteHistory::classify($row);
    expect($classification)->toBe(['handling' => 'import', 'event' => 'state_change', 'field' => null]);

    $values = ParseDeliveryNoteHistory::extractValues($row, 'state_change', null);
    expect($values['new_values'])->toBe(['state' => 'opened']);
    expect($values['data']['replacement'])->toBeTrue();
});

test('delivery note bare replacement note is skipped', function () {
    $row = makeHistoryRow(['Direct Object' => 'Delivery Note', 'Action' => 'edited', 'History Abstract' => 'Replacement note']);

    expect(ParseDeliveryNoteHistory::classify($row))->toBe(['handling' => 'skip', 'event' => null, 'field' => null]);
});

test('delivery note address edit maps and extracts adr address', function () {
    $oldAddress = '<div class="adr"><span class="street-address">1 Old St</span></div>';
    $newAddress = '<div class="adr"><span class="street-address">2 New St</span></div>';

    $row = makeHistoryRow([
        'Direct Object' => 'Delivery Note',
        'Action' => 'edited',
        'Indirect Object' => 'Delivery Note Address',
        'History Details' => "<tr><td>Old value</td><td>{$oldAddress}</td></tr><tr><td>New value</td><td>{$newAddress}</td></tr>",
    ]);

    $classification = ParseDeliveryNoteHistory::classify($row);
    expect($classification)->toBe(['handling' => 'import', 'event' => 'updated', 'field' => 'delivery_address']);

    $values = ParseDeliveryNoteHistory::extractValues($row, 'updated', 'delivery_address');
    expect($values['old_values']['delivery_address']['address_line_1'])->toBe('1 Old St');
    expect($values['new_values']['delivery_address']['address_line_1'])->toBe('2 New St');
});

test('delivery note unknown null indirect object abstract is skipped', function () {
    $row = makeHistoryRow(['Direct Object' => 'Delivery Note', 'Action' => 'edited', 'History Abstract' => 'Something else entirely']);

    expect(ParseDeliveryNoteHistory::classify($row))->toBe(['handling' => 'skip', 'event' => null, 'field' => null]);
});

test('delivery note other indirect object is skipped', function () {
    $row = makeHistoryRow(['Direct Object' => 'Delivery Note', 'Action' => 'edited', 'Indirect Object' => 'Delivery Note Mystery']);

    expect(ParseDeliveryNoteHistory::classify($row))->toBe(['handling' => 'skip', 'event' => null, 'field' => null]);
});
