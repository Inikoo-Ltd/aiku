<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\EInvoice;

use DOMDocument;
use DOMXPath;
use Lorisleiva\Actions\Concerns\AsObject;
use RuntimeException;

/**
 * Reads the header of a Peppol BIS Billing 3.0 Invoice or CreditNote (UBL 2.1): who sent it, its number, dates and
 * totals. Lines are not read. Amounts stay decimal strings as written in the document.
 */
class ReadUblDocument
{
    use AsObject;

    private const array NAMESPACES = [
        'inv' => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
        'cn'  => 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2',
        'cac' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
        'cbc' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
    ];

    /**
     * @return array{document_type: string, document_number: string|null, issue_date: string|null, due_date: string|null, currency_code: string|null, net_amount: string|null, tax_amount: string|null, total_amount: string|null, payable_amount: string|null, supplier_name: string|null, supplier_peppol_id: string|null, supplier_vat_number: string|null, supplier_tax_number: string|null, supplier_company_number: string|null, buyer_reference: string|null, order_reference: string|null, billing_reference: string|null}
     */
    public function handle(string $xml): array
    {
        $dom      = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded   = $dom->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            throw new RuntimeException('Not a readable XML document');
        }

        $xpath = new DOMXPath($dom);
        foreach (self::NAMESPACES as $prefix => $uri) {
            $xpath->registerNamespace($prefix, $uri);
        }

        $root = $xpath->query('//inv:Invoice | //cn:CreditNote')->item(0);
        if (!$root) {
            throw new RuntimeException('No UBL Invoice or CreditNote in the document');
        }
        $isCreditNote = $root->namespaceURI === self::NAMESPACES['cn'];

        $text = function (string $path) use ($xpath, $root): ?string {
            $value = trim((string)$xpath->evaluate("string($path)", $root));

            return $value === '' ? null : $value;
        };

        $currency = $text('cbc:DocumentCurrencyCode');
        $supplier = 'cac:AccountingSupplierParty/cac:Party';

        $endpointScheme = $text("$supplier/cbc:EndpointID/@schemeID");
        $endpoint       = $text("$supplier/cbc:EndpointID");

        $orderReference = $text('cac:OrderReference/cbc:ID');

        return [
            'document_type'           => $isCreditNote ? 'credit_note' : 'invoice',
            'document_number'         => $text('cbc:ID'),
            'issue_date'              => $text('cbc:IssueDate'),
            'due_date'                => $text('cbc:DueDate') ?? $text('cac:PaymentMeans/cbc:PaymentDueDate'),
            'currency_code'           => $currency,
            'net_amount'              => $text('cac:LegalMonetaryTotal/cbc:TaxExclusiveAmount'),
            'tax_amount'              => $text($currency ? "cac:TaxTotal/cbc:TaxAmount[@currencyID='$currency']" : 'cac:TaxTotal/cbc:TaxAmount'),
            'total_amount'            => $text('cac:LegalMonetaryTotal/cbc:TaxInclusiveAmount'),
            'payable_amount'          => $text('cac:LegalMonetaryTotal/cbc:PayableAmount'),
            'supplier_name'           => $text("$supplier/cac:PartyLegalEntity/cbc:RegistrationName") ?? $text("$supplier/cac:PartyName/cbc:Name"),
            'supplier_peppol_id'      => $endpoint ? ($endpointScheme ? "$endpointScheme:$endpoint" : $endpoint) : null,
            'supplier_vat_number'     => $text("$supplier/cac:PartyTaxScheme[cac:TaxScheme/cbc:ID='VAT']/cbc:CompanyID"),
            'supplier_tax_number'     => $text("$supplier/cac:PartyTaxScheme[cac:TaxScheme/cbc:ID!='VAT']/cbc:CompanyID") ?? ($endpointScheme === '0245' ? $endpoint : null),
            'supplier_company_number' => $text("$supplier/cac:PartyLegalEntity/cbc:CompanyID"),
            'buyer_reference'         => $text('cbc:BuyerReference'),
            'order_reference'         => $orderReference === 'NA' ? null : $orderReference,
            'billing_reference'       => $text('cac:BillingReference/cac:InvoiceDocumentReference/cbc:ID'),
        ];
    }
}
