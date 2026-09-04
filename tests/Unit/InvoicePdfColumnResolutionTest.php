<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Accounting\Invoice\GetInvoicePdfColumns;
use App\Actions\Accounting\Invoice\WithInvoicesExport;
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

test('export by tariff code collapses lines into one row per tariff code and origin pair', function () {
    $line = function (string $code, string $name, string $family, ?string $tariff, ?string $origin, float $qty, float $net) {
        return (object)[
            'quantity'      => $qty,
            'net_amount'    => $net,
            'historicAsset' => (object)['code' => $code, 'name' => $name],
            'model'         => (object)['tariff_code' => $tariff, 'country_of_origin' => $origin, 'family' => (object)['name' => $family]],
        ];
    };

    $rows = (new class () {
        use WithInvoicesExport;
    })->groupTransactionsByTariffCodeAndOrigin(collect([
        $line('SBIS-12', 'Sandal Sticks', 'Incense', '3307410000', 'IND', 3, 10),
        $line('Msanto-05A', 'Palo Santo', 'Incense', '3307410000', 'PER', 1, 59.5),
        $line('SBIS-09', 'Rose Sticks', 'Incense', '3307410000', 'IND', 4, 17.79),
        $line('SelB-01', 'Selenite Round Bowl', 'Bowls', '2520100000', 'MAR', 1, 7.3),
        $line('OOS-01', 'Never shipped', 'Bowls', '2520100000', 'MAR', 0, 0),
    ]), fn (string $tariffCode) => ['3307410000' => 'Incense'][$tariffCode] ?? null);

    expect($rows)->toHaveCount(3)
        ->and($rows[0])->toMatchArray(['tariff_code' => '2520100000', 'origin' => 'MAR', 'description' => 'Selenite Round Bowl *', 'codes' => 'SelB-01', 'quantity' => 1.0, 'net_amount' => 7.3])
        ->and($rows[1])->toMatchArray(['tariff_code' => '3307410000', 'origin' => 'IND', 'description' => 'Incense', 'codes' => 'SBIS-12, SBIS-09', 'quantity' => 7.0, 'net_amount' => 27.79])
        ->and($rows[2]['origin'])->toBe('PER');
});
