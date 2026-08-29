<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Accounting\Invoice\WithInvoicesExport;
use App\Models\Accounting\Invoice;
use App\Models\Catalogue\Shop;

function invoiceWithColumns(array $columns): Invoice
{
    $shop           = new Shop();
    $shop->settings = ['invoicing' => ['download_pdf_columns' => $columns]];

    $invoice = new Invoice();
    $invoice->setRelation('shop', $shop);

    return $invoice;
}

function columnResolver(): object
{
    return new class () {
        use WithInvoicesExport;
    };
}

test('download options win over the shop setting', function () {
    $invoice = invoiceWithColumns(['weight' => true]);

    expect(columnResolver()->resolvePdfColumn($invoice, ['weight' => false], 'weight'))->toBeFalse();
});

test('an automated pdf falls back to the shop setting', function () {
    $invoice = invoiceWithColumns(['weight' => true, 'show_discounts' => false]);

    expect(columnResolver()->resolvePdfColumn($invoice, [], 'weight'))->toBeTrue()
        ->and(columnResolver()->resolvePdfColumn($invoice, [], 'show_discounts', true))->toBeFalse();
});

test('an unset column falls back to its default', function () {
    $invoice = invoiceWithColumns([]);

    expect(columnResolver()->resolvePdfColumn($invoice, [], 'weight'))->toBeFalse()
        ->and(columnResolver()->resolvePdfColumn($invoice, [], 'separate_out_of_stock', true))->toBeTrue();
});
