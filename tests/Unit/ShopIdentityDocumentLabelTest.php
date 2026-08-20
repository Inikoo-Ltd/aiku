<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace Tests\Unit;

use App\Models\Catalogue\Shop;

it('detects when a shop already collects a number under its own identity document label', function (array $settings, string $label, bool $expected) {
    $shop = new Shop(['settings' => $settings]);

    expect($shop->collectsIdentityDocumentAs($label))->toBe($expected);
})->with([
    'uk labels both'      => [['customer' => ['identity_document_number' => 'EORI Number', 'identity_document_number_alt' => 'UKIMS Number']], 'EORI', true],
    'uk ukims'            => [['customer' => ['identity_document_number' => 'EORI Number', 'identity_document_number_alt' => 'UKIMS Number']], 'UKIMS', true],
    'case insensitive'    => [['customer' => ['identity_document_number' => 'eori number']], 'EORI', true],
    'national reg number' => [['customer' => ['identity_document_number' => 'IČO', 'identity_document_number_alt' => 'DIČ']], 'EORI', false],
    'local vat id'        => [['customer' => ['identity_document_number' => 'Local VAT ID']], 'EORI', false],
    'no labels set'       => [[], 'EORI', false],
]);
