<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\EInvoice;

use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Helpers\TaxCategories\TaxCategoryTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceTransaction;
use App\Models\Helpers\Address;
use App\Models\Helpers\TaxCategory;
use Carbon\CarbonInterface;
use Closure;
use Lorisleiva\Actions\Concerns\AsObject;
use RuntimeException;
use XMLWriter;

class BuildUblInvoice
{
    use AsObject;

    public const string CUSTOMIZATION_ID = 'urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0';
    public const string PROFILE_ID = 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0';

    public const string PEPPOL_SCHEME = '0245';
    public const string PEPPOL_ID_PATTERN = '/^\d{4}:[A-Za-z0-9]{1,50}$/';

    private XMLWriter $xml;
    private string $currency;
    private bool $isCreditNote;
    private string $timezone;

    public function handle(Invoice $invoice): string
    {
        $this->timezone = $invoice->shop->timezoneName();
        $invoice->loadMissing(['invoiceTransactions.historicAsset', 'invoiceTransactions.taxCategory', 'currency', 'organisation', 'shop.taxNumber', 'shop.address', 'address', 'customer', 'originalInvoice']);

        if ($invoice->in_process) {
            throw new RuntimeException("Invoice $invoice->reference is not finalised");
        }

        $this->isCreditNote = $invoice->type === InvoiceTypeEnum::REFUND;
        $this->currency     = $invoice->currency->code;
        // ponytail: EUR only; other currencies need TaxCurrencyCode and a second TaxTotal in EUR from org_exchange
        if ($this->currency !== 'EUR') {
            throw new RuntimeException("Invoice $invoice->reference is in $this->currency, e-invoices are EUR only");
        }
        $sign               = $this->isCreditNote ? -1 : 1;

        $lines     = $invoice->invoiceTransactions->filter(fn (InvoiceTransaction $transaction) => (float)$transaction->net_amount != 0.0 || (float)$transaction->quantity != 0.0)->values();
        $netAmount = round($lines->sum(fn (InvoiceTransaction $transaction) => round($sign * (float)$transaction->net_amount, 2)), 2);
        $subtotals = $this->taxSubtotals($lines, $sign);
        $taxAmount = round(array_sum(array_column($subtotals, 'tax')), 2);

        $this->assertMatchesInvoice($invoice, $sign * $netAmount, $sign * $taxAmount);

        $totalAmount   = round($netAmount + $taxAmount, 2);
        $prepaidAmount = round(min(max(abs((float)$invoice->payment_amount), 0), max($totalAmount, 0)), 2);

        $root = $this->isCreditNote ? 'CreditNote' : 'Invoice';

        $this->xml = new XMLWriter();
        $this->xml->openMemory();
        $this->xml->setIndent(true);
        $this->xml->startDocument('1.0', 'UTF-8');
        $this->xml->startElement($root);
        $this->xml->writeAttribute('xmlns', "urn:oasis:names:specification:ubl:schema:xsd:$root-2");
        $this->xml->writeAttribute('xmlns:cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $this->xml->writeAttribute('xmlns:cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

        $this->xml->writeElement('cbc:CustomizationID', self::CUSTOMIZATION_ID);
        $this->xml->writeElement('cbc:ProfileID', self::PROFILE_ID);
        $this->xml->writeElement('cbc:ID', $invoice->reference);
        $this->xml->writeElement('cbc:IssueDate', $this->day($invoice->date));
        if (!$this->isCreditNote) {
            $dueInDays = $invoice->is_pastpay ? (int)data_get($invoice->order?->data, 'pastpay.termDays', 30) : max((int)$invoice->customer?->payment_terms_days, 0);
            $this->xml->writeElement('cbc:DueDate', $this->day($invoice->date->copy()->addDays($dueInDays)));
        }
        if ($this->isCreditNote) {
            $this->taxPointDate($invoice);
            $this->xml->writeElement('cbc:CreditNoteTypeCode', '381');
        } else {
            $this->xml->writeElement('cbc:InvoiceTypeCode', '380');
            $this->taxPointDate($invoice);
        }
        $this->xml->writeElement('cbc:DocumentCurrencyCode', $this->currency);
        $this->xml->writeElement('cbc:BuyerReference', $invoice->customer?->reference ?: (string)$invoice->customer_id);

        if ($this->isCreditNote && !$invoice->originalInvoice) {
            throw new RuntimeException("Credit note $invoice->reference has no original invoice to refer to");
        }

        if ($this->isCreditNote) {
            $this->xml->startElement('cac:BillingReference');
            $this->xml->startElement('cac:InvoiceDocumentReference');
            $this->xml->writeElement('cbc:ID', $invoice->originalInvoice->reference);
            $this->xml->writeElement('cbc:IssueDate', $this->day($invoice->originalInvoice->date));
            $this->xml->endElement();
            $this->xml->endElement();
        }

        $this->party(
            'cac:AccountingSupplierParty',
            participant: self::participantId($invoice->shop->taxNumber ? $invoice->shop->taxNumber->country_code.$invoice->shop->taxNumber->number : null),
            vatNumber: $invoice->shop->taxNumber ? $invoice->shop->taxNumber->country_code.$invoice->shop->taxNumber->number : null,
            name: $invoice->organisation->name,
            address: $invoice->shop->address ?? $invoice->organisation->address,
            isVatRegistered: true,
            companyNumber: $invoice->shop->identity_document_number
        );

        $this->party(
            'cac:AccountingCustomerParty',
            participant: self::buyerParticipantId($invoice),
            vatNumber: $invoice->tax_number,
            name: $invoice->fiscal_name ?: ($invoice->customer_name ?: $invoice->customer->name),
            address: $invoice->address,
            isVatRegistered: $invoice->tax_number_valid === true,
            companyNumber: $invoice->identity_document_number
        );

        $this->xml->startElement('cac:TaxTotal');
        $this->amount('cbc:TaxAmount', $taxAmount);
        foreach ($subtotals as $subtotal) {
            $this->xml->startElement('cac:TaxSubtotal');
            $this->amount('cbc:TaxableAmount', $subtotal['taxable']);
            $this->amount('cbc:TaxAmount', $subtotal['tax']);
            $this->taxCategory('cac:TaxCategory', $subtotal['category'], true);
            $this->xml->endElement();
        }
        $this->xml->endElement();

        $this->xml->startElement('cac:LegalMonetaryTotal');
        $this->amount('cbc:LineExtensionAmount', $netAmount);
        $this->amount('cbc:TaxExclusiveAmount', $netAmount);
        $this->amount('cbc:TaxInclusiveAmount', $totalAmount);
        if ($prepaidAmount > 0) {
            $this->amount('cbc:PrepaidAmount', $prepaidAmount);
        }
        $this->amount('cbc:PayableAmount', round($totalAmount - $prepaidAmount, 2));
        $this->xml->endElement();

        foreach ($lines as $index => $transaction) {
            $this->line($index + 1, $transaction, $sign);
        }

        $this->xml->endElement();
        $this->xml->endDocument();

        return $this->xml->outputMemory();
    }

    /**
     * @return array{scheme: string, id: string}|null
     */
    public static function participantId(?string $vatNumber): ?array
    {
        $vatNumber = strtoupper((string)preg_replace('/[^A-Za-z0-9]/', '', (string)$vatNumber));
        if (!preg_match('/^SK(\d{10})$/', $vatNumber, $matches)) {
            return null;
        }

        return ['scheme' => self::PEPPOL_SCHEME, 'id' => $matches[1]];
    }

    /**
     * Slovak DIČ: 10 digits, no leading zero, divisible by 11 (every validated Slovak VAT number passes; catches most typos).
     */
    public static function isSlovakDic(string $dic): bool
    {
        return preg_match('/^[1-9]\d{9}$/', $dic) && (int)$dic % 11 === 0;
    }

    /**
     * Validation rule for a stored Peppol ID: scheme:number, and a well-formed DIČ when the scheme is the Slovak one.
     *
     * @return array<int, mixed>
     */
    public static function peppolIdRules(): array
    {
        return [
            'sometimes', 'nullable', 'string', 'regex:'.self::PEPPOL_ID_PATTERN,
            function (string $attribute, mixed $value, Closure $fail) {
                if (is_string($value) && str_starts_with($value, self::PEPPOL_SCHEME.':') && !self::isSlovakDic(substr($value, 5))) {
                    $fail(__('This is not a valid Slovak DIČ. Please check the number.'));
                }
            },
        ];
    }

    /**
     * @return array{scheme: string, id: string}|null
     */
    public static function parsePeppolId(?string $peppolId): ?array
    {
        if (!$peppolId || !preg_match(self::PEPPOL_ID_PATTERN, $peppolId)) {
            return null;
        }
        [$scheme, $id] = explode(':', $peppolId, 2);

        return ['scheme' => $scheme, 'id' => $id];
    }

    /**
     * @return array{scheme: string, id: string}|null
     */
    public static function buyerParticipantId(Invoice $invoice): ?array
    {
        return ($invoice->tax_number_valid === true ? self::participantId($invoice->tax_number) : null)
            ?? self::parsePeppolId($invoice->customer?->peppol_id);
    }

    /**
     * @return array{code: string, percent: float|null, exemption_code: string|null, exemption_reason: string|null}
     */
    public static function ublTaxCategory(TaxCategory $taxCategory): array
    {
        $rate = round((float)$taxCategory->rate * 100, 2);

        return match ($taxCategory->type) {
            TaxCategoryTypeEnum::STANDARD, TaxCategoryTypeEnum::REDUCED, TaxCategoryTypeEnum::SPECIAL, TaxCategoryTypeEnum::REDUCED_SPECIAL => $rate > 0
                ? ['code' => 'S', 'percent' => $rate, 'exemption_code' => null, 'exemption_reason' => null]
                : ['code' => 'Z', 'percent' => 0.0, 'exemption_code' => null, 'exemption_reason' => null],
            TaxCategoryTypeEnum::EXEMPT => ['code' => 'E', 'percent' => 0.0, 'exemption_code' => null, 'exemption_reason' => $taxCategory->name ?: 'Exempt from VAT'],
            // ponytail: domestic only; intra-EU (K) and export (G) need cac:Delivery country and date, add with the 2030 cross-border scope
            default => throw new RuntimeException("Tax category $taxCategory->label has no e-invoice VAT category"),
        };
    }

    /**
     * @return array<string, array{category: array{code: string, percent: float|null, exemption_code: string|null, exemption_reason: string|null}, taxable: float, tax: float}>
     */
    private function taxSubtotals(iterable $lines, int $sign): array
    {
        $subtotals = [];
        foreach ($lines as $transaction) {
            $category = self::ublTaxCategory($transaction->taxCategory);
            $key      = $category['code'].'|'.$category['percent'];

            $subtotals[$key] ??= ['category' => $category, 'taxable' => 0.0, 'tax' => 0.0];
            $subtotals[$key]['taxable'] += round($sign * (float)$transaction->net_amount, 2);
        }

        foreach ($subtotals as $key => $subtotal) {
            $subtotals[$key]['taxable'] = round($subtotal['taxable'], 2);
            $subtotals[$key]['tax']     = round($subtotals[$key]['taxable'] * ($subtotal['category']['percent'] ?? 0) / 100, 2);
        }

        return $subtotals;
    }

    private function assertMatchesInvoice(Invoice $invoice, float $netAmount, float $taxAmount): void
    {
        if (abs($netAmount - (float)$invoice->net_amount) > 0.005 || abs($taxAmount - (float)$invoice->tax_amount) > 0.005) {
            throw new RuntimeException(sprintf(
                'Invoice %s totals do not match its lines (net %s vs %s, VAT %s vs %s)',
                $invoice->reference,
                $invoice->net_amount,
                $netAmount,
                $invoice->tax_amount,
                $taxAmount
            ));
        }
    }

    /**
     * @param array{scheme: string, id: string}|null $participant
     */
    private function party(string $element, ?array $participant, ?string $vatNumber, string $name, ?Address $address, bool $isVatRegistered, ?string $companyNumber): void
    {
        if (!$participant) {
            throw new RuntimeException("$name has no Peppol ID or Slovak tax number to address it on Peppol");
        }
        if (!$address) {
            throw new RuntimeException("$name has no address");
        }
        $vatNumber = strtoupper((string)preg_replace('/[^A-Za-z0-9]/', '', (string)$vatNumber));

        $this->xml->startElement($element);
        $this->xml->startElement('cac:Party');

        $this->xml->startElement('cbc:EndpointID');
        $this->xml->writeAttribute('schemeID', $participant['scheme']);
        $this->xml->text($participant['id']);
        $this->xml->endElement();

        $this->xml->startElement('cac:PostalAddress');
        $this->optional('cbc:StreetName', $address->address_line_1);
        $this->optional('cbc:AdditionalStreetName', $address->address_line_2);
        $this->optional('cbc:CityName', $address->locality);
        $this->optional('cbc:PostalZone', $address->postal_code);
        $this->optional('cbc:CountrySubentity', $address->administrative_area);
        $this->xml->startElement('cac:Country');
        $this->xml->writeElement('cbc:IdentificationCode', $address->country_code);
        $this->xml->endElement();
        $this->xml->endElement();

        if ($isVatRegistered && preg_match('/^[A-Z]{2}[A-Z0-9]+$/', $vatNumber)) {
            $this->xml->startElement('cac:PartyTaxScheme');
            $this->xml->writeElement('cbc:CompanyID', $vatNumber);
            $this->xml->startElement('cac:TaxScheme');
            $this->xml->writeElement('cbc:ID', 'VAT');
            $this->xml->endElement();
            $this->xml->endElement();
        }

        $this->xml->startElement('cac:PartyLegalEntity');
        $this->xml->writeElement('cbc:RegistrationName', $name);
        $companyNumber = preg_replace('/\D/', '', (string)$companyNumber);
        if (strlen($companyNumber) === 8) {
            $this->xml->startElement('cbc:CompanyID');
            $this->xml->writeAttribute('schemeID', '0158');
            $this->xml->text($companyNumber);
            $this->xml->endElement();
        }
        $this->xml->endElement();

        $this->xml->endElement();
        $this->xml->endElement();
    }

    private function line(int $id, InvoiceTransaction $transaction, int $sign): void
    {
        $netAmount = round($sign * (float)$transaction->net_amount, 2);
        $quantity  = abs((float)$transaction->quantity) ?: 1.0;
        if ($netAmount < 0) {
            $quantity = -$quantity;
        }

        $this->xml->startElement($this->isCreditNote ? 'cac:CreditNoteLine' : 'cac:InvoiceLine');
        $this->xml->writeElement('cbc:ID', (string)$id);

        $this->xml->startElement($this->isCreditNote ? 'cbc:CreditedQuantity' : 'cbc:InvoicedQuantity');
        $this->xml->writeAttribute('unitCode', 'C62');
        $this->xml->text($this->decimal($quantity, 6));
        $this->xml->endElement();

        $this->amount('cbc:LineExtensionAmount', $netAmount);

        $this->xml->startElement('cac:Item');
        $this->xml->writeElement('cbc:Name', $transaction->historicAsset?->name ?: ($transaction->historicAsset?->code ?: class_basename((string)$transaction->model_type)));
        if ($transaction->historicAsset?->code) {
            $this->xml->startElement('cac:SellersItemIdentification');
            $this->xml->writeElement('cbc:ID', $transaction->historicAsset->code);
            $this->xml->endElement();
        }
        $this->taxCategory('cac:ClassifiedTaxCategory', self::ublTaxCategory($transaction->taxCategory), false);
        $this->xml->endElement();

        $this->xml->startElement('cac:Price');
        $this->xml->startElement('cbc:PriceAmount');
        $this->xml->writeAttribute('currencyID', $this->currency);
        $this->xml->text($this->decimal(abs($netAmount / $quantity), 6));
        $this->xml->endElement();
        $this->xml->endElement();

        $this->xml->endElement();
    }

    /**
     * @param array{code: string, percent: float|null, exemption_code: string|null, exemption_reason: string|null} $category
     */
    private function taxCategory(string $element, array $category, bool $withExemption): void
    {
        $this->xml->startElement($element);
        $this->xml->writeElement('cbc:ID', $category['code']);
        if ($category['percent'] !== null) {
            $this->xml->writeElement('cbc:Percent', $this->decimal($category['percent'], 2));
        }
        if ($withExemption) {
            $this->optional('cbc:TaxExemptionReasonCode', $category['exemption_code']);
            $this->optional('cbc:TaxExemptionReason', $category['exemption_reason']);
        }
        $this->xml->startElement('cac:TaxScheme');
        $this->xml->writeElement('cbc:ID', 'VAT');
        $this->xml->endElement();
        $this->xml->endElement();
    }

    private function taxPointDate(Invoice $invoice): void
    {
        if ($invoice->tax_liability_at) {
            $this->xml->writeElement('cbc:TaxPointDate', $this->day($invoice->tax_liability_at));
        }
    }

    /**
     * The day as printed on the PDF invoice, in the shop's timezone.
     */
    private function day(CarbonInterface $date): string
    {
        return $date->copy()->setTimezone($this->timezone)->toDateString();
    }

    private function amount(string $element, float $amount): void
    {
        $this->xml->startElement($element);
        $this->xml->writeAttribute('currencyID', $this->currency);
        $this->xml->text(number_format($amount, 2, '.', ''));
        $this->xml->endElement();
    }

    private function optional(string $element, ?string $value): void
    {
        if ($value !== null && trim($value) !== '') {
            $this->xml->writeElement($element, trim($value));
        }
    }

    private function decimal(float $value, int $decimals): string
    {
        $formatted = rtrim(rtrim(number_format($value, $decimals, '.', ''), '0'), '.');

        return $formatted === '-0' ? '0' : $formatted;
    }
}
