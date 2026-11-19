<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Exports\Accounting\MontanaInvoicesExport;
use App\Models\Accounting\Invoice;
use App\Models\CRM\Customer;
use App\Models\Helpers\Address;
use App\Models\Helpers\Currency;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Organisation;

function montanaDropshipInvoice(?Invoice $originalInvoice = null): Invoice
{
    $customer = new Customer(['name' => 'Dropshipper']);
    $customer->setRelation('address', new Address(['country_code' => 'ES']));

    $order = new Order(['reference' => 'ORD-1']);
    $order->setRelation('deliveryAddress', new Address(['country_code' => 'DE']));

    $invoice = new Invoice([
        'reference'       => $originalInvoice ? 'ref-dse-1' : 'DESi1',
        'type'            => $originalInvoice ? InvoiceTypeEnum::REFUND : InvoiceTypeEnum::INVOICE,
        'net_amount'      => 100,
        'tax_amount'      => 19,
        'total_amount'    => 119,
        'goods_amount'    => 100,
        'date'            => '2026-09-01 10:00:00',
        'tax_category_id' => 22,
    ]);
    $invoice->setRelation('customer', $customer);
    $invoice->setRelation('address', new Address(['country_code' => 'AT']));
    $invoice->setRelation('deliveryAddress', null);
    $invoice->setRelation('order', $order);
    $invoice->setRelation('currency', new Currency(['code' => 'EUR']));
    $invoice->setRelation('originalInvoice', $originalInvoice);

    return $invoice;
}

function montanaRow(Invoice $invoice, array $breakdown = []): array
{
    $export = new class (new Organisation(), $breakdown) extends MontanaInvoicesExport {
        public function __construct(Organisation $parent, private array $breakdown)
        {
            parent::__construct($parent);
        }

        protected function taxBreakdown(Invoice $invoice): array
        {
            return $this->breakdown;
        }
    };

    return array_combine($export->headings(), $export->map($invoice));
}

function montanaSpanishInvoice(int $headerTaxCategoryId, string $lineTaxCategoryIds, float $net, float $tax): Invoice
{
    $invoice = montanaDropshipInvoice();
    $invoice->setRelation('address', new Address(['country_code' => 'ES']));
    $invoice->forceFill([
        'tax_category_id'       => $headerTaxCategoryId,
        'net_amount'            => $net,
        'tax_amount'            => $tax,
        'total_amount'          => $net + $tax,
        'goods_amount'          => $net,
        'line_tax_category_ids' => $lineTaxCategoryIds,
    ]);

    return $invoice;
}

function montanaTaxCodes(array $row): array
{
    return [
        'ES-SR'    => $row['Tax code: ES-SR'],
        'ES-SR+RE' => $row['Tax code: ES-SR+RE'],
        'ES-RR'    => $row['Tax code: ES-RR'],
        'ES-RR+RE' => $row['Tax code: ES-RR+RE'],
    ];
}

test('the country is the billing country fixed on the invoice, not the customer current address', function () {
    expect(montanaRow(montanaDropshipInvoice())['País'])->toBe('AT');
});

test('the shipping country comes from the order when the invoice has no delivery address', function () {
    expect(montanaRow(montanaDropshipInvoice())['País envío'])->toBe('DE');
});

test('a refund shows the reference of the invoice it refunds', function () {
    $original = new Invoice(['reference' => 'DESi1']);

    expect(montanaRow(montanaDropshipInvoice($original))['Factura original'])->toBe('DESi1')
        ->and(montanaRow(montanaDropshipInvoice())['Factura original'])->toBe('');
});

test('an invoice at 21% and 10% splits its VAT between ES-SR and ES-RR', function () {
    $row = montanaRow(montanaSpanishInvoice(30, '30,52', 134.05, 25.66), [
        ['tax_category_id' => 30, 'name' => 'IVA 21%', 'rate' => 0.21, 'net_amount' => 100.00, 'tax_amount' => 21.00],
        ['tax_category_id' => 52, 'name' => 'IVA 10%', 'rate' => 0.10, 'net_amount' => 34.05, 'tax_amount' => 4.66],
    ]);

    expect(montanaTaxCodes($row))->toBe(['ES-SR' => '21.00', 'ES-SR+RE' => '', 'ES-RR' => '4.66', 'ES-RR+RE' => ''])
        ->and($row['Impuestos'])->toBe('25.66')
        ->and($row['TIPO FRA'])->toBe('T3');
});

test('an invoice with recargo de equivalencia at both rates splits its VAT and stays T4', function () {
    $row = montanaRow(montanaSpanishInvoice(51, '51,53', 291.07, 66.28), [
        ['tax_category_id' => 51, 'name' => 'IVA+RE (26,2%)', 'rate' => 0.262, 'net_amount' => 200.00, 'tax_amount' => 52.40],
        ['tax_category_id' => 53, 'name' => 'IVA+RE (11,4%)', 'rate' => 0.114, 'net_amount' => 91.07, 'tax_amount' => 10.38],
    ]);

    expect(montanaTaxCodes($row))->toBe(['ES-SR' => '', 'ES-SR+RE' => '52.40', 'ES-RR' => '', 'ES-RR+RE' => '10.38'])
        ->and($row['TIPO FRA'])->toBe('T4');
});

test('a single rate invoice keeps its whole VAT in its own tax code', function () {
    $row = montanaRow(montanaSpanishInvoice(30, '30', 100, 21));

    expect(montanaTaxCodes($row))->toBe(['ES-SR' => '21.00', 'ES-SR+RE' => '', 'ES-RR' => '', 'ES-RR+RE' => '']);
});

test('an invoice whose lines do not add up to its stored VAT keeps the stored VAT in its own tax code', function () {
    $row = montanaRow(montanaSpanishInvoice(30, '30,52', 129.90, 27.28));

    expect(montanaTaxCodes($row))->toBe(['ES-SR' => '27.28', 'ES-SR+RE' => '', 'ES-RR' => '', 'ES-RR+RE' => '']);
});

test('a refund at two rates splits its negative VAT', function () {
    $invoice = montanaSpanishInvoice(30, '30,52', -134.05, -25.66);
    $invoice->type = InvoiceTypeEnum::REFUND;

    $row = montanaRow($invoice, [
        ['tax_category_id' => 30, 'name' => 'IVA 21%', 'rate' => 0.21, 'net_amount' => -100.00, 'tax_amount' => -21.00],
        ['tax_category_id' => 52, 'name' => 'IVA 10%', 'rate' => 0.10, 'net_amount' => -34.05, 'tax_amount' => -4.66],
    ]);

    expect(montanaTaxCodes($row))->toBe(['ES-SR' => '-21.00', 'ES-SR+RE' => '', 'ES-RR' => '-4.66', 'ES-RR+RE' => '']);
});

test('a rate whose lines carry no VAT leaves its tax code blank', function () {
    $row = montanaRow(montanaSpanishInvoice(30, '30,52', 153.33, 32.20), [
        ['tax_category_id' => 30, 'name' => 'IVA 21%', 'rate' => 0.21, 'net_amount' => 153.33, 'tax_amount' => 32.20],
        ['tax_category_id' => 52, 'name' => 'IVA 10%', 'rate' => 0.10, 'net_amount' => 0.00, 'tax_amount' => 0.00],
    ]);

    expect(montanaTaxCodes($row))->toBe(['ES-SR' => '32.20', 'ES-SR+RE' => '', 'ES-RR' => '', 'ES-RR+RE' => '']);
});

test('a recargo de equivalencia invoice without VAT keeps its zero VAT type', function () {
    expect(montanaRow(montanaSpanishInvoice(51, '51', 100, 0))['TIPO FRA'])->toBe('T2');
});
