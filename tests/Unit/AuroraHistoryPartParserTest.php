<?php

use App\Transfers\Aurora\History\Parsers\ParsePartHistory;

function partRow(array $overrides = []): object
{
    return (object) array_merge([
        'History Key' => 1,
        'History Date' => '2024-01-01',
        'Direct Object' => 'Part',
        'Direct Object Key' => 1,
        'Indirect Object' => '',
        'Action' => '',
        'History Abstract' => '',
        'History Details' => '',
    ], $overrides);
}

it('classifies part creation as TradeUnit created', function () {
    $row = partRow(['Action' => 'created', 'History Abstract' => "SKU123 part created change_view('upload/17')"]);

    expect(ParsePartHistory::classify($row))->toBe(['handling' => 'import', 'event' => 'created', 'field' => null, 'auditable_type' => 'TradeUnit']);

    $values = ParsePartHistory::extractValues($row, 'created', null);
    expect($values['data']['upload_source_id'])->toBe('17');
});

it('classifies part deletion as TradeUnit deleted', function () {
    $row = partRow(['Action' => 'deleted', 'History Abstract' => 'Part deleted']);

    expect(ParsePartHistory::classify($row))->toBe(['handling' => 'import', 'event' => 'deleted', 'field' => null, 'auditable_type' => 'TradeUnit']);
});

it('routes a TradeUnit field to TradeUnit', function () {
    $row = partRow([
        'Action' => 'edited',
        'Indirect Object' => 'Part Reference',
        'History Details' => '<table><tr><td>Old value</td><td>ABC</td></tr><tr><td>New value</td><td>XYZ</td></tr></table>',
    ]);

    $classification = ParsePartHistory::classify($row);
    expect($classification)->toBe(['handling' => 'import', 'event' => 'updated', 'field' => 'code', 'auditable_type' => 'TradeUnit']);

    $values = ParsePartHistory::extractValues($row, 'updated', 'code');
    expect($values['old_values'])->toBe(['code' => 'ABC']);
    expect($values['new_values'])->toBe(['code' => 'XYZ']);
});

it('routes an OrgStock field to OrgStock', function () {
    $row = partRow([
        'Action' => 'edited',
        'Indirect Object' => 'Part Delivery Days',
        'History Details' => '<table><tr><td>Old value</td><td>5</td></tr><tr><td>New value</td><td>10</td></tr></table>',
    ]);

    expect(ParsePartHistory::classify($row))->toBe(['handling' => 'import', 'event' => 'updated', 'field' => 'delivery_days', 'auditable_type' => 'OrgStock']);
});

it('skips an unknown indirect object', function () {
    $row = partRow(['Action' => 'edited', 'Indirect Object' => 'Something Unmapped']);

    expect(ParsePartHistory::classify($row))->toBe(['handling' => 'skip', 'event' => null, 'field' => null, 'auditable_type' => null]);
});

it('routes main supplier set to SupplierProduct', function () {
    $row = partRow([
        'Action' => 'edited',
        'Indirect Object' => '',
        'History Abstract' => 'Part main supplier set to SUP01 (Acme Ltd)',
    ]);

    expect(ParsePartHistory::classify($row))->toBe(['handling' => 'import', 'event' => 'updated', 'field' => 'main_supplier', 'auditable_type' => 'SupplierProduct']);

    $values = ParsePartHistory::extractValues($row, 'updated', 'main_supplier');
    expect($values['new_values'])->toBe(['main_supplier' => 'SUP01']);
    expect($values['data'])->toBe(['supplier_reference' => 'Acme Ltd']);
});

it('classifies a category association', function () {
    $row = partRow([
        'Action' => 'associated',
        'Indirect Object' => 'Category Part',
        'History Abstract' => 'Part: <a href="part.php?sku=42">SKU42</a> associated with category <a href="part_category.php?id=7">CAT7</a>',
    ]);

    expect(ParsePartHistory::classify($row))->toBe(['handling' => 'import', 'event' => 'associated', 'field' => 'category', 'auditable_type' => 'TradeUnit']);

    $values = ParsePartHistory::extractValues($row, 'associated', 'category');
    expect($values['new_values'])->toBe(['category' => 'CAT7']);
    expect($values['data'])->toBe(['category_source_id' => '7', 'category_code' => 'CAT7']);
});

it('classifies a category disassociation even under associated action', function () {
    $row = partRow([
        'Action' => 'associated',
        'Indirect Object' => 'Category Part',
        'History Abstract' => 'Part: <a href="part.php?sku=42">SKU42</a> disassociated with category <a href="part_category.php?id=7">CAT7</a>',
    ]);

    expect(ParsePartHistory::classify($row))->toBe(['handling' => 'import', 'event' => 'disassociated', 'field' => 'category', 'auditable_type' => 'TradeUnit']);

    $values = ParsePartHistory::extractValues($row, 'disassociated', 'category');
    expect($values['old_values'])->toBe(['category' => 'CAT7']);
});

it('classifies a barcode association', function () {
    $row = partRow([
        'Action' => 'associated',
        'Indirect Object' => '',
        'History Abstract' => 'Barcode <span class="icon">5056422980005</span> associated',
    ]);

    expect(ParsePartHistory::classify($row))->toBe(['handling' => 'import', 'event' => 'associated', 'field' => 'barcode', 'auditable_type' => 'TradeUnit']);

    $values = ParsePartHistory::extractValues($row, 'associated', 'barcode');
    expect($values['new_values'])->toBe(['barcode' => '5056422980005']);
});

it('classifies a barcode disassociation', function () {
    $row = partRow([
        'Action' => 'disassociate',
        'Indirect Object' => '',
        'History Abstract' => 'Barcode 5056422980005 disassociated',
    ]);

    expect(ParsePartHistory::classify($row))->toBe(['handling' => 'import', 'event' => 'disassociated', 'field' => 'barcode', 'auditable_type' => 'TradeUnit']);

    $values = ParsePartHistory::extractValues($row, 'disassociated', 'barcode');
    expect($values['old_values'])->toBe(['barcode' => '5056422980005']);
});

it('extracts an era A plain sentence for state', function () {
    $row = partRow([
        'Action' => 'edited',
        'Indirect Object' => 'Part Status',
        'History Details' => 'Part Status changed from "In Use" to "Not In Use"',
    ]);

    $values = ParsePartHistory::extractValues($row, 'updated', 'state');
    expect($values['old_values'])->toBe(['state' => 'active']);
    expect($values['new_values'])->toBe(['state' => 'discontinued']);
});

it('extracts price with per-carton note and margin, flagging negative margin', function () {
    $row = partRow([
        'Action' => 'edited',
        'Indirect Object' => 'Part Unit Price',
        'History Details' => '<table><tr><td>Old value</td><td>£1,234.50 (£10.00 per carton) <span class="error">margin -5.2%</span></td></tr><tr><td>New value</td><td>£1,300.00</td></tr></table>',
    ]);

    $values = ParsePartHistory::extractValues($row, 'updated', 'price');
    expect($values['old_values'])->toBe(['price' => '1234.50']);
    expect($values['new_values'])->toBe(['price' => '1300.00']);
    expect($values['data'])->toBe(['price_margin' => '-5.2', 'negative_margin' => true]);
});

it('passes through non-legacy state values unmapped', function () {
    $row = partRow([
        'Action' => 'edited',
        'Indirect Object' => 'Part Status',
        'History Details' => '<table><tr><td>New value</td><td>Discontinuing</td></tr></table>',
    ]);

    $values = ParsePartHistory::extractValues($row, 'updated', 'state');
    expect($values['new_values'])->toBe(['state' => 'Discontinuing']);
});

it('skips unrecognised abstracts with no indirect object', function () {
    $row = partRow(['Action' => 'edited', 'Indirect Object' => '', 'History Abstract' => 'Something unrelated happened']);

    expect(ParsePartHistory::classify($row))->toBe(['handling' => 'skip', 'event' => null, 'field' => null, 'auditable_type' => null]);
});
