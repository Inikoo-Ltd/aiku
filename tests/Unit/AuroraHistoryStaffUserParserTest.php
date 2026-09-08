<?php

use App\Transfers\Aurora\History\Parsers\ParseStaffUserHistory;

function staffUserRow(array $attributes): object
{
    return (object) array_merge([
        'Direct Object' => null,
        'Indirect Object' => null,
        'Action' => null,
        'History Abstract' => null,
        'History Details' => null,
    ], $attributes);
}

test('unknown direct object is skipped', function () {
    $row = staffUserRow(['Direct Object' => 'Something Else']);

    $classification = ParseStaffUserHistory::classify($row);

    expect($classification)->toBe(['handling' => 'skip', 'event' => null, 'field' => null, 'auditable_type' => null]);
});

test('staff created is routed to Employee', function () {
    $row = staffUserRow(['Direct Object' => 'Staff', 'Action' => 'created']);

    $classification = ParseStaffUserHistory::classify($row);

    expect($classification)->toBe(['handling' => 'import', 'event' => 'created', 'field' => null, 'auditable_type' => 'Employee']);
});

test('staff deleted is routed to Employee', function () {
    $row = staffUserRow(['Direct Object' => 'Staff', 'Action' => 'deleted']);

    $classification = ParseStaffUserHistory::classify($row);

    expect($classification)->toBe(['handling' => 'import', 'event' => 'deleted', 'field' => null, 'auditable_type' => 'Employee']);
});

test('staff name edit maps to name field', function () {
    $row = staffUserRow([
        'Direct Object' => 'Staff',
        'Indirect Object' => 'Staff Name',
        'History Details' => '<table><tr><td>Old value</td><td>John</td></tr><tr><td>New value</td><td>Johnny</td></tr></table>',
    ]);

    $classification = ParseStaffUserHistory::classify($row);
    expect($classification)->toBe(['handling' => 'import', 'event' => 'updated', 'field' => 'name', 'auditable_type' => 'Employee']);

    $values = ParseStaffUserHistory::extractValues($row, $classification['event'], $classification['field']);
    expect($values)->toBe([
        'old_values' => ['name' => 'John'],
        'new_values' => ['name' => 'Johnny'],
        'data' => [],
    ]);
});

test('staff clocking pin edit is redacted', function () {
    $row = staffUserRow([
        'Direct Object' => 'Staff',
        'Indirect Object' => 'Staff Clocking PIN',
        'History Details' => '<table><tr><td>Old value</td><td>1234</td></tr><tr><td>New value</td><td>5678</td></tr></table>',
    ]);

    $classification = ParseStaffUserHistory::classify($row);
    $values = ParseStaffUserHistory::extractValues($row, $classification['event'], $classification['field']);

    expect($values)->toBe([
        'old_values' => ['clocking_pin' => '[redacted]'],
        'new_values' => ['clocking_pin' => '[redacted]'],
        'data' => [],
    ]);
});

test('unknown staff indirect object is skipped', function () {
    $row = staffUserRow(['Direct Object' => 'Staff', 'Indirect Object' => 'Staff Something Unknown']);

    expect(ParseStaffUserHistory::classify($row))->toBe(['handling' => 'skip', 'event' => null, 'field' => null, 'auditable_type' => null]);
});

test('user created is routed to User', function () {
    $row = staffUserRow(['Direct Object' => 'User', 'Action' => 'created']);

    expect(ParseStaffUserHistory::classify($row))->toBe(['handling' => 'import', 'event' => 'created', 'field' => null, 'auditable_type' => 'User']);
});

test('user password edit is redacted plaintext', function () {
    $row = staffUserRow([
        'Direct Object' => 'User',
        'Indirect Object' => 'User Password',
        'History Details' => '<table><tr><td>Old value</td><td>oldpass123</td></tr><tr><td>New value</td><td>newpass456</td></tr></table>',
    ]);

    $classification = ParseStaffUserHistory::classify($row);
    expect($classification['field'])->toBe('password');

    $values = ParseStaffUserHistory::extractValues($row, $classification['event'], $classification['field']);
    expect($values['old_values']['password'])->toBe('[redacted]');
    expect($values['new_values']['password'])->toBe('[redacted]');
});

test('user password masked value stays masked', function () {
    $row = staffUserRow([
        'Direct Object' => 'User',
        'Indirect Object' => 'User Password',
        'History Details' => '<table><tr><td>Old value</td><td>****</td></tr><tr><td>New value</td><td>****</td></tr></table>',
    ]);

    $classification = ParseStaffUserHistory::classify($row);
    $values = ParseStaffUserHistory::extractValues($row, $classification['event'], $classification['field']);

    expect($values['old_values']['password'])->toBe('****');
    expect($values['new_values']['password'])->toBe('****');
});

test('user password hash value is redacted', function () {
    $hash = str_repeat('a1b2', 16);
    $row = staffUserRow([
        'Direct Object' => 'User',
        'Indirect Object' => 'User Password',
        'History Details' => "<table><tr><td>Old value</td><td>{$hash}</td></tr><tr><td>New value</td><td>{$hash}</td></tr></table>",
    ]);

    $classification = ParseStaffUserHistory::classify($row);
    $values = ParseStaffUserHistory::extractValues($row, $classification['event'], $classification['field']);

    expect($values['old_values']['password'])->toBe('[redacted]');
    expect($values['new_values']['password'])->toBe('[redacted]');
});

test('non credential field is left untouched', function () {
    $row = staffUserRow([
        'Direct Object' => 'User',
        'Indirect Object' => 'User Handle',
        'History Details' => '<table><tr><td>Old value</td><td>old_handle</td></tr><tr><td>New value</td><td>new_handle</td></tr></table>',
    ]);

    $classification = ParseStaffUserHistory::classify($row);
    $values = ParseStaffUserHistory::extractValues($row, $classification['event'], $classification['field']);

    expect($values['old_values']['handle'])->toBe('old_handle');
    expect($values['new_values']['handle'])->toBe('new_handle');
});

test('user disassociate from group is access revoked with scope', function () {
    $row = staffUserRow([
        'Direct Object' => 'User',
        'Indirect Object' => 'Group',
        'Action' => 'disassociate',
        'History Abstract' => 'User disassociated with Warehouse Staff',
    ]);

    $classification = ParseStaffUserHistory::classify($row);
    expect($classification)->toBe(['handling' => 'import', 'event' => 'access_revoked', 'field' => null, 'auditable_type' => 'User']);

    $values = ParseStaffUserHistory::extractValues($row, $classification['event'], $classification['field']);
    expect($values)->toBe([
        'old_values' => [],
        'new_values' => [],
        'data' => ['scope_type' => 'Group', 'scope' => 'Warehouse Staff'],
    ]);
});

test('user blank action against scope indirect object is access granted', function () {
    $row = staffUserRow([
        'Direct Object' => 'User',
        'Indirect Object' => 'Store',
        'Action' => '',
    ]);

    $classification = ParseStaffUserHistory::classify($row);
    expect($classification)->toBe(['handling' => 'import', 'event' => 'access_granted', 'field' => null, 'auditable_type' => 'User']);

    $values = ParseStaffUserHistory::extractValues($row, $classification['event'], $classification['field']);
    expect($values)->toBe([
        'old_values' => [],
        'new_values' => [],
        'data' => ['scope_type' => 'Store', 'scope' => null],
    ]);
});

test('user blank action against field map indirect object is treated as edited', function () {
    $row = staffUserRow([
        'Direct Object' => 'User',
        'Indirect Object' => 'User Alias',
        'Action' => '',
        'History Details' => '<table><tr><td>Old value</td><td>alias1</td></tr><tr><td>New value</td><td>alias2</td></tr></table>',
    ]);

    $classification = ParseStaffUserHistory::classify($row);
    expect($classification)->toBe(['handling' => 'import', 'event' => 'updated', 'field' => 'alias', 'auditable_type' => 'User']);
});

test('website user created captures email from abstract', function () {
    $row = staffUserRow([
        'Direct Object' => 'Website User',
        'Action' => 'created',
        'History Abstract' => 'Website user jane@example.com created',
    ]);

    $classification = ParseStaffUserHistory::classify($row);
    expect($classification)->toBe(['handling' => 'import', 'event' => 'created', 'field' => null, 'auditable_type' => 'WebUser']);

    $values = ParseStaffUserHistory::extractValues($row, $classification['event'], $classification['field']);
    expect($values)->toBe([
        'old_values' => [],
        'new_values' => ['email' => 'jane@example.com'],
        'data' => [],
    ]);
});

test('website user handle edit maps to email field', function () {
    $row = staffUserRow([
        'Direct Object' => 'Website User',
        'Indirect Object' => 'Website User Handle',
        'History Abstract' => "Website User's handle was changed to jane2@example.com",
    ]);

    $classification = ParseStaffUserHistory::classify($row);
    expect($classification)->toBe(['handling' => 'import', 'event' => 'updated', 'field' => 'email', 'auditable_type' => 'WebUser']);

    $values = ParseStaffUserHistory::extractValues($row, $classification['event'], $classification['field']);
    expect($values)->toBe([
        'old_values' => ['email' => ''],
        'new_values' => ['email' => 'jane2@example.com'],
        'data' => [],
    ]);
});

test('website user unknown indirect object is skipped', function () {
    $row = staffUserRow(['Direct Object' => 'Website User', 'Indirect Object' => 'Website User Something Else']);

    expect(ParseStaffUserHistory::classify($row))->toBe(['handling' => 'skip', 'event' => null, 'field' => null, 'auditable_type' => null]);
});

test('localized deletion abstract is treated as updated with empty new value', function () {
    $row = staffUserRow([
        'Direct Object' => 'Staff',
        'Indirect Object' => 'Staff Next of Kind',
        'History Abstract' => "Staff's emergency contact bol odstránený",
    ]);

    $classification = ParseStaffUserHistory::classify($row);
    expect($classification)->toBe(['handling' => 'import', 'event' => 'updated', 'field' => 'emergency_contact', 'auditable_type' => 'Employee']);

    $values = ParseStaffUserHistory::extractValues($row, $classification['event'], $classification['field']);
    expect($values)->toBe([
        'old_values' => ['emergency_contact' => ''],
        'new_values' => ['emergency_contact' => ''],
        'data' => [],
    ]);
});

test('updated field with no table and no abstract match returns empty arrays', function () {
    $row = staffUserRow([
        'Direct Object' => 'Staff',
        'Indirect Object' => 'Staff Position',
    ]);

    $values = ParseStaffUserHistory::extractValues($row, 'updated', 'position');

    expect($values)->toBe(['old_values' => [], 'new_values' => [], 'data' => []]);
});
