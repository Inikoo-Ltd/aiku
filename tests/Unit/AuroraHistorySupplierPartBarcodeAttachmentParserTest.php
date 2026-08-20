<?php

use App\Transfers\Aurora\History\Parsers\ParseAttachmentHistory;
use App\Transfers\Aurora\History\Parsers\ParseBarcodeHistory;
use App\Transfers\Aurora\History\Parsers\ParseSupplierPartHistory;

function historyRow(array $overrides = []): object
{
    return (object) array_merge([
        'History Key' => 1,
        'History Date' => '2024-01-01',
        'Direct Object' => '',
        'Direct Object Key' => 1,
        'Indirect Object' => '',
        'Action' => '',
        'History Abstract' => '',
        'History Details' => '',
    ], $overrides);
}

it('imports supplier part created and deleted', function () {
    $created = historyRow(['Action' => 'created']);
    expect(ParseSupplierPartHistory::classify($created))->toBe(['handling' => 'import', 'event' => 'created', 'field' => null]);

    $deleted = historyRow(['Action' => 'deleted']);
    expect(ParseSupplierPartHistory::classify($deleted))->toBe(['handling' => 'import', 'event' => 'deleted', 'field' => null]);
});

it('imports known supplier part edited indirect objects', function ($indirectObject, $field) {
    $row = historyRow(['Action' => 'edited', 'Indirect Object' => $indirectObject]);

    expect(ParseSupplierPartHistory::classify($row))->toBe(['handling' => 'import', 'event' => 'updated', 'field' => $field]);
})->with([
    ['Supplier Part Unit Cost', 'cost'],
    ['Supplier Part On Demand', 'on_demand'],
    ['Supplier Part Fresh', 'fresh'],
    ['Supplier Part Packages Per Carton', 'packages_per_carton'],
    ['Supplier Part Description', 'description'],
    ['Supplier Part Unit Extra Cost Percentage', 'extra_cost_percentage'],
    ['Supplier Part Reference', 'supplier_reference'],
    ['Supplier Part Carton Barcode', 'carton_barcode'],
    ['Supplier Part Currency Code', 'currency'],
    ['Supplier Part Status', 'status'],
    ['Supplier Part Sticky Note', 'notes'],
    ['Supplier Part Minimum Carton Order', 'minimum_carton_order'],
    ['Supplier Part Unit Expense', 'expense'],
    ['Supplier Part Carton CBM', 'carton_cbm'],
    ['Supplier Part Average Delivery Days', 'delivery_days'],
]);

it('skips unknown supplier part edited indirect objects', function () {
    $row = historyRow(['Action' => 'edited', 'Indirect Object' => 'Something Unknown']);

    expect(ParseSupplierPartHistory::classify($row))->toBe(['handling' => 'skip', 'event' => null, 'field' => null]);
});

it('skips supplier part edited rows with a null indirect object', function () {
    $row = historyRow(['Action' => 'edited', 'Indirect Object' => '']);

    expect(ParseSupplierPartHistory::classify($row))->toBe(['handling' => 'skip', 'event' => null, 'field' => null]);
});

it('extracts supplier part cost with per-carton amount', function () {
    $row = historyRow([
        'Action' => 'edited',
        'Indirect Object' => 'Supplier Part Unit Cost',
        'History Details' => '<div class="field tr"><div>Old value:</div><div>£40.00 (£1,800.00 per carton)</div></div>'
            .'<div class="field tr"><div>New value:</div><div>£45.00 (£2,025.00 per carton)</div></div>',
    ]);

    $values = ParseSupplierPartHistory::extractValues($row, 'updated', 'cost');

    expect($values['old_values']['cost'])->toBe('40.00')
        ->and($values['new_values']['cost'])->toBe('45.00')
        ->and($values['data']['cost_per_carton'])->toBe('2025.00');
});

it('extracts supplier part expense with per-carton amount and mojibake currency byte', function () {
    $row = historyRow([
        'Action' => 'edited',
        'Indirect Object' => 'Supplier Part Unit Expense',
        'History Details' => '<div class="field tr"><div>New value:</div><div>Â£12.50 (Â£562.50 per carton) <span class="discreet">est.</span></div></div>',
    ]);

    $values = ParseSupplierPartHistory::extractValues($row, 'updated', 'expense');

    expect($values['new_values']['expense'])->toBe('12.50')
        ->and($values['data']['expense_per_carton'])->toBe('562.50');
});

it('falls back to the abstract was-changed-to form for supplier part fields', function () {
    $row = historyRow([
        'Action' => 'edited',
        'Indirect Object' => 'Supplier Part Description',
        'History Abstract' => "Supplier Part's description was changed to Lavender Oil 5L",
        'History Details' => '',
    ]);

    $values = ParseSupplierPartHistory::extractValues($row, 'updated', 'description');

    expect($values['new_values']['description'])->toBe('Lavender Oil 5L')
        ->and($values['old_values']['description'])->toBe('');
});

it('falls back to the abstract set-as form for supplier part fields', function () {
    $row = historyRow([
        'Action' => 'edited',
        'Indirect Object' => 'Supplier Part Status',
        'History Abstract' => "Supplier Part's status set as Active",
        'History Details' => '',
    ]);

    $values = ParseSupplierPartHistory::extractValues($row, 'updated', 'status');

    expect($values['new_values']['status'])->toBe('Active');
});

it('associates a barcode with a part', function () {
    $row = historyRow([
        'Direct Object' => 'Barcode',
        'Action' => 'associated',
        'History Abstract' => 'Barcode associated with <span class="link" onclick="change_view(\'part/48467\')">OPullB-03</span>',
    ]);

    expect(ParseBarcodeHistory::classify($row))->toBe(['handling' => 'import', 'event' => 'associated', 'field' => null]);

    $values = ParseBarcodeHistory::extractValues($row, 'associated', null);

    expect($values['new_values']['part'])->toBe('OPullB-03')
        ->and($values['data']['part_source_id'])->toBe('48467')
        ->and($values['data']['part_code'])->toBe('OPullB-03');
});

it('disassociates a barcode from a part', function () {
    $row = historyRow([
        'Direct Object' => 'Barcode',
        'Action' => 'disassociate',
        'History Abstract' => 'Barcode disassociated from <span class="link" onclick="change_view(\'part/48467\')">OPullB-03</span>',
    ]);

    expect(ParseBarcodeHistory::classify($row))->toBe(['handling' => 'import', 'event' => 'disassociated', 'field' => null]);

    $values = ParseBarcodeHistory::extractValues($row, 'disassociated', null);

    expect($values['old_values']['part'])->toBe('OPullB-03');
});

it('skips barcode rows with no parseable part link', function () {
    $row = historyRow([
        'Direct Object' => 'Barcode',
        'Action' => 'associated',
        'History Abstract' => 'Barcode associated with something unparseable',
    ]);

    expect(ParseBarcodeHistory::classify($row))->toBe(['handling' => 'skip', 'event' => null, 'field' => null]);
});

it('skips barcode created deleted and edited actions', function ($action) {
    $row = historyRow(['Direct Object' => 'Barcode', 'Action' => $action]);

    expect(ParseBarcodeHistory::classify($row))->toBe(['handling' => 'skip', 'event' => null, 'field' => null]);
})->with(['created', 'deleted', 'edited']);

it('imports the MSDS attachment upload example', function () {
    $row = historyRow([
        'Direct Object' => 'Attachment',
        'Action' => 'associated',
        'Indirect Object' => 'Part',
        'History Abstract' => 'MSDS Attachment uploaded; <a href="file.php?bid=218">Palmarosa Oil AW MSDS.pdf</a> (1.1 MB)',
    ]);

    expect(ParseAttachmentHistory::classify($row))->toBe(['handling' => 'import', 'event' => 'attached', 'field' => null]);

    $values = ParseAttachmentHistory::extractValues($row, 'attached', null);

    expect($values['new_values']['filename'])->toBe('Palmarosa Oil AW MSDS.pdf')
        ->and($values['data']['size'])->toBe('1.1 MB')
        ->and($values['data']['label'])->toBe('MSDS');
});

it('skips attachment associations targeting a customer', function () {
    $row = historyRow([
        'Direct Object' => 'Attachment',
        'Action' => 'associated',
        'Indirect Object' => 'Customer',
        'History Abstract' => 'Attachment uploaded; <a href="file.php?bid=1">note.pdf</a> (10 KB)',
    ]);

    expect(ParseAttachmentHistory::classify($row))->toBe(['handling' => 'skip', 'event' => null, 'field' => null]);
});

it('skips attachment edited metadata rows', function () {
    $row = historyRow([
        'Direct Object' => 'Attachment',
        'Action' => 'edited',
        'Indirect Object' => 'Part',
    ]);

    expect(ParseAttachmentHistory::classify($row))->toBe(['handling' => 'skip', 'event' => null, 'field' => null]);
});
