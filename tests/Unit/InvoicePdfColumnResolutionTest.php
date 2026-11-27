<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Accounting\Invoice\GetInvoicePdfColumns;
use App\Actions\Accounting\Invoice\WithInvoicesExport;
use App\Actions\Dropshipping\Ankorstore\ShipAnkorstoreOrder;
use App\Models\Accounting\Invoice;
use App\Models\Catalogue\Shop;
use App\Models\Dispatching\DeliveryNote;
use App\Helpers\NaturalLanguage;

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
        $line('FREE-01', 'Free gift', 'Bowls', '2520100000', 'MAR', 1, 0),
    ]), fn (string $tariffCode) => ['3307410000' => 'Incense'][$tariffCode] ?? null);

    expect($rows)->toHaveCount(3)
        ->and($rows[0])->toMatchArray(['tariff_code' => '2520100000', 'origin' => 'MAR', 'description' => 'Selenite Round Bowl *', 'codes' => 'SelB-01', 'quantity' => 1.0, 'net_amount' => 7.3])
        ->and($rows[1])->toMatchArray(['tariff_code' => '3307410000', 'origin' => 'IND', 'description' => 'Incense', 'codes' => 'SBIS-12, SBIS-09', 'quantity' => 7.0, 'net_amount' => 27.79])
        ->and($rows[2]['origin'])->toBe('PER');
});

test('the invoice prints a delivery address whose street was typed into the town box, and nothing for a blank one', function () {
    $invoice      = new Invoice();
    $deliveryNote = new DeliveryNote();
    $streetInTown = new \App\Models\Helpers\Address(['address_line_1' => '', 'locality' => 'Rear of 230 Church Lane', 'postal_code' => 'NR25 6LZ']);
    $placeholder  = new \App\Models\Helpers\Address(['address_line_1' => '0', 'locality' => '0', 'postal_code' => '0']);

    $deliveryNote->setRelation('deliveryAddress', $streetInTown);
    expect(\App\Actions\Accounting\Invoice\PdfInvoice::make()->invoicePdfDeliveryAddress($invoice, $deliveryNote, false))->toBe($streetInTown);

    $deliveryNote->setRelation('deliveryAddress', $placeholder);
    expect(\App\Actions\Accounting\Invoice\PdfInvoice::make()->invoicePdfDeliveryAddress($invoice, $deliveryNote, false))->toBeNull();
});

test('the English language override is accepted but never stored as a sticky pdf column', function () {
    expect(\App\Actions\Accounting\Invoice\PdfInvoice::make()->rules())->toHaveKey('lang')
        ->and(\App\Actions\Accounting\Invoice\GetInvoicePdfColumns::COLUMNS)->not->toHaveKey('lang');
});

test('an unticked column sent as 0 switches off a column the invoice had saved as on', function () {
    $invoice = invoiceWithColumns(['barcode' => true], ['barcode' => true, 'group_by_tariff_code' => false]);
    $invoice->data = ['pdf_columns' => ['barcode' => '0', 'group_by_tariff_code' => '1'] + $invoice->data['pdf_columns']];

    $columns = GetInvoicePdfColumns::run($invoice);

    expect($columns['barcode'])->toBeFalse()
        ->and($columns['group_by_tariff_code'])->toBeTrue();
});

test('boxes nobody weighed print the estimated weight instead', function (array $parcels, int $estimatedWeight, string $expected) {
    $deliveryNote                   = new DeliveryNote();
    $deliveryNote->parcels          = $parcels;
    $deliveryNote->estimated_weight = $estimatedWeight;

    expect($deliveryNote->getBestWeight())->toBe($expected);
})->with([
    'eight boxes at the default, 62.8 kg estimated' => [array_fill(0, 8, ['weight' => 1, 'dimensions' => [40, 40, 40]]), 62818, NaturalLanguage::make()->weight(62818)],
    'boxes at the default heavier than the estimate' => [array_fill(0, 3, ['weight' => 1]), 800, NaturalLanguage::make()->weight(3000)],
    'weighed boxes win over the estimate'           => [[['weight' => 12.5], ['weight' => 1]], 62818, NaturalLanguage::make()->weight(13500)],
    'no boxes'                                      => [[], 62818, NaturalLanguage::make()->weight(62818)],
    'a box left blank'                              => [[['weight' => 12.5], ['weight' => null, 'dimensions' => [40, 40, 40]]], 62818, NaturalLanguage::make()->weight(62818)],
]);

test('carriers get the estimate left after the weighed boxes, split over the boxes left blank', function (array $parcels, ?int $estimatedWeight, array $expectedWeights) {
    expect(array_column(DeliveryNote::parcelsWithCarrierWeights($parcels, $estimatedWeight), 'weight'))->toBe($expectedWeights);
})->with([
    'all weighed, untouched'              => [[['weight' => 12.5], ['weight' => 3]], 62818, [12.5, 3]],
    'one weighed, two blank'              => [[['weight' => 12.5], ['weight' => null], []], 62818, [12.5, 25.159, 25.159]],
    'weighed boxes already over estimate' => [[['weight' => 70], ['weight' => null]], 62818, [70, 1.0]],
    'pallet return, no estimate'          => [[['weight' => null], ['weight' => 4]], null, [1.0, 4]],
]);

test('Ankorstore gets the estimate for a box left blank, not 1 g', function () {
    $deliveryNote                   = new DeliveryNote();
    $deliveryNote->parcels          = [['weight' => null, 'dimensions' => [40, 30, 20]]];
    $deliveryNote->estimated_weight = 6200;

    expect(array_column(ShipAnkorstoreOrder::make()->parcels($deliveryNote), 'weight'))->toBe([6200]);
});
