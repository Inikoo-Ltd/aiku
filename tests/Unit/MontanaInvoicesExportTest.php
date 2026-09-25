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

function montanaRow(Invoice $invoice): array
{
    $export = new MontanaInvoicesExport(new Organisation());

    return array_combine($export->headings(), $export->map($invoice));
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
