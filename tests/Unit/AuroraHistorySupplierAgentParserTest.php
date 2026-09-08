<?php

use App\Transfers\Aurora\History\Parsers\ParseSupplierAgentHistory;

function supplierAgentRow(array $overrides = []): object
{
    return (object) array_merge([
        'Direct Object'    => 'Supplier',
        'Indirect Object'  => null,
        'Action'           => null,
        'History Abstract' => '',
        'History Details'  => '',
    ], $overrides);
}

test('Supplier Name update routes to group Supplier', function () {
    $row = supplierAgentRow([
        'Action'          => 'edited',
        'Indirect Object' => 'Supplier Name',
    ]);

    $result = ParseSupplierAgentHistory::classify($row);

    expect($result)->toBe(['handling' => 'import', 'event' => 'updated', 'field' => 'name', 'auditable_type' => 'Supplier']);
});

test('Supplier Default Currency Code update routes to OrgSupplier', function () {
    $row = supplierAgentRow([
        'Action'          => 'edited',
        'Indirect Object' => 'Supplier Default Currency Code',
    ]);

    $result = ParseSupplierAgentHistory::classify($row);

    expect($result)->toBe(['handling' => 'import', 'event' => 'updated', 'field' => 'currency', 'auditable_type' => 'OrgSupplier']);
});

test('Agent Company Name update routes to group Agent', function () {
    $row = supplierAgentRow([
        'Direct Object'   => 'Agent',
        'Action'          => 'edited',
        'Indirect Object' => 'Agent Company Name',
    ]);

    $result = ParseSupplierAgentHistory::classify($row);

    expect($result)->toBe(['handling' => 'import', 'event' => 'updated', 'field' => 'company_name', 'auditable_type' => 'Agent']);
});

test('Agent Default Incoterm update routes to OrgAgent', function () {
    $row = supplierAgentRow([
        'Direct Object'   => 'Agent',
        'Action'          => 'edited',
        'Indirect Object' => 'Agent Default Incoterm',
    ]);

    $result = ParseSupplierAgentHistory::classify($row);

    expect($result)->toBe(['handling' => 'import', 'event' => 'updated', 'field' => 'incoterm', 'auditable_type' => 'OrgAgent']);
});

test('era-A plain sentence extracts supplier name change', function () {
    $row = supplierAgentRow([
        'Action'           => 'edited',
        'Indirect Object'  => 'Supplier Name',
        'History Details'  => 'Supplier Name changed from "Old Co" to "New Co"',
    ]);

    $classified = ParseSupplierAgentHistory::classify($row);
    $values     = ParseSupplierAgentHistory::extractValues($row, $classified['event'], $classified['field']);

    expect($values)->toBe([
        'old_values' => ['name' => 'Old Co'],
        'new_values' => ['name' => 'New Co'],
        'data' => [],
    ]);
});

test('supplier contact address table era parses adr microformat', function () {
    $old = '<div class="adr"><span class="street-address">1 Old St</span><span class="locality">Oldtown</span></div>';
    $new = '<div class="adr"><span class="street-address">2 New St</span><span class="locality">Newtown</span></div>';

    $row = supplierAgentRow([
        'Action'          => 'edited',
        'Indirect Object' => 'Supplier Contact Address',
        'History Details' => "<table><tr><td>Old value</td><td>{$old}</td></tr><tr><td>New value</td><td>{$new}</td></tr></table>",
    ]);

    $classified = ParseSupplierAgentHistory::classify($row);
    $values     = ParseSupplierAgentHistory::extractValues($row, $classified['event'], $classified['field']);

    expect($values['old_values']['address']['address_line_1'])->toBe('1 Old St');
    expect($values['old_values']['address']['locality'])->toBe('Oldtown');
    expect($values['new_values']['address']['address_line_1'])->toBe('2 New St');
    expect($values['new_values']['address']['locality'])->toBe('Newtown');
});

test('agent contact address abstract set as form parses adr microformat', function () {
    $adr = '<div class="adr"><span class="street-address">5 Agent Rd</span><span class="locality">Agentville</span></div>';

    $row = supplierAgentRow([
        'Direct Object'    => 'Agent',
        'Action'           => 'edited',
        'Indirect Object'  => 'Agent Contact Address',
        'History Abstract' => "Agent's contact address set as {$adr}",
    ]);

    $classified = ParseSupplierAgentHistory::classify($row);
    $values     = ParseSupplierAgentHistory::extractValues($row, $classified['event'], $classified['field']);

    expect($values['old_values'])->toBe([]);
    expect($values['new_values']['address']['address_line_1'])->toBe('5 Agent Rd');
    expect($values['new_values']['address']['locality'])->toBe('Agentville');
});

test('agent contact address abstract arrow form parses adr microformat', function () {
    $adr = '<div class="adr"><span class="street-address">9 Arrow Ave</span></div>';

    $row = supplierAgentRow([
        'Direct Object'    => 'Agent',
        'Action'           => 'edited',
        'Indirect Object'  => 'Agent Contact Address',
        'History Abstract' => "Agent &rArr; contact address was changed to {$adr}",
    ]);

    $classified = ParseSupplierAgentHistory::classify($row);
    $values     = ParseSupplierAgentHistory::extractValues($row, $classified['event'], $classified['field']);

    expect($values['old_values'])->toBe([]);
    expect($values['new_values']['address']['address_line_1'])->toBe('9 Arrow Ave');
});

test('legacy supplier address html sentence keeps raw strings', function () {
    $old = '<div class="history_address">1 Legacy St, Oldtown</div>';
    $new = '<div class="history_address">2 Legacy St, Newtown</div>';

    $row = supplierAgentRow([
        'Action'          => 'edited',
        'Indirect Object' => 'Supplier Contact Address',
        'History Details' => "Address changed from {$old} to {$new}",
    ]);

    $classified = ParseSupplierAgentHistory::classify($row);
    $values     = ParseSupplierAgentHistory::extractValues($row, $classified['event'], $classified['field']);

    expect($values)->toBe([
        'old_values' => ['address' => $old],
        'new_values' => ['address' => $new],
        'data' => [],
    ]);
});

test('bare Supplier created abstract is imported with no name', function () {
    $row = supplierAgentRow(['Action' => 'created', 'History Abstract' => 'Supplier created']);

    $classified = ParseSupplierAgentHistory::classify($row);

    expect($classified)->toBe(['handling' => 'import', 'event' => 'created', 'field' => null, 'auditable_type' => 'Supplier']);

    $values = ParseSupplierAgentHistory::extractValues($row, $classified['event'], $classified['field']);

    expect($values)->toBe(['old_values' => [], 'new_values' => [], 'data' => []]);
});

test('New supplier added abstract extracts name', function () {
    $row = supplierAgentRow(['Action' => 'created', 'History Abstract' => 'New supplier "Acme Ltd" added']);

    $classified = ParseSupplierAgentHistory::classify($row);
    $values     = ParseSupplierAgentHistory::extractValues($row, $classified['event'], $classified['field']);

    expect($values)->toBe([
        'old_values' => [],
        'new_values' => ['name' => 'Acme Ltd'],
        'data' => [],
    ]);
});

test('Agent created abstract extracts name', function () {
    $row = supplierAgentRow(['Direct Object' => 'Agent', 'Action' => 'created', 'History Abstract' => 'Agent Speedy Freight created']);

    $classified = ParseSupplierAgentHistory::classify($row);

    expect($classified)->toBe(['handling' => 'import', 'event' => 'created', 'field' => null, 'auditable_type' => 'Agent']);

    $values = ParseSupplierAgentHistory::extractValues($row, $classified['event'], $classified['field']);

    expect($values)->toBe([
        'old_values' => [],
        'new_values' => ['name' => 'Speedy Freight'],
        'data' => [],
    ]);
});

test('supplier deleted routes to OrgSupplier and extracts name', function () {
    $row = supplierAgentRow(['Action' => 'deleted', 'History Abstract' => 'Supplier record Acme Ltd deleted']);

    $classified = ParseSupplierAgentHistory::classify($row);

    expect($classified)->toBe(['handling' => 'import', 'event' => 'deleted', 'field' => null, 'auditable_type' => 'OrgSupplier']);

    $values = ParseSupplierAgentHistory::extractValues($row, $classified['event'], $classified['field']);

    expect($values)->toBe(['old_values' => [], 'new_values' => [], 'data' => ['name' => 'Acme Ltd']]);
});

test('agent deleted routes to OrgAgent', function () {
    $row = supplierAgentRow(['Direct Object' => 'Agent', 'Action' => 'deleted', 'History Abstract' => 'Agent record Speedy Freight deleted']);

    $classified = ParseSupplierAgentHistory::classify($row);

    expect($classified)->toBe(['handling' => 'import', 'event' => 'deleted', 'field' => null, 'auditable_type' => 'OrgAgent']);

    $values = ParseSupplierAgentHistory::extractValues($row, $classified['event'], $classified['field']);

    expect($values)->toBe(['old_values' => [], 'new_values' => [], 'data' => ['name' => 'Speedy Freight']]);
});

test('Acc Parts indirect object is skipped', function () {
    $row = supplierAgentRow(['Action' => 'edited', 'Indirect Object' => 'Supplier Acc Parts Balance']);

    expect(ParseSupplierAgentHistory::classify($row)['handling'])->toBe('skip');
});

test('Category Supplier indirect object is skipped', function () {
    $row = supplierAgentRow(['Action' => 'edited', 'Indirect Object' => 'Category Supplier']);

    expect(ParseSupplierAgentHistory::classify($row)['handling'])->toBe('skip');
});

test('blank Action with a mapped Indirect Object is treated as edited', function () {
    $row = supplierAgentRow(['Action' => '', 'Indirect Object' => 'Supplier Code']);

    $result = ParseSupplierAgentHistory::classify($row);

    expect($result)->toBe(['handling' => 'import', 'event' => 'updated', 'field' => 'code', 'auditable_type' => 'Supplier']);
});

test('blank Action with unmapped contact-subrecord bucket is skipped', function () {
    $row = supplierAgentRow(['Action' => '', 'Indirect Object' => 'Telephone']);

    expect(ParseSupplierAgentHistory::classify($row)['handling'])->toBe('skip');
});

test('unknown Indirect Object is skipped', function () {
    $row = supplierAgentRow(['Action' => 'edited', 'Indirect Object' => 'Supplier Mystery Field']);

    expect(ParseSupplierAgentHistory::classify($row)['handling'])->toBe('skip');
});

test('unknown Direct Object is skipped', function () {
    $row = supplierAgentRow(['Direct Object' => 'Customer', 'Action' => 'edited', 'Indirect Object' => 'Supplier Name']);

    expect(ParseSupplierAgentHistory::classify($row)['handling'])->toBe('skip');
});

test('associated action is skipped', function () {
    $row = supplierAgentRow(['Action' => 'associated', 'Indirect Object' => 'Supplier Name']);

    expect(ParseSupplierAgentHistory::classify($row)['handling'])->toBe('skip');
});
