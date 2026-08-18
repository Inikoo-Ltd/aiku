<?php

namespace Tests\Unit;

use App\Transfers\Aurora\History\Parsers\ParseCustomerClientHistory;

function auroraClientPalletHistoryRow(array $attributes): object
{
    return (object) array_merge([
        'Action' => null,
        'Direct Object' => null,
        'Indirect Object' => null,
        'History Abstract' => '',
        'History Details' => '',
    ], $attributes);
}

test('customer client created with name extracts it', function () {
    $row = auroraClientPalletHistoryRow(['Action' => 'created', 'History Abstract' => "Customer's client created (Acme Ltd)"]);
    expect(ParseCustomerClientHistory::classify($row))->toBe(['handling' => 'import', 'event' => 'created', 'field' => null]);

    $values = ParseCustomerClientHistory::extractValues($row, 'created', null);
    expect($values['new_values'])->toBe(['name' => 'Acme Ltd']);
});

test('customer client created without name returns empty values', function () {
    $row = auroraClientPalletHistoryRow(['Action' => 'created', 'History Abstract' => "Customer's client created"]);
    $values = ParseCustomerClientHistory::extractValues($row, 'created', null);
    expect($values)->toBe(['old_values' => [], 'new_values' => [], 'data' => []]);
});

test('customer client deleted classifies as deleted', function () {
    $row = auroraClientPalletHistoryRow(['Action' => 'deleted', 'History Abstract' => 'Customer client deleted']);
    expect(ParseCustomerClientHistory::classify($row))->toBe(['handling' => 'import', 'event' => 'deleted', 'field' => null]);
});

test('customer client email edit via arrow abstract', function () {
    $row = auroraClientPalletHistoryRow([
        'Action' => 'edited',
        'Indirect Object' => 'Customer Client Main Plain Email',
        'History Abstract' => 'Customer Client &rArr; Email was changed to new@example.com',
    ]);
    $classification = ParseCustomerClientHistory::classify($row);
    expect($classification)->toBe(['handling' => 'import', 'event' => 'updated', 'field' => 'email']);

    $values = ParseCustomerClientHistory::extractValues($row, 'updated', 'email');
    expect($values['new_values'])->toBe(['email' => 'new@example.com']);
    expect($values['old_values'])->toBe(['email' => '']);
});

test('customer client address edit parses adr block', function () {
    $row = auroraClientPalletHistoryRow([
        'Action' => 'edited',
        'Indirect Object' => 'Customer Client Contact Address',
        'History Details' => '<table><tr><td>Old value</td><td></td></tr><tr><td>New value</td><td><div class="adr"><span class="street-address">1 Main St</span><span class="locality">Town</span></div></td></tr></table>',
    ]);
    $values = ParseCustomerClientHistory::extractValues($row, 'updated', 'address');
    expect($values['new_values']['address']['address_line_1'])->toBe('1 Main St');
    expect($values['new_values']['address']['locality'])->toBe('Town');
});

test('customer client code edit via table', function () {
    $row = auroraClientPalletHistoryRow([
        'Action' => 'edited',
        'Indirect Object' => 'Customer Client Code',
        'History Details' => '<table><tr><td>Old value</td><td>C1</td></tr><tr><td>New value</td><td>C2</td></tr></table>',
    ]);
    $values = ParseCustomerClientHistory::extractValues($row, 'updated', 'code');
    expect($values['old_values'])->toBe(['code' => 'C1']);
    expect($values['new_values'])->toBe(['code' => 'C2']);
});

test('customer client unknown edit is skipped', function () {
    $row = auroraClientPalletHistoryRow(['Action' => 'edited', 'Indirect Object' => 'Something Else']);
    expect(ParseCustomerClientHistory::classify($row)['handling'])->toBe('skip');
});

test('customer client null indirect object edit is skipped', function () {
    $row = auroraClientPalletHistoryRow(['Action' => 'edited']);
    expect(ParseCustomerClientHistory::classify($row)['handling'])->toBe('skip');
});
