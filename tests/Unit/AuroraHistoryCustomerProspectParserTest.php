<?php

use App\Transfers\Aurora\History\Parsers\ParseCustomerHistory;
use App\Transfers\Aurora\History\Parsers\ParseProspectHistory;

function makeRow(array $overrides = []): object
{
    return (object) array_merge([
        'History Key' => 1,
        'History Date' => '2020-01-01',
        'Direct Object' => 'Customer',
        'Direct Object Key' => 1,
        'Indirect Object' => null,
        'Action' => 'created',
        'History Abstract' => '',
        'History Details' => '',
        'Subject' => null,
        'Subject Key' => null,
    ], $overrides);
}

test('customer registered abstract classifies as created and extracts name', function () {
    $row = makeRow(['Action' => 'created', 'History Abstract' => 'Customer John Doe registered']);

    $classification = ParseCustomerHistory::classify($row);
    expect($classification)->toBe(['handling' => 'import', 'event' => 'created', 'field' => null]);

    $values = ParseCustomerHistory::extractValues($row, 'created', null);
    expect($values['new_values'])->toBe(['name' => 'John Doe']);
});

test('generic dw customer record created classifies as created', function () {
    $row = makeRow(['Action' => 'created', 'History Abstract' => 'Jane Smith customer record created']);

    $classification = ParseCustomerHistory::classify($row);
    expect($classification['handling'])->toBe('import');
    expect($classification['event'])->toBe('created');

    $values = ParseCustomerHistory::extractValues($row, 'created', null);
    expect($values['new_values'])->toBe(['name' => 'Jane Smith']);
});

test('literal Customer Created imports with name unavailable data flag', function () {
    $row = makeRow(['Action' => 'created', 'History Abstract' => 'Customer Created']);

    $classification = ParseCustomerHistory::classify($row);
    expect($classification)->toBe(['handling' => 'import', 'event' => 'created', 'field' => null]);

    $values = ParseCustomerHistory::extractValues($row, 'created', null);
    expect($values['new_values'])->toBe([]);
    expect($values['data'])->toBe(['name_unavailable' => true]);
});

test('customer merged classifies as merged and extracts ref', function () {
    $row = makeRow(['Action' => 'merged', 'History Abstract' => 'Customer C63948 merged']);

    $classification = ParseCustomerHistory::classify($row);
    expect($classification)->toBe(['handling' => 'import', 'event' => 'merged', 'field' => null]);

    $values = ParseCustomerHistory::extractValues($row, 'merged', null);
    expect($values['data'])->toBe(['merged_ref' => 'C63948']);
});

test('customer newsletter field edit maps to canonical field', function () {
    $row = makeRow([
        'Action' => 'edited',
        'Indirect Object' => 'Customer Send Newsletter',
        'History Abstract' => 'Customer Send Newsletter changed',
    ]);

    $classification = ParseCustomerHistory::classify($row);
    expect($classification)->toBe(['handling' => 'import', 'event' => 'updated', 'field' => 'subscribed_to_newsletter']);
});

test('customer unsubscribed activity stream event classifies and extracts', function () {
    $row = makeRow([
        'Action' => 'edited',
        'Indirect Object' => null,
        'History Abstract' => 'Unsubscribed because of a hard bounce',
    ]);

    $classification = ParseCustomerHistory::classify($row);
    expect($classification)->toBe(['handling' => 'import', 'event' => 'unsubscribed', 'field' => null]);

    $values = ParseCustomerHistory::extractValues($row, 'unsubscribed', null);
    expect($values['new_values'])->toBe([
        'subscribed_to_newsletter' => 'No',
        'subscribed_to_email_marketing' => 'No',
    ]);
    expect($values['data'])->toBe(['reason' => 'bounce']);
});

test('customer bounce diagnostics in activity stream are skipped', function () {
    $row = makeRow([
        'Action' => 'edited',
        'Indirect Object' => null,
        'History Abstract' => 'Correo electrónico rebotado',
    ]);

    expect(ParseCustomerHistory::classify($row))->toBe(['handling' => 'skip', 'event' => null, 'field' => null]);
});

test('customer unknown indirect object is skipped', function () {
    $row = makeRow([
        'Action' => 'edited',
        'Indirect Object' => 'Something Unmapped',
        'History Abstract' => 'Something Unmapped changed',
    ]);

    expect(ParseCustomerHistory::classify($row))->toBe(['handling' => 'skip', 'event' => null, 'field' => null]);
});

test('customer other action is skipped', function () {
    $row = makeRow(['Action' => 'viewed']);

    expect(ParseCustomerHistory::classify($row))->toBe(['handling' => 'skip', 'event' => null, 'field' => null]);
});

test('prospect record created classifies and extracts name', function () {
    $row = makeRow(['Action' => 'created', 'History Abstract' => 'Bob Builder prospect record created']);

    $classification = ParseProspectHistory::classify($row);
    expect($classification)->toBe(['handling' => 'import', 'event' => 'created', 'field' => null]);

    $values = ParseProspectHistory::extractValues($row, 'created', null);
    expect($values['new_values'])->toBe(['name' => 'Bob Builder']);
});

test('slovak prospect created extracts company name', function () {
    $row = makeRow([
        'Action' => 'created',
        'History Abstract' => 'Bol vytvorený záznam o perspektíve Acme SK',
    ]);

    $classification = ParseProspectHistory::classify($row);
    expect($classification)->toBe(['handling' => 'import', 'event' => 'created', 'field' => null]);

    $values = ParseProspectHistory::extractValues($row, 'created', null);
    expect($values['new_values'])->toBe(['company_name' => 'Acme SK']);
});

test('prospect registered as customer extracts source id and reference', function () {
    $row = makeRow([
        'Action' => 'edited',
        'Indirect Object' => null,
        'History Abstract' => "Prospect registered as a customer change_view('customers/5/12345') (ABC123)",
    ]);

    $classification = ParseProspectHistory::classify($row);
    expect($classification)->toBe(['handling' => 'import', 'event' => 'registered', 'field' => null]);

    $values = ParseProspectHistory::extractValues($row, 'registered', null);
    expect($values['data'])->toBe([
        'customer_source_id' => 12345,
        'customer_reference' => 'ABC123',
    ]);
});

test('prospect opt out classifies without repeat flag', function () {
    $row = makeRow([
        'Action' => 'edited',
        'Indirect Object' => null,
        'History Abstract' => 'Recipient opt out',
    ]);

    $classification = ParseProspectHistory::classify($row);
    expect($classification)->toBe(['handling' => 'import', 'event' => 'opted_out', 'field' => null]);

    $values = ParseProspectHistory::extractValues($row, 'opted_out', null);
    expect($values['data'])->toBe([]);
});

test('prospect opt out again classifies with repeat flag', function () {
    $row = makeRow([
        'Action' => 'edited',
        'Indirect Object' => null,
        'History Abstract' => 'Recipient opt out again',
    ]);

    $classification = ParseProspectHistory::classify($row);
    expect($classification)->toBe(['handling' => 'import', 'event' => 'opted_out', 'field' => null]);

    $values = ParseProspectHistory::extractValues($row, 'opted_out', null);
    expect($values['data'])->toBe(['repeat' => true]);
});

test('prospect email field edit maps to canonical field', function () {
    $row = makeRow([
        'Action' => 'edited',
        'Indirect Object' => 'Prospect Main Plain Email',
        'History Abstract' => "Prospect's email was changed to bob@example.com",
    ]);

    $classification = ParseProspectHistory::classify($row);
    expect($classification)->toBe(['handling' => 'import', 'event' => 'updated', 'field' => 'email']);

    $values = ParseProspectHistory::extractValues($row, 'updated', 'email');
    expect($values['new_values'])->toBe(['email' => 'bob@example.com']);
});

test('prospect field deletion form sets empty new and old values', function () {
    $row = makeRow([
        'Action' => 'edited',
        'Indirect Object' => 'Prospect Sticky Note',
        'History Abstract' => "Prospect's note was deleted",
    ]);

    $values = ParseProspectHistory::extractValues($row, 'updated', 'note');
    expect($values['old_values'])->toBe(['note' => '']);
    expect($values['new_values'])->toBe(['note' => '']);
});

test('prospect invitation email activity stream is skipped', function () {
    $row = makeRow([
        'Action' => 'edited',
        'Indirect Object' => null,
        'History Abstract' => 'Invitation email sent',
    ]);

    expect(ParseProspectHistory::classify($row))->toBe(['handling' => 'skip', 'event' => null, 'field' => null]);
});

test('prospect unknown indirect object is skipped', function () {
    $row = makeRow([
        'Action' => 'edited',
        'Indirect Object' => 'Something Unmapped',
        'History Abstract' => 'Something Unmapped changed',
    ]);

    expect(ParseProspectHistory::classify($row))->toBe(['handling' => 'skip', 'event' => null, 'field' => null]);
});

test('prospect other action is skipped', function () {
    $row = makeRow(['Action' => 'invoiced']);

    expect(ParseProspectHistory::classify($row))->toBe(['handling' => 'skip', 'event' => null, 'field' => null]);
});
