<?php

use App\Transfers\Aurora\History\Parsers\ParseProductHistory;

function productRow(array $overrides = []): object
{
    return (object) array_merge([
        'History Key' => 1,
        'History Date' => '2024-01-01',
        'Direct Object' => 'Product',
        'Direct Object Key' => 1,
        'Indirect Object' => '',
        'Action' => '',
        'History Abstract' => '',
        'History Details' => '',
    ], $overrides);
}

it('classifies a named product creation', function () {
    $row = productRow(['Action' => 'created', 'History Abstract' => 'Blue Widget product created']);

    expect(ParseProductHistory::classify($row))->toBe(['handling' => 'import', 'event' => 'created', 'field' => null]);

    $values = ParseProductHistory::extractValues($row, 'created', null);
    expect($values['new_values'])->toBe(['name' => 'Blue Widget']);
});

it('classifies a bare code product creation as code not name', function () {
    $row = productRow(['Action' => 'created', 'History Abstract' => 'AB123 product created']);

    $values = ParseProductHistory::extractValues($row, 'created', null);
    expect($values['new_values'])->toBe(['code' => 'AB123']);
});

it('handles localized creation abstracts', function () {
    $row = productRow(['Action' => 'created', 'History Abstract' => 'Produkt Vecka bol vytvorený']);

    $classification = ParseProductHistory::classify($row);
    expect($classification['handling'])->toBe('import');

    $values = ParseProductHistory::extractValues($row, 'created', null);
    expect($values['new_values'])->toBe(['code' => 'Vecka']);
});

it('captures the upload source id from a created abstract', function () {
    $row = productRow([
        'Action' => 'created',
        'History Abstract' => "Blue Widget product created change_view('upload/482')",
    ]);

    $values = ParseProductHistory::extractValues($row, 'created', null);
    expect($values['data']['upload_source_id'])->toBe('482');
});

it('marks bare product created with no details as name unavailable', function () {
    $row = productRow(['Action' => 'created', 'History Abstract' => 'Product Created']);

    $classification = ParseProductHistory::classify($row);
    expect($classification)->toBe(['handling' => 'import', 'event' => 'created', 'field' => null]);

    $values = ParseProductHistory::extractValues($row, 'created', null);
    expect($values)->toBe(['old_values' => [], 'new_values' => [], 'data' => ['name_unavailable' => true]]);
});

it('classifies the lowercase edit association branch', function () {
    $row = productRow(['Action' => 'edit', 'History Abstract' => 'Product associated with Partner Ltd']);

    expect(ParseProductHistory::classify($row))->toBe(['handling' => 'import', 'event' => 'associated', 'field' => null]);

    $values = ParseProductHistory::extractValues($row, 'associated', null);
    expect($values['data']['association'])->toBe('Product associated with Partner Ltd');
});

it('classifies the lowercase edit parts deleted branch', function () {
    $row = productRow(['Action' => 'edit', 'History Abstract' => "Product's parts deleted"]);

    expect(ParseProductHistory::classify($row))->toBe(['handling' => 'import', 'event' => 'updated', 'field' => 'parts']);

    $values = ParseProductHistory::extractValues($row, 'updated', 'parts');
    expect($values)->toBe(['old_values' => ['parts' => ''], 'new_values' => ['parts' => ''], 'data' => ['note' => 'parts deleted']]);
});

it('skips unrecognised lowercase edit abstracts', function () {
    $row = productRow(['Action' => 'edit', 'History Abstract' => 'Something else entirely']);

    expect(ParseProductHistory::classify($row))->toBe(['handling' => 'skip', 'event' => null, 'field' => null]);
});

it('imports known edited indirect objects', function ($indirectObject, $field) {
    $row = productRow(['Action' => 'edited', 'Indirect Object' => $indirectObject]);

    expect(ParseProductHistory::classify($row))->toBe(['handling' => 'import', 'event' => 'updated', 'field' => $field]);
})->with([
    ['Product Price', 'price'],
    ['Product RRP', 'rrp'],
    ['Product Name', 'name'],
    ['Product Code', 'code'],
    ['Product Status', 'state'],
    ['Product Unit Dimensions', 'unit_dimensions'],
    ['Product Unit Weight', 'unit_weight'],
    ['Product Materials', 'materials'],
    ['Product CPNP Number', 'cpnp_number'],
    ['Product Barcode Number', 'barcode'],
    ['Product UFI', 'ufi'],
    ['Product Next Supplier Shipment', 'next_supplier_shipment'],
]);

it('skips known non-importable edited indirect objects', function ($indirectObject) {
    $row = productRow(['Action' => 'edited', 'Indirect Object' => $indirectObject]);

    expect(ParseProductHistory::classify($row))->toBe(['handling' => 'skip', 'event' => null, 'field' => null]);
})->with([
    ['Product Web Configuration'],
    ['Product Video'],
    ['Product Package Weight Display'],
    ['Product XHTML Package Weight'],
    ['Some Unknown Field'],
]);

it('skips edited rows with a null indirect object', function () {
    $row = productRow(['Action' => 'edited', 'Indirect Object' => '']);

    expect(ParseProductHistory::classify($row))->toBe(['handling' => 'skip', 'event' => null, 'field' => null]);
});

it('extracts price with margin and negative margin flag', function () {
    $row = productRow([
        'Action' => 'edited',
        'Indirect Object' => 'Product Price',
        'History Details' => '<div class="field tr"><div>Old value:</div><div>£40.00 (£2.00/piece) <span class="error">margin -5.0%</span></div></div>'
            .'<div class="field tr"><div>New value:</div><div>£45.00 (£2.25/piece) <span class="normal">margin 12.3%</span></div></div>',
    ]);

    $values = ParseProductHistory::extractValues($row, 'updated', 'price');

    expect($values['old_values']['price'])->toBe('40.00')
        ->and($values['new_values']['price'])->toBe('45.00')
        ->and($values['data']['price_margin'])->toBe('12.3')
        ->and($values['data']['negative_margin'])->toBeTrue();
});

it('maps localized product status values', function ($raw, $expected) {
    $row = productRow([
        'Action' => 'edited',
        'Indirect Object' => 'Product Status',
        'History Details' => '<div class="field tr"><div>New value:</div><div>'.$raw.'</div></div>',
    ]);

    $values = ParseProductHistory::extractValues($row, 'updated', 'state');
    expect($values['new_values']['state'])->toBe($expected);
})->with([
    ['InProcess', \App\Enums\Catalogue\Product\ProductStateEnum::IN_PROCESS->value],
    ['Aktiv', \App\Enums\Catalogue\Product\ProductStateEnum::ACTIVE->value],
    ['Vyradený', \App\Enums\Catalogue\Product\ProductStateEnum::DISCONTINUED->value],
    ['aus dem Sortiment genommen', \App\Enums\Catalogue\Product\ProductStateEnum::DISCONTINUED->value],
]);

it('keeps unknown status raw and flags it unmapped', function () {
    $row = productRow([
        'Action' => 'edited',
        'Indirect Object' => 'Product Status',
        'History Details' => '<div class="field tr"><div>New value:</div><div>Mystery</div></div>',
    ]);

    $values = ParseProductHistory::extractValues($row, 'updated', 'state');

    expect($values['new_values']['state'])->toBe('Mystery')
        ->and($values['data']['unmapped_state'])->toBeTrue();
});

it('falls back to a plain sentence when there is no table', function () {
    $row = productRow([
        'Action' => 'edited',
        'Indirect Object' => 'Product Materials',
        'History Details' => 'Materials changed from "Plastic" to "Metal"',
    ]);

    $values = ParseProductHistory::extractValues($row, 'updated', 'materials');

    expect($values['old_values']['materials'])->toBe('Plastic')
        ->and($values['new_values']['materials'])->toBe('Metal');
});

it('falls back to the name changed abstract form', function () {
    $row = productRow([
        'Action' => 'edited',
        'Indirect Object' => 'Product Name',
        'History Abstract' => 'Product Name Changed (New Name)',
        'History Details' => '',
    ]);

    $values = ParseProductHistory::extractValues($row, 'updated', 'name');

    expect($values['new_values']['name'])->toBe('New Name')
        ->and($values['old_values']['name'])->toBe('');
});
