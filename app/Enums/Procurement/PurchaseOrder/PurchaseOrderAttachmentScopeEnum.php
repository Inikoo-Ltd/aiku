<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Procurement\PurchaseOrder;

use App\Enums\EnumHelperTrait;
use Illuminate\Support\Str;

enum PurchaseOrderAttachmentScopeEnum: string
{
    use EnumHelperTrait;

    case PROFORMA     = 'Proforma';
    case INVOICE      = 'Invoice';
    case PACKING_LIST = 'Packing list';
    case CLAIM        = 'Claim';
    case FEEDBACK     = 'Delivery feedback';
    case CUSTOMS      = 'Customs declaration';
    case SERVICE_INVOICE = 'Service invoice';
    case BANK_STATEMENT = 'Bank statement';
    case DEPOSIT_PAYMENT = 'Deposit payment';
    case DUTY_PAYMENT = 'Import VAT / Duty paid';
    case SAMPLE_PHOTO = 'Sample photo';
    case PRODUCTION_PHOTO = 'Production photo';
    case QC_REPORT    = 'QC report';
    case BILL_OF_LADING = 'Bill of lading';
    case CERTIFICATE_OF_ORIGIN = 'Certificate of origin';
    case SHIPPING_QUOTE = 'Shipping quote';
    case OTHER        = 'Other';

    /**
     * The shipping and customs documents a supplier uploads to its own order from its portal.
     *
     * @return list<self>
     */
    public static function supplierPortal(): array
    {
        return [self::PROFORMA, self::INVOICE, self::PACKING_LIST, self::BILL_OF_LADING, self::CERTIFICATE_OF_ORIGIN, self::OTHER];
    }

    public static function labels(): array
    {
        return [
            'Proforma'     => __('Proforma'),
            'Invoice'      => __('Invoice'),
            'Packing list' => __('Packing list'),
            'Claim'        => __('Claim'),
            'Delivery feedback' => __('Delivery feedback'),
            'Customs declaration' => __('Customs declaration'),
            'Service invoice' => __('Service invoice (freight, customs, local)'),
            'Bank statement' => __('Bank statement'),
            'Deposit payment' => __('Deposit payment'),
            'Import VAT / Duty paid' => __('Import VAT / Duty paid'),
            'Sample photo' => __('Sample photo'),
            'Production photo' => __('Production photo'),
            'QC report'    => __('QC report'),
            'Bill of lading' => __('Bill of lading'),
            'Certificate of origin' => __('Certificate of origin'),
            'Shipping quote' => __('Shipping quote'),
            'Other'        => __('Other'),
        ];
    }

    /**
     * @return array<int, array{name: string, code: string}>
     */
    public static function options(): array
    {
        return collect(self::labels())
            ->map(fn (string $label, string $code) => ['name' => $label, 'code' => $code])
            ->values()
            ->all();
    }

    /**
     * Proforma is checked before invoice because suppliers name them "proforma invoice".
     */
    public static function guessFromFileName(string $fileName): self
    {
        $name = Str::lower($fileName);

        return match (true) {
            Str::contains($name, ['proforma', 'pro-forma', 'pro forma']) => self::PROFORMA,
            Str::contains($name, ['packing', 'packlist']) => self::PACKING_LIST,
            Str::contains($name, ['bill of lading', 'bill_of_lading', 'bill-of-lading', 'b/l']) => self::BILL_OF_LADING,
            Str::contains($name, ['certificate of origin', 'certificate_of_origin', 'certificate-of-origin']) => self::CERTIFICATE_OF_ORIGIN,
            Str::contains($name, ['shipping quote', 'freight quote', 'quotation']) => self::SHIPPING_QUOTE,
            Str::contains($name, ['qc report', 'qc_report', 'qc-report', 'inspection report']) => self::QC_REPORT,
            Str::contains($name, ['bank statement', 'bank_statement', 'bank-statement', 'swift', 'remittance']) => self::BANK_STATEMENT,
            Str::contains($name, ['customs', 'colny', 'colný', 'aduana', 'zoll']) => self::CUSTOMS,
            Str::contains($name, ['invoice', 'factura', 'faktura', 'rechnung', 'fattura']) => self::INVOICE,
            default => self::OTHER,
        };
    }
}
