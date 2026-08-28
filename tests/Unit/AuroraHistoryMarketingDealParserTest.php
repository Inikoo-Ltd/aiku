<?php

use App\Transfers\Aurora\History\Parsers\ParseDealHistory;
use App\Transfers\Aurora\History\Parsers\ParseMarketingHistory;
use App\Transfers\Aurora\History\Parsers\ParseShippingZoneHistory;

function marketingRow(array $overrides = []): object
{
    return (object) array_merge([
        'Direct Object'    => 'Email Campaign',
        'Indirect Object'  => null,
        'Action'           => null,
        'History Abstract' => '',
        'History Details'  => '',
    ], $overrides);
}

function dealRow(array $overrides = []): object
{
    return (object) array_merge([
        'Direct Object'    => 'Deal',
        'Indirect Object'  => null,
        'Action'           => null,
        'History Abstract' => '',
        'History Details'  => '',
    ], $overrides);
}

function shippingZoneRow(array $overrides = []): object
{
    return (object) array_merge([
        'Direct Object'    => 'Shipping Zone',
        'Indirect Object'  => null,
        'Action'           => null,
        'History Abstract' => '',
        'History Details'  => '',
    ], $overrides);
}

test('scheduled email campaign created is imported with campaign_type', function () {
    $row = marketingRow(['Action' => 'created', 'History Abstract' => 'Email campaign 2026.08.16 created']);

    $classified = ParseMarketingHistory::classify($row);
    expect($classified)->toBe(['handling' => 'import', 'event' => 'created', 'field' => null, 'auditable_type' => 'Mailshot']);

    $values = ParseMarketingHistory::extractValues($row, $classified['event'], $classified['field']);
    expect($values)->toBe(['old_values' => [], 'new_values' => [], 'data' => ['campaign_type' => 'scheduled']]);
});

test('newsletter created localized Czech is detected', function () {
    $row = marketingRow(['Action' => 'created', 'History Abstract' => 'Byl vytvořen zpravodaj']);

    $classified = ParseMarketingHistory::classify($row);
    $values     = ParseMarketingHistory::extractValues($row, $classified['event'], $classified['field']);

    expect($values['data'])->toBe(['campaign_type' => 'newsletter']);
});

test('newsletter created localized Slovak is detected', function () {
    $row = marketingRow(['Action' => 'created', 'History Abstract' => 'Bol vytvorený informačný bulletin']);

    $classified = ParseMarketingHistory::classify($row);
    $values     = ParseMarketingHistory::extractValues($row, $classified['event'], $classified['field']);

    expect($values['data'])->toBe(['campaign_type' => 'newsletter']);
});

test('abandoned basket mailshot localized Spanish is detected', function () {
    $row = marketingRow(['Action' => 'created', 'History Abstract' => 'mailing para pedidos en la cesta']);

    $classified = ParseMarketingHistory::classify($row);
    $values     = ParseMarketingHistory::extractValues($row, $classified['event'], $classified['field']);

    expect($values['data'])->toBe(['campaign_type' => 'abandoned_basket']);
});

test('invitation mailshot is detected', function () {
    $row = marketingRow(['Action' => 'created', 'History Abstract' => 'Invitation mailshot created']);

    $classified = ParseMarketingHistory::classify($row);
    $values     = ParseMarketingHistory::extractValues($row, $classified['event'], $classified['field']);

    expect($values['data'])->toBe(['campaign_type' => 'invitation']);
});

test('email template created captures category', function () {
    $row = marketingRow([
        'Direct Object'    => 'Email Template',
        'Action'           => 'created',
        'History Abstract' => '<b>Order Confirmation</b> email template created',
    ]);

    $classified = ParseMarketingHistory::classify($row);
    expect($classified['auditable_type'])->toBe('EmailTemplate');

    $values = ParseMarketingHistory::extractValues($row, $classified['event'], $classified['field']);
    expect($values['data'])->toBe(['template_category' => 'Order Confirmation']);
});

test('email campaign type status edit extracts new value', function () {
    $row = marketingRow([
        'Direct Object'    => 'Email Campaign Type',
        'Action'           => 'edited',
        'Indirect Object'  => 'Email Campaign Type Status',
        'History Abstract' => "Email Campaign Type's status was changed to Active",
    ]);

    $classified = ParseMarketingHistory::classify($row);
    expect($classified)->toBe(['handling' => 'import', 'event' => 'updated', 'field' => 'status', 'auditable_type' => 'MailshotType']);

    $values = ParseMarketingHistory::extractValues($row, $classified['event'], $classified['field']);
    expect($values)->toBe(['old_values' => [], 'new_values' => ['status' => 'Active'], 'data' => []]);
});

test('unmapped marketing indirect object is skipped', function () {
    $row = marketingRow(['Action' => 'edited', 'Indirect Object' => 'Something Unknown']);

    expect(ParseMarketingHistory::classify($row)['handling'])->toBe('skip');
});

test('deal created extracts name term allowance', function () {
    $row = dealRow([
        'Action'           => 'created',
        'History Abstract' => 'Offer Summer Sale (<span class="term">10% off</span> when spending <span class="allowance">£50</span>) created',
    ]);

    $classified = ParseDealHistory::classify($row);
    expect($classified)->toBe(['handling' => 'import', 'event' => 'created', 'field' => null, 'auditable_type' => 'Offer']);

    $values = ParseDealHistory::extractValues($row, $classified['event'], $classified['field']);
    expect($values)->toBe([
        'old_values' => [],
        'new_values' => ['name' => 'Summer Sale'],
        'data'       => ['term' => '10% off', 'allowance' => '£50'],
    ]);
});

test('deal created voucher variant captures voucher code', function () {
    $row = dealRow([
        'Action'           => 'created',
        'History Abstract' => 'Offer Voucher Deal (voucher: SAVE10 <span class="term">10% off</span>) created',
    ]);

    $classified = ParseDealHistory::classify($row);
    $values     = ParseDealHistory::extractValues($row, $classified['event'], $classified['field']);

    expect($values['new_values'])->toBe(['name' => 'Voucher Deal']);
    expect($values['data']['voucher'])->toBe('SAVE10');
});

test('deal name edit uses public name phrasing', function () {
    $row = dealRow([
        'Action'           => 'edited',
        'Indirect Object'  => 'Deal Name Label',
        'History Abstract' => 'The public name was changed to Winter Sale',
    ]);

    $classified = ParseDealHistory::classify($row);
    expect($classified)->toBe(['handling' => 'import', 'event' => 'updated', 'field' => 'name', 'auditable_type' => 'Offer']);

    $values = ParseDealHistory::extractValues($row, $classified['event'], $classified['field']);
    expect($values)->toBe(['old_values' => [], 'new_values' => ['name' => 'Winter Sale'], 'data' => []]);
});

test('deal status edit uses rArr arrow form', function () {
    $row = dealRow([
        'Action'           => 'edited',
        'Indirect Object'  => 'Deal Status',
        'History Abstract' => 'Deal &rArr; status was changed to Active',
    ]);

    $classified = ParseDealHistory::classify($row);
    $values     = ParseDealHistory::extractValues($row, $classified['event'], $classified['field']);

    expect($values)->toBe(['old_values' => [], 'new_values' => ['status' => 'Active'], 'data' => []]);
});

test('deal status edit uses Slovak mojibake form', function () {
    $row = dealRow([
        'Action'           => 'edited',
        'Indirect Object'  => 'Deal Status',
        'History Abstract' => 'Stav bolo zmenené naAktívny',
    ]);

    $classified = ParseDealHistory::classify($row);
    $values     = ParseDealHistory::extractValues($row, $classified['event'], $classified['field']);

    expect($values['new_values'])->toBe(['status' => 'Aktívny']);
});

test('deal component expiration date edit extracts set as date', function () {
    $row = dealRow([
        'Direct Object'    => 'Deal Component',
        'Action'           => 'edited',
        'Indirect Object'  => 'Deal Component Expiration Date',
        'History Abstract' => 'Deal component expiration date set as 2026-12-31',
    ]);

    $classified = ParseDealHistory::classify($row);
    expect($classified)->toBe(['handling' => 'import', 'event' => 'updated', 'field' => 'expiration_date', 'auditable_type' => 'OfferComponent']);

    $values = ParseDealHistory::extractValues($row, $classified['event'], $classified['field']);
    expect($values)->toBe(['old_values' => [], 'new_values' => ['expiration_date' => '2026-12-31'], 'data' => []]);
});

test('unmapped deal indirect object is skipped', function () {
    $row = dealRow(['Action' => 'edited', 'Indirect Object' => 'Something Unknown']);

    expect(ParseDealHistory::classify($row)['handling'])->toBe('skip');
});

test('shipping zone created extracts name', function () {
    $row = shippingZoneRow(['Action' => 'created', 'History Abstract' => 'Europe shipping zone created']);

    $classified = ParseShippingZoneHistory::classify($row);
    expect($classified)->toBe(['handling' => 'import', 'event' => 'created', 'field' => null, 'auditable_type' => 'ShippingZone']);

    $values = ParseShippingZoneHistory::extractValues($row, $classified['event'], $classified['field']);
    expect($values)->toBe(['old_values' => [], 'new_values' => ['name' => 'Europe'], 'data' => []]);
});

test('shipping zone schema created extracts name', function () {
    $row = shippingZoneRow([
        'Direct Object'    => 'Shipping Zone Schema',
        'Action'           => 'created',
        'History Abstract' => 'Default schema shipping zone schema created',
    ]);

    $classified = ParseShippingZoneHistory::classify($row);
    expect($classified)->toBe(['handling' => 'import', 'event' => 'created', 'field' => null, 'auditable_type' => 'ShippingZoneSchema']);

    $values = ParseShippingZoneHistory::extractValues($row, $classified['event'], $classified['field']);
    expect($values)->toBe(['old_values' => [], 'new_values' => ['name' => 'Default schema'], 'data' => []]);
});

test('shipping zone deleted is skipped', function () {
    $row = shippingZoneRow(['Action' => 'deleted']);

    expect(ParseShippingZoneHistory::classify($row)['handling'])->toBe('skip');
});
