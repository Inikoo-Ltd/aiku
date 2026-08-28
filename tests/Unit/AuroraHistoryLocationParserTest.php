<?php

use App\Transfers\Aurora\History\Parsers\ParseLocationHistory;

function locationRow(array $attributes): object
{
    return (object) array_merge([
        'Direct Object' => 'Location',
        'Indirect Object' => '',
        'Action' => 'edited',
        'History Abstract' => '',
        'History Details' => '',
    ], $attributes);
}

it('classifies and extracts a location created via Details', function () {
    $row = locationRow([
        'Action' => 'created',
        'History Abstract' => 'Location Created',
        'History Details' => 'Location ABC-01 created',
    ]);

    $classified = ParseLocationHistory::classify($row);
    expect($classified)->toBe(['handling' => 'import', 'event' => 'created', 'field' => null]);

    $values = ParseLocationHistory::extractValues($row, 'created', null);
    expect($values['new_values'])->toBe(['code' => 'ABC-01']);
    expect($values['old_values'])->toBe([]);
});

it('extracts location created record pattern', function () {
    $row = locationRow(['Action' => 'created', 'History Abstract' => 'ABC-02 location record created']);
    $values = ParseLocationHistory::extractValues($row, 'created', null);
    expect($values['new_values'])->toBe(['code' => 'ABC-02']);
});

it('extracts location created new location added pattern', function () {
    $row = locationRow(['Action' => 'created', 'History Abstract' => 'New location ABC-03 added']);
    $values = ParseLocationHistory::extractValues($row, 'created', null);
    expect($values['new_values'])->toBe(['code' => 'ABC-03']);
});

it('extracts sk location created pattern', function () {
    $row = locationRow(['Action' => 'created', 'History Abstract' => 'Poloha ABC-04 bola vytvorená']);
    $values = ParseLocationHistory::extractValues($row, 'created', null);
    expect($values['new_values'])->toBe(['code' => 'ABC-04']);
});

it('captures upload provenance on created location', function () {
    $row = locationRow([
        'Action' => 'created',
        'History Abstract' => "New location ABC-05 added via <a onclick=\"change_view('upload/321')\">upload</a>",
    ]);
    $values = ParseLocationHistory::extractValues($row, 'created', null);
    expect($values['data'])->toBe(['upload_source_id' => '321']);
});

it('normalizes max weight with thousands separator', function () {
    $row = locationRow([
        'Indirect Object' => 'Location Max Weight',
        'History Details' => '<div class="field tr"><div>Old value:</div><div>500Kg</div></div><div class="field tr"><div>New value:</div><div>1,000Kg</div></div>',
    ]);

    $classified = ParseLocationHistory::classify($row);
    expect($classified)->toBe(['handling' => 'import', 'event' => 'updated', 'field' => 'max_weight']);

    $values = ParseLocationHistory::extractValues($row, 'updated', 'max_weight');
    expect($values['old_values'])->toBe(['max_weight' => '500']);
    expect($values['new_values'])->toBe(['max_weight' => '1000']);
});

it('normalizes max volume cubic meters', function () {
    $row = locationRow([
        'Indirect Object' => 'Location Max Volume',
        'History Details' => '<div class="field tr"><div>Old value:</div><div>1.1 cubic meters</div></div><div class="field tr"><div>New value:</div><div>2.2 cubic meters</div></div>',
    ]);

    $values = ParseLocationHistory::extractValues($row, 'updated', 'max_volume');
    expect($values['new_values'])->toBe(['max_volume' => '2.2']);
});

it('normalizes cleared max volume to empty string', function () {
    $row = locationRow([
        'Indirect Object' => 'Location Max Volume',
        'History Details' => '<div class="field tr"><div>Old value:</div><div>2.2 cubic meters</div></div><div class="field tr"><div>New value:</div><div></div></div>',
    ]);

    $values = ParseLocationHistory::extractValues($row, 'updated', 'max_volume');
    expect($values['new_values'])->toBe(['max_volume' => '']);
});

it('extracts flag label and color', function () {
    $row = locationRow([
        'Indirect Object' => 'Location Warehouse Flag Key',
        'History Details' => '<div class="field tr"><div>Old value:</div><div></div></div><div class="field tr"><div>New value:</div><div><i class="fa fa-flag red"></i> Priority</div></div>',
    ]);

    $values = ParseLocationHistory::extractValues($row, 'updated', 'flag');
    expect($values['new_values'])->toBe(['flag' => 'Priority']);
    expect($values['data'])->toBe(['flag_color' => 'red']);
});

it('skips flag updates when both old and new are empty', function () {
    $row = locationRow([
        'Indirect Object' => 'Location Warehouse Flag Key',
        'History Details' => '<div class="field tr"><div>Old value:</div><div></div></div><div class="field tr"><div>New value:</div><div></div></div>',
    ]);

    $values = ParseLocationHistory::extractValues($row, 'updated', 'flag');
    expect($values)->toBe(['old_values' => [], 'new_values' => [], 'data' => []]);
});

it('classifies location association event', function () {
    $row = locationRow([
        'Action' => 'edited',
        'Indirect Object' => '',
        'History Abstract' => "Location associated to warehouse area <span onclick=\"change_view('warehouse/1/areas/90')\">Bay A</span>",
    ]);

    $classified = ParseLocationHistory::classify($row);
    expect($classified)->toBe(['handling' => 'import', 'event' => 'associated', 'field' => null]);

    $values = ParseLocationHistory::extractValues($row, 'associated', null);
    expect($values['new_values'])->toBe(['warehouse_area' => 'Bay A']);
    expect($values['data'])->toBe(['warehouse_area_source_id' => '90', 'warehouse_area' => 'Bay A']);
});

it('classifies location moved between warehouse areas', function () {
    $row = locationRow([
        'Action' => 'edited',
        'Indirect Object' => '',
        'History Abstract' => "Location moved from to warehouse area <span onclick=\"change_view('warehouse/1/areas/10')\">Bay A</span> to <span onclick=\"change_view('warehouse/1/areas/20')\">Bay B</span>",
    ]);

    $classified = ParseLocationHistory::classify($row);
    expect($classified)->toBe(['handling' => 'import', 'event' => 'updated', 'field' => 'warehouse_area']);

    $values = ParseLocationHistory::extractValues($row, 'updated', 'warehouse_area');
    expect($values['old_values'])->toBe(['warehouse_area' => 'Bay A']);
    expect($values['new_values'])->toBe(['warehouse_area' => 'Bay B']);
});

it('skips unknown location indirect object', function () {
    $row = locationRow(['Indirect Object' => 'Location Something Else']);
    expect(ParseLocationHistory::classify($row))->toBe(['handling' => 'skip', 'event' => null, 'field' => null]);
});

it('extracts full warehouse area created details', function () {
    $row = locationRow([
        'Direct Object' => 'Warehouse Area',
        'Action' => 'created',
        'History Abstract' => 'Warehouse area <span class="italic">Bay A</span> (<span title="Code" class="strong">BAY-A</span>) created',
    ]);

    $values = ParseLocationHistory::extractValues($row, 'created', null);
    expect($values['new_values'])->toBe(['name' => 'Bay A', 'code' => 'BAY-A']);
});

it('falls back to code only for older warehouse area created format', function () {
    $row = locationRow([
        'Direct Object' => 'Warehouse Area',
        'Action' => 'created',
        'History Abstract' => 'Warehouse area <span class="italic">BAYB</span> record created',
    ]);

    $values = ParseLocationHistory::extractValues($row, 'created', null);
    expect($values['new_values'])->toBe(['code' => 'BAYB']);
});

it('classifies and extracts warehouse area place edit', function () {
    $row = locationRow([
        'Direct Object' => 'Warehouse Area',
        'Indirect Object' => 'Warehouse Area Place',
        'History Details' => '<div class="field tr"><div>Old value:</div><div>Local warehouse</div></div><div class="field tr"><div>New value:</div><div>External warehouse</div></div>',
    ]);

    $classified = ParseLocationHistory::classify($row);
    expect($classified)->toBe(['handling' => 'import', 'event' => 'updated', 'field' => 'place']);

    $values = ParseLocationHistory::extractValues($row, 'updated', 'place');
    expect($values['old_values'])->toBe(['place' => 'Local warehouse']);
    expect($values['new_values'])->toBe(['place' => 'External warehouse']);
});

it('extracts warehouse area associated location event', function () {
    $row = locationRow([
        'Direct Object' => 'Warehouse Area',
        'Action' => 'edited',
        'Indirect Object' => '',
        'History Abstract' => "Location <span class=\"link\" onclick=\"change_view('locations/1/areas/55')\" >L-01</span> associated",
    ]);

    $classified = ParseLocationHistory::classify($row);
    expect($classified)->toBe(['handling' => 'import', 'event' => 'associated', 'field' => null]);

    $values = ParseLocationHistory::extractValues($row, 'associated', null);
    expect($values['new_values'])->toBe(['location' => 'L-01']);
    expect($values['data'])->toBe(['location_source_id' => '55']);
});

it('handles sk sticky note with double html-escaped value', function () {
    $row = locationRow([
        'Indirect Object' => 'Location Sticky Note',
        'History Details' => '<div class="field tr"><div>Old value:</div><div></div></div><div class="field tr"><div>New value:</div><div>&amp;lt;b&amp;gt;fragile&amp;lt;/b&amp;gt;</div></div>',
    ]);

    $values = ParseLocationHistory::extractValues($row, 'updated', 'notes');
    expect($values['new_values']['notes'])->toContain('fragile');
});

it('skips unknown warehouse area indirect object', function () {
    $row = locationRow(['Direct Object' => 'Warehouse Area', 'Indirect Object' => 'Warehouse Area Something Else']);
    expect(ParseLocationHistory::classify($row))->toBe(['handling' => 'skip', 'event' => null, 'field' => null]);
});

it('skips deleted action', function () {
    $row = locationRow(['Action' => 'deleted']);
    expect(ParseLocationHistory::classify($row))->toBe(['handling' => 'skip', 'event' => null, 'field' => null]);
});
