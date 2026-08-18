<?php

namespace Tests\Unit;

use App\Transfers\Aurora\History\Parsers\ParseInvoiceHistory;
use App\Transfers\Aurora\History\Parsers\ParsePurchaseOrderHistory;
use App\Transfers\Aurora\History\Parsers\ParseSupplierDeliveryHistory;

function auroraHistoryRow(array $attributes): object
{
    return (object) array_merge([
        'Action' => null,
        'Direct Object' => null,
        'Indirect Object' => null,
        'History Abstract' => '',
        'History Details' => '',
    ], $attributes);
}

test('invoice created extracts reference', function () {
    $row = auroraHistoryRow(['Action' => 'created', 'History Abstract' => 'Invoice INV-100 created']);
    $classification = ParseInvoiceHistory::classify($row);
    expect($classification)->toBe(['handling' => 'import', 'event' => 'created', 'field' => null]);

    $values = ParseInvoiceHistory::extractValues($row, 'created', null);
    expect($values['new_values'])->toBe(['reference' => 'INV-100']);
    expect($values['data'])->toBe([]);
});

test('refund created marks is_refund', function () {
    $row = auroraHistoryRow(['Action' => 'created', 'History Abstract' => 'Refund INV-101 created']);
    $classification = ParseInvoiceHistory::classify($row);
    expect($classification['event'])->toBe('created');

    $values = ParseInvoiceHistory::extractValues($row, 'created', null);
    expect($values['new_values'])->toBe(['reference' => 'INV-101']);
    expect($values['data'])->toBe(['is_refund' => true]);
});

test('invoice deleted classifies as deleted', function () {
    $row = auroraHistoryRow(['Action' => 'deleted']);
    expect(ParseInvoiceHistory::classify($row))->toBe(['handling' => 'import', 'event' => 'deleted', 'field' => null]);
});

test('invoice associated is skipped', function () {
    $row = auroraHistoryRow(['Action' => 'associated']);
    expect(ParseInvoiceHistory::classify($row)['handling'])->toBe('skip');
});

test('invoice source key edit is skipped', function () {
    $row = auroraHistoryRow(['Action' => 'edited', 'Indirect Object' => 'Invoice Source Key']);
    expect(ParseInvoiceHistory::classify($row)['handling'])->toBe('skip');
});

test('invoice message edit extracts via table', function () {
    $row = auroraHistoryRow([
        'Action' => 'edited',
        'Indirect Object' => 'Invoice Message',
        'History Details' => '<table><tr><td>Old value</td><td>Hello</td></tr><tr><td>New value</td><td>Bye</td></tr></table>',
    ]);
    $classification = ParseInvoiceHistory::classify($row);
    expect($classification)->toBe(['handling' => 'import', 'event' => 'updated', 'field' => 'message']);

    $values = ParseInvoiceHistory::extractValues($row, 'updated', 'message');
    expect($values['old_values'])->toBe(['message' => 'Hello']);
    expect($values['new_values'])->toBe(['message' => 'Bye']);
});

test('invoice public id edit falls back to abstract sentence', function () {
    $row = auroraHistoryRow([
        'Action' => 'edited',
        'Indirect Object' => 'Invoice Public ID',
        'History Abstract' => "Invoice's Public ID was changed to INV-200",
    ]);

    $values = ParseInvoiceHistory::extractValues($row, 'updated', 'reference');
    expect($values['new_values'])->toBe(['reference' => 'INV-200']);
});

test('invoice edit with both sides null returns empty arrays', function () {
    $row = auroraHistoryRow(['Action' => 'edited', 'Indirect Object' => 'Invoice Date']);
    $values = ParseInvoiceHistory::extractValues($row, 'updated', 'date');
    expect($values)->toBe(['old_values' => [], 'new_values' => [], 'data' => []]);
});

test('purchase order created classifies as created', function () {
    $row = auroraHistoryRow(['Action' => 'created', 'Direct Object' => 'Purchase Order', 'History Abstract' => 'Purchase order created']);
    expect(ParsePurchaseOrderHistory::classify($row))->toBe(['handling' => 'import', 'event' => 'created', 'field' => null]);
});

test('purchase order deleted classifies as deleted', function () {
    $row = auroraHistoryRow(['Action' => 'deleted', 'Direct Object' => 'Purchase Order']);
    expect(ParsePurchaseOrderHistory::classify($row))->toBe(['handling' => 'import', 'event' => 'deleted', 'field' => null]);
});

test('agent supplier purchase order created is imported', function () {
    $row = auroraHistoryRow([
        'Action' => 'created',
        'Direct Object' => 'Agent Supplier Purchase Order',
        'History Abstract' => 'Agent supplier purchase order created',
    ]);
    expect(ParsePurchaseOrderHistory::classify($row))->toBe(['handling' => 'import', 'event' => 'created', 'field' => null]);
});

test('agent supplier purchase order other actions are skipped', function () {
    $row = auroraHistoryRow([
        'Action' => 'edited',
        'Direct Object' => 'Agent Supplier Purchase Order',
        'History Abstract' => 'Agent supplier purchase order submitted',
    ]);
    expect(ParsePurchaseOrderHistory::classify($row)['handling'])->toBe('skip');
});

test('purchase order state tokens are classified', function (string $abstract, string $token) {
    $row = auroraHistoryRow(['Action' => 'edited', 'Direct Object' => 'Purchase Order', 'History Abstract' => $abstract]);
    $classification = ParsePurchaseOrderHistory::classify($row);
    expect($classification)->toBe(['handling' => 'import', 'event' => 'state_change', 'field' => null]);

    $values = ParsePurchaseOrderHistory::extractValues($row, 'state_change', null);
    expect($values['new_values'])->toBe(['state' => $token]);
    expect($values['data'])->toBe(['source_abstract' => $abstract]);
})->with([
    ['Purchase order submitted', 'submitted'],
    ['Purchase order confirmed by supplier', 'confirmed'],
    ['Purchase order confirmed', 'confirmed'],
    ['Purchase order cancelled', 'cancelled'],
    ['Cancel confirmation', 'cancel_confirmation'],
    ['Purchase order send back to planing', 'back_to_planning'],
    ['Purchase order send back to process', 'back_to_process'],
    ['Sent back to queue', 'back_to_queue'],
    ['Job order start manufacturing', 'manufacturing'],
    ['Job order manufactured', 'manufactured'],
    ['Job order quality control done', 'quality_control_done'],
    ['Job order set back as manufacturing', 'back_to_manufacturing'],
    ['Quality control for Job order cancelled', 'quality_control_cancelled'],
]);

test('purchase order reference edit extracts via table', function () {
    $row = auroraHistoryRow([
        'Action' => 'edited',
        'Direct Object' => 'Purchase Order',
        'Indirect Object' => 'Purchase Order Public ID',
        'History Details' => '<table><tr><td>Old value</td><td>PO-1</td></tr><tr><td>New value</td><td>PO-2</td></tr></table>',
    ]);
    $classification = ParsePurchaseOrderHistory::classify($row);
    expect($classification)->toBe(['handling' => 'import', 'event' => 'updated', 'field' => 'reference']);

    $values = ParsePurchaseOrderHistory::extractValues($row, 'updated', 'reference');
    expect($values['old_values'])->toBe(['reference' => 'PO-1']);
    expect($values['new_values'])->toBe(['reference' => 'PO-2']);
});

test('purchase order sk verejny id edit maps to reference', function () {
    $row = auroraHistoryRow([
        'Action' => 'edited',
        'Direct Object' => 'Purchase Order',
        'Indirect Object' => 'verejn. Id',
        'History Abstract' => "Purchase Order's verejný Id was changed to PO-99",
    ]);
    $classification = ParsePurchaseOrderHistory::classify($row);
    expect($classification)->toBe(['handling' => 'import', 'event' => 'updated', 'field' => 'reference']);

    $values = ParsePurchaseOrderHistory::extractValues($row, 'updated', 'reference');
    expect($values['new_values'])->toBe(['reference' => 'PO-99']);
});

test('purchase order operator key edit is skipped', function () {
    $row = auroraHistoryRow(['Action' => 'edited', 'Direct Object' => 'Purchase Order', 'Indirect Object' => 'Operator Key']);
    expect(ParsePurchaseOrderHistory::classify($row)['handling'])->toBe('skip');
});

test('purchase order unmatched null indirect abstract is skipped', function () {
    $row = auroraHistoryRow(['Action' => 'edited', 'Direct Object' => 'Purchase Order', 'History Abstract' => 'Something unrelated happened']);
    expect(ParsePurchaseOrderHistory::classify($row)['handling'])->toBe('skip');
});

test('supplier delivery created and deleted classify', function () {
    $created = auroraHistoryRow(['Action' => 'created']);
    expect(ParseSupplierDeliveryHistory::classify($created))->toBe(['handling' => 'import', 'event' => 'created', 'field' => null]);

    $deleted = auroraHistoryRow(['Action' => 'deleted']);
    expect(ParseSupplierDeliveryHistory::classify($deleted))->toBe(['handling' => 'import', 'event' => 'deleted', 'field' => null]);
});

test('supplier delivery state tokens are classified', function (string $abstract, string $token) {
    $row = auroraHistoryRow(['Action' => 'edited', 'History Abstract' => $abstract]);
    $classification = ParseSupplierDeliveryHistory::classify($row);
    expect($classification)->toBe(['handling' => 'import', 'event' => 'state_change', 'field' => null]);

    $values = ParseSupplierDeliveryHistory::extractValues($row, 'state_change', null);
    expect($values['new_values'])->toBe(['state' => $token]);
})->with([
    ['Supplier delivery set as placed', 'placed'],
    ['Supplier delivery set as dispatched', 'dispatched'],
    ['Supplier delivery set as in process', 'in_process'],
    ['Supplier delivery set as received', 'received'],
    ['Supplier delivery set as checked', 'checked'],
    ['Supplier delivery cancelled', 'cancelled'],
    ['Setting costs', 'setting_costs'],
    ['Costing done', 'costing_done'],
    ['Redoing costing', 'redoing_costing'],
]);

test('supplier delivery invoice reference falls back to abstract sentence', function () {
    $row = auroraHistoryRow([
        'Action' => 'edited',
        'Indirect Object' => 'Supplier Delivery Invoice Public ID',
        'History Abstract' => "Supplier Delivery's Invoice number set as INV-55",
    ]);
    $classification = ParseSupplierDeliveryHistory::classify($row);
    expect($classification)->toBe(['handling' => 'import', 'event' => 'updated', 'field' => 'invoice_reference']);

    $values = ParseSupplierDeliveryHistory::extractValues($row, 'updated', 'invoice_reference');
    expect($values['new_values'])->toBe(['invoice_reference' => 'INV-55']);
});

test('supplier delivery unknown edit is skipped', function () {
    $row = auroraHistoryRow(['Action' => 'edited', 'Indirect Object' => 'Something Else']);
    expect(ParseSupplierDeliveryHistory::classify($row)['handling'])->toBe('skip');
});

test('supplier delivery edit with both sides null returns empty arrays', function () {
    $row = auroraHistoryRow(['Action' => 'edited', 'Indirect Object' => 'Supplier Delivery Dispatched Date']);
    $values = ParseSupplierDeliveryHistory::extractValues($row, 'updated', 'dispatched_date');
    expect($values)->toBe(['old_values' => [], 'new_values' => [], 'data' => []]);
});

test('charge field edit classifies and extracts values', function () {
    $row = auroraHistoryRow([
        'Action' => 'edited',
        'Direct Object' => 'Charge',
        'Indirect Object' => 'Charge Public Description',
        'History Details' => '<div class="table"><div class="field tr"><div>Time:</div><div>Mon 3 Feb 2025 10:00:00 UTC</div></div><div class="field tr"><div>User:</div><div>Ana</div></div><div class="field tr"><div>Action:</div><div>Changed</div></div><div class="field tr"><div>Old value:</div><div>Old text</div></div><div class="field tr"><div>New value:</div><div>New text</div></div><div class="field tr"><div>Charge:</div><div></div></div></div>',
    ]);

    expect(\App\Transfers\Aurora\History\Parsers\ParseChargeHistory::classify($row))
        ->toBe(['handling' => 'import', 'event' => 'updated', 'field' => 'public_description']);

    expect(\App\Transfers\Aurora\History\Parsers\ParseChargeHistory::extractValues($row, 'updated', 'public_description'))
        ->toBe(['old_values' => ['public_description' => 'Old text'], 'new_values' => ['public_description' => 'New text'], 'data' => []]);
});

test('charge unknown indirect object skips', function () {
    $row = auroraHistoryRow(['Action' => 'edited', 'Direct Object' => 'Charge', 'Indirect Object' => 'Charge Something Else']);
    expect(\App\Transfers\Aurora\History\Parsers\ParseChargeHistory::classify($row)['handling'])->toBe('skip');
});
