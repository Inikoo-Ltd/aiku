<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Accounting\EInvoice\BuildUblInvoice;
use App\Actions\Accounting\EInvoice\IsInvoiceInEInvoiceScope;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Helpers\TaxCategories\TaxCategoryTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceTransaction;
use App\Models\Catalogue\HistoricAsset;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Helpers\Address;
use App\Models\Helpers\Currency;
use App\Models\Helpers\TaxCategory;
use App\Models\Helpers\TaxNumber;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

function ublTaxCategory(TaxCategoryTypeEnum $type, float $rate, string $label = 'SK-SR23'): TaxCategory
{
    $taxCategory = new TaxCategory();
    $taxCategory->forceFill(['type' => $type, 'rate' => $rate, 'label' => $label, 'name' => $label]);

    return $taxCategory;
}

function ublLine(float $quantity, float $net, ?string $code, TaxCategory $taxCategory): InvoiceTransaction
{
    $transaction = new InvoiceTransaction();
    $transaction->forceFill(['quantity' => $quantity, 'net_amount' => $net, 'model_type' => 'Product']);
    $transaction->setRelation('taxCategory', $taxCategory);

    $historicAsset = null;
    if ($code) {
        $historicAsset = new HistoricAsset();
        $historicAsset->forceFill(['code' => $code, 'name' => "Item $code"]);
    }
    $transaction->setRelation('historicAsset', $historicAsset);

    return $transaction;
}

function ublInvoice(array $lines, float $net, float $tax, InvoiceTypeEnum $type = InvoiceTypeEnum::INVOICE, float $paid = 0, ?Invoice $original = null): Invoice
{
    $address = new Address();
    $address->forceFill(['address_line_1' => 'Hlavná 1', 'locality' => 'Bratislava', 'postal_code' => '81101', 'country_code' => 'SK']);

    $shopTaxNumber = new TaxNumber();
    $shopTaxNumber->forceFill(['country_code' => 'SK', 'number' => '2120525440']);

    $shop = new Shop();
    $shop->forceFill(['identity_document_number' => '50920600']);
    $shop->setRelation('taxNumber', $shopTaxNumber);
    $shop->setRelation('address', $address);

    $organisation = new Organisation();
    $organisation->forceFill(['name' => 'Ancient Wisdom s.r.o']);

    $currency = new Currency();
    $currency->forceFill(['code' => 'EUR']);

    $customer = new Customer();
    $customer->forceFill(['reference' => '123456', 'name' => 'Obchod s.r.o.']);

    $invoice = new Invoice();
    $invoice->forceFill([
        'type'           => $type,
        'reference'      => $type === InvoiceTypeEnum::REFUND ? 'SK-R1' : 'SK-1',
        'date'           => Carbon::parse('2027-01-04'),
        'net_amount'     => $net,
        'tax_amount'     => $tax,
        'total_amount'   => $net + $tax,
        'payment_amount' => $paid,
        'tax_number'               => 'SK 2120643690',
        'tax_number_valid'         => true,
        'identity_document_number' => '12345678',
        'customer_name'            => 'Obchod s.r.o.',
    ]);
    $invoice->setRelation('invoiceTransactions', new Collection($lines));
    $invoice->setRelation('currency', $currency);
    $invoice->setRelation('organisation', $organisation);
    $invoice->setRelation('shop', $shop);
    $invoice->setRelation('address', $address);
    $invoice->setRelation('customer', $customer);
    $invoice->setRelation('originalInvoice', $original);

    return $invoice;
}

function ublParse(string $xml): SimpleXMLElement
{
    $document = simplexml_load_string($xml);
    $document->registerXPathNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
    $document->registerXPathNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');

    return $document;
}

function ublValue(SimpleXMLElement $document, string $path): string
{
    return (string)($document->xpath($path)[0] ?? '');
}

test('a Slovak invoice becomes a Peppol BIS 3 invoice whose totals match the invoice', function () {
    $sk23  = ublTaxCategory(TaxCategoryTypeEnum::STANDARD, 0.23);
    $lines = [ublLine(3, 30.00, 'AWB-01', $sk23), ublLine(1, 12.50, 'AWB-02', $sk23), ublLine(1, 5.00, null, $sk23)];

    $document = ublParse(BuildUblInvoice::run(ublInvoice($lines, 47.50, 10.93, paid: 20)));

    expect($document->getName())->toBe('Invoice')
        ->and(ublValue($document, '/*/cbc:CustomizationID'))->toBe(BuildUblInvoice::CUSTOMIZATION_ID)
        ->and(ublValue($document, '/*/cbc:InvoiceTypeCode'))->toBe('380')
        ->and(ublValue($document, '//cac:AccountingSupplierParty//cbc:EndpointID'))->toBe('2120525440')
        ->and(ublValue($document, '//cac:AccountingSupplierParty//cbc:EndpointID/@schemeID'))->toBe('0245')
        ->and(ublValue($document, '//cac:AccountingSupplierParty//cac:PartyTaxScheme/cbc:CompanyID'))->toBe('SK2120525440')
        ->and(ublValue($document, '//cac:AccountingCustomerParty//cbc:EndpointID'))->toBe('2120643690')
        ->and(ublValue($document, '//cac:AccountingCustomerParty//cac:PartyTaxScheme/cbc:CompanyID'))->toBe('SK2120643690')
        ->and(ublValue($document, '//cac:AccountingSupplierParty//cac:PartyLegalEntity/cbc:CompanyID'))->toBe('50920600')
        ->and(ublValue($document, '//cac:AccountingCustomerParty//cac:PartyLegalEntity/cbc:CompanyID[@schemeID="0158"]'))->toBe('12345678')
        ->and(ublValue($document, '/*/cac:TaxTotal/cbc:TaxAmount'))->toBe('10.93')
        ->and(ublValue($document, '//cac:TaxSubtotal/cac:TaxCategory/cbc:ID'))->toBe('S')
        ->and(ublValue($document, '//cac:TaxSubtotal/cac:TaxCategory/cbc:Percent'))->toBe('23')
        ->and(ublValue($document, '//cac:LegalMonetaryTotal/cbc:TaxInclusiveAmount'))->toBe('58.43')
        ->and(ublValue($document, '//cac:LegalMonetaryTotal/cbc:PrepaidAmount'))->toBe('20.00')
        ->and(ublValue($document, '//cac:LegalMonetaryTotal/cbc:PayableAmount'))->toBe('38.43')
        ->and($document->xpath('//cac:InvoiceLine'))->toHaveCount(3)
        ->and(ublValue($document, '//cac:InvoiceLine[1]/cac:Price/cbc:PriceAmount'))->toBe('10')
        ->and(ublValue($document, '//cac:InvoiceLine[3]/cac:Item/cbc:Name'))->toBe('Product');
});

test('a refund becomes a credit note with positive amounts that points to the original invoice', function () {
    $sk23     = ublTaxCategory(TaxCategoryTypeEnum::STANDARD, 0.23);
    $original = ublInvoice([], 0, 0);

    $refund                   = ublInvoice([ublLine(-2, -20.00, 'AWB-01', $sk23)], -20.00, -4.60, InvoiceTypeEnum::REFUND, original: $original);
    $refund->tax_liability_at = Carbon::parse('2027-01-03');

    $xml      = BuildUblInvoice::run($refund);
    $document = ublParse($xml);

    expect($document->getName())->toBe('CreditNote')
        ->and(strpos($xml, '<cbc:TaxPointDate>'))->toBeLessThan(strpos($xml, '<cbc:CreditNoteTypeCode>'))
        ->and(ublValue($document, '/*/cbc:CreditNoteTypeCode'))->toBe('381')
        ->and(ublValue($document, '//cac:BillingReference/cac:InvoiceDocumentReference/cbc:ID'))->toBe('SK-1')
        ->and(ublValue($document, '//cac:CreditNoteLine/cbc:CreditedQuantity'))->toBe('2')
        ->and(ublValue($document, '//cac:CreditNoteLine/cbc:LineExtensionAmount'))->toBe('20.00')
        ->and(ublValue($document, '//cac:LegalMonetaryTotal/cbc:PayableAmount'))->toBe('24.60')
        ->and($document->xpath('//cac:PaymentTerms/cbc:Note'))->toHaveCount(1);
});

test('an invoice whose stored totals differ from its lines is refused', function () {
    $sk23 = ublTaxCategory(TaxCategoryTypeEnum::STANDARD, 0.23);

    BuildUblInvoice::run(ublInvoice([ublLine(1, 10.00, 'AWB-01', $sk23)], 10.00, 2.40));
})->throws(RuntimeException::class, 'totals do not match');

test('a buyer whose tax number is not a validated VAT number needs a Peppol ID', function () {
    $sk23    = ublTaxCategory(TaxCategoryTypeEnum::STANDARD, 0.23);
    $invoice = ublInvoice([ublLine(1, 10.00, 'AWB-01', $sk23)], 10.00, 2.30);
    $invoice->tax_number_valid = false;

    expect(fn () => BuildUblInvoice::run($invoice))->toThrow(RuntimeException::class, 'no Peppol ID');

    $invoice->customer->peppol_id = '0245:2120643690';
    $document = ublParse(BuildUblInvoice::run($invoice));

    expect(ublValue($document, '//cac:AccountingCustomerParty//cbc:EndpointID'))->toBe('2120643690')
        ->and($document->xpath('//cac:AccountingCustomerParty//cac:PartyTaxScheme'))->toBeEmpty()
        ->and($document->xpath('//cac:AccountingSupplierParty//cac:PartyTaxScheme'))->toHaveCount(1);
});

test('a refund without its original invoice is refused', function () {
    $sk23 = ublTaxCategory(TaxCategoryTypeEnum::STANDARD, 0.23);

    BuildUblInvoice::run(ublInvoice([ublLine(-1, -10.00, 'AWB-01', $sk23)], -10.00, -2.30, InvoiceTypeEnum::REFUND));
})->throws(RuntimeException::class, 'no original invoice');

test('a customer without a Slovak VAT number cannot be addressed on Peppol', function () {
    $sk23    = ublTaxCategory(TaxCategoryTypeEnum::STANDARD, 0.23);
    $invoice = ublInvoice([ublLine(1, 10.00, 'AWB-01', $sk23)], 10.00, 2.30);
    $invoice->tax_number = null;

    BuildUblInvoice::run($invoice);
})->throws(RuntimeException::class, 'no Peppol ID');

test('tax category types map to EN 16931 VAT categories', function () {
    expect(BuildUblInvoice::ublTaxCategory(ublTaxCategory(TaxCategoryTypeEnum::STANDARD, 0))['code'])->toBe('Z')
        ->and(BuildUblInvoice::ublTaxCategory(ublTaxCategory(TaxCategoryTypeEnum::EXEMPT, 0, ''))['exemption_reason'])->toBe('Exempt from VAT')
        ->and(fn () => BuildUblInvoice::ublTaxCategory(ublTaxCategory(TaxCategoryTypeEnum::EU_VTC, 0)))->toThrow(RuntimeException::class)
        ->and(BuildUblInvoice::participantId('sk 2120643690'))->toBe(['scheme' => '0245', 'id' => '2120643690'])
        ->and(BuildUblInvoice::participantId('CZ12345678'))->toBeNull();
});

test('a Peppol ID stored on the customer wins over the tax number', function () {
    $sk23    = ublTaxCategory(TaxCategoryTypeEnum::STANDARD, 0.23);
    $invoice = ublInvoice([ublLine(1, 10.00, 'AWB-01', $sk23)], 10.00, 2.30);
    $invoice->tax_number = null;
    $invoice->customer->peppol_id = '0245:2121551498';

    $document = ublParse(BuildUblInvoice::run($invoice));

    expect(ublValue($document, '//cac:AccountingCustomerParty//cbc:EndpointID'))->toBe('2121551498')
        ->and(ublValue($document, '//cac:AccountingCustomerParty//cbc:EndpointID/@schemeID'))->toBe('0245')
        ->and($document->xpath('//cac:AccountingCustomerParty//cac:PartyTaxScheme'))->toBeEmpty()
        ->and(BuildUblInvoice::parsePeppolId('0245 2121551498'))->toBeNull();
});

test('only business invoices addressed in an e-invoice country of the organisation are in scope', function () {
    $sk23    = ublTaxCategory(TaxCategoryTypeEnum::STANDARD, 0.23);
    $invoice = ublInvoice([ublLine(1, 10.00, 'AWB-01', $sk23)], 10.00, 2.30);

    expect(IsInvoiceInEInvoiceScope::run($invoice))->toBeFalse();

    $invoice->organisation->settings = ['e_invoicing' => ['countries' => ['SK']]];
    expect(IsInvoiceInEInvoiceScope::run($invoice))->toBeTrue();

    $invoice->tax_number               = null;
    $invoice->identity_document_number = '54018579';
    expect(IsInvoiceInEInvoiceScope::run($invoice))->toBeTrue();

    $invoice->identity_document_number = 'Slovensko';
    expect(IsInvoiceInEInvoiceScope::run($invoice))->toBeFalse();

    $invoice->customer->company_name = 'Obchod s.r.o.';
    expect(IsInvoiceInEInvoiceScope::run($invoice))->toBeTrue();
    $invoice->customer->company_name = null;

    $invoice->tax_number = 'SK 2120643690';
    $invoice->address->country_code = 'CZ';
    expect(IsInvoiceInEInvoiceScope::run($invoice))->toBeFalse();

    $invoice->address->country_code = 'SK';
    $invoice->in_process = true;
    expect(IsInvoiceInEInvoiceScope::run($invoice))->toBeFalse();
});

test('a Slovak DIČ has 10 digits and is divisible by 11', function () {
    expect(BuildUblInvoice::isSlovakDic('2120525440'))->toBeTrue()
        ->and(BuildUblInvoice::isSlovakDic('1027033667'))->toBeTrue()
        ->and(BuildUblInvoice::isSlovakDic('2120525441'))->toBeFalse()
        ->and(BuildUblInvoice::isSlovakDic('0120525440'))->toBeFalse()
        ->and(BuildUblInvoice::isSlovakDic('212052544'))->toBeFalse();
});

test('the due date follows the customer payment terms', function () {
    $sk23    = ublTaxCategory(TaxCategoryTypeEnum::STANDARD, 0.23);
    $invoice = ublInvoice([ublLine(1, 10.00, 'AWB-01', $sk23)], 10.00, 2.30);

    expect(ublValue(ublParse(BuildUblInvoice::run($invoice)), '/*/cbc:DueDate'))->toBe('2027-01-04');

    $invoice->customer->payment_terms_days = 14;
    expect(ublValue(ublParse(BuildUblInvoice::run($invoice)), '/*/cbc:DueDate'))->toBe('2027-01-18');
});
