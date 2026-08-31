<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Accounting\Invoice\GetInvoicePdfColumns;
use App\Models\Accounting\Invoice;
use App\Models\Catalogue\Shop;

function invoiceWithColumns(array $shopColumns, array $invoiceColumns = []): Invoice
{
    $shop           = new Shop();
    $shop->settings = ['invoicing' => ['download_pdf_columns' => $shopColumns]];

    $invoice       = new Invoice();
    $invoice->data = $invoiceColumns ? ['pdf_columns' => $invoiceColumns] : [];
    $invoice->setRelation('shop', $shop);

    return $invoice;
}

test('the invoice override wins over the shop setting', function () {
    $columns = GetInvoicePdfColumns::run(invoiceWithColumns(['weight' => true], ['weight' => false]));

    expect($columns['weight'])->toBeFalse();
});

test('a column with no invoice override falls back to the shop setting', function () {
    $columns = GetInvoicePdfColumns::run(invoiceWithColumns(['weight' => true, 'show_discounts' => false]));

    expect($columns['weight'])->toBeTrue()
        ->and($columns['show_discounts'])->toBeFalse();
});

test('an unset column falls back to its default', function () {
    $columns = GetInvoicePdfColumns::run(invoiceWithColumns([]));

    expect($columns['weight'])->toBeFalse()
        ->and($columns['separate_out_of_stock'])->toBeTrue()
        ->and($columns['show_discounts'])->toBeTrue();
});

test('every column is always resolved', function () {
    expect(GetInvoicePdfColumns::run(invoiceWithColumns([])))
        ->toHaveKeys(array_keys(GetInvoicePdfColumns::COLUMNS));
});
