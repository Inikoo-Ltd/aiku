<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 17-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Traits;

use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Models\Accounting\Invoice;
use Illuminate\Support\Arr;

trait WithOmegaData
{
    /** @noinspection SpellCheckingInspection */
    private const string UNDEFINED_LITERAL = '(Nedefinované)';

    public function getOmegaExportText(Invoice $invoice, $base_country = 'SK'): string
    {
        $surrogate_code   = 'zGB';
        $surrogate        = (bool)$invoice->external_invoicer_id;
        $invoice_tax_code = $invoice->taxCategory->label;

        $european_union_2alpha = [
            'NL',
            'BE',
            'BG',
            'ES',
            'IE',
            'IT',
            'AT',
            'GR',
            'CY',
            'LV',
            'LT',
            'LU',
            'MT',
            'PT',
            'PL',
            'FR',
            'RO',
            'SE',
            'DE',
            'SK',
            'SI',
            'FI',
            'DK',
            'CZ',
            'HU',
            'EE'
        ];

        $store = $invoice->shop;
        $order = $invoice->order;


        $invoiceAddress = $this->getInvoiceAddress($invoice);
        $invoiceCodes   = $this->determineInvoiceCodes($invoice, $invoiceAddress, $base_country, $surrogate, $store, $surrogate_code);

        $code_tax = $this->determineTaxCode($invoiceAddress, $invoice_tax_code, $invoice->shop->taxNumber);
        $code_sum = $this->determineSumCode($invoiceAddress, $invoice_tax_code, $european_union_2alpha);


        $text = $this->generateHeaderRow($invoice, $order, $store, $invoiceCodes);
        $text .= $this->generateInvoiceTotalsRow($invoice, $invoiceCodes);
        $text .= $this->generateItemsRow($invoice, $store, $invoiceCodes, $code_sum, $code_tax);

        if ($invoice->shipping_amount != 0) {
            $text .= $this->generateShippingRow($invoice, $store, $invoiceCodes, $code_sum, $code_tax);
        }

        if ($invoice->charges_amount != 0) {
            $text .= $this->generateChargesRow($invoice, $store, $invoiceCodes, $code_sum, $code_tax);
        }

        if ($invoice->tax_amount != 0) {
            $text .= $this->generateTaxRow($invoice, $store, $invoice_tax_code, $code_tax);
        }

        return $text;
    }


    private function getInvoiceAddress(Invoice $invoice): ?\App\Models\Helpers\Address
    {
        if ($invoice->type == InvoiceTypeEnum::REFUND) {
            $invoice->tax_liability_at = $invoice->originalInvoice->tax_liability_at;

            return $invoice->originalInvoice->address;
        }

        return $invoice->address;
    }

    private function determineInvoiceCodes(Invoice $invoice, $invoiceAddress, $base_country, $surrogate, $store, $surrogate_code): array
    {
        if ($base_country == $invoiceAddress->country_code) {
            return [
                'numeric_code'          => 100,
                'alpha_code'            => 'OF',
                'alpha_code_bis'        => 'OF',
                'numeric_code_total'    => 100,
                'numeric_code_shipping' => 101,
                'numeric_code_charges'  => 102,
                'items_code'            => 100,
            ];
        }

        $alpha_code     = $surrogate ? $surrogate_code : 'zOF';
        $alpha_code_bis = $surrogate ? $surrogate_code.$store->code : 'zOF'.$store->code;

        if ($invoice->type == InvoiceTypeEnum::REFUND) {
            $alpha_code     = 'zOD';
            $alpha_code_bis = 'zOD'.$store->code;
        }

        return [
            'numeric_code'          => 300,
            'alpha_code'            => $alpha_code,
            'alpha_code_bis'        => $alpha_code_bis,
            'numeric_code_total'    => 200,
            'numeric_code_shipping' => 201,
            'numeric_code_charges'  => 202,
            'items_code'            => 200,
        ];
    }

    private function determineTaxCode($invoiceAddress, $invoice_tax_code, $shopTaxNumber): string
    {
        if ($invoiceAddress->country_code == 'SK') {
            return $shopTaxNumber ? 'A1' : 'D2';
        }

        return ($invoice_tax_code == 'S1' || $invoice_tax_code == 'XS1') ? 'A1' : 'X';
    }

    private function determineSumCode($invoiceAddress, $invoice_tax_code, $european_union_2alpha): string
    {
        if ($invoiceAddress->country_code == 'SK') {
            return '03';
        }

        if (in_array($invoiceAddress->country_code, $european_union_2alpha)) {
            return ($invoice_tax_code == 'S1' || $invoice_tax_code == 'XS1') ? '03' : '14';
        }

        return ($invoice_tax_code == 'S1' || $invoice_tax_code == 'XS1') ? '03' : '15t';
    }


    private function generateHeaderRow(Invoice $invoice, $order, $store, $invoiceCodes): string
    {
        $zeroLiteral = '0.000';

        $organisationSettings = $invoice->shop->organisation->settings;


        $header_data = [
            'R01',
            $invoiceCodes['numeric_code'],
            $invoiceCodes['alpha_code'],
            $invoiceCodes['alpha_code_bis'],
            $invoice->reference,
            $order->reference ?? '',
            $invoice->customer->name,
            $invoice->identity_document_number,
            $invoice->tax_number,
            $invoice->date->format('d.m.Y'),
            '',
            $invoice->tax_liability_at->format('d.m.Y'),
            $order?->date->format('d.m.Y') ?? '',
            $order?->date->format('d.m.Y') ?? '',
            $invoice->currency->code,
            1,
            1 / $invoice->org_exchange,
            0,
            $invoice->total_amount,
            $invoice->total_amount * $invoice->org_exchange,
            19,
            23,
            $zeroLiteral,
            $invoice->net_amount,
            $zeroLiteral,
            $zeroLiteral,
            $zeroLiteral,
            ($invoice->tax_amount == 0 ? '' : $invoice->tax_amount),
            ($invoice->tax_amount == 0 ? '' : $zeroLiteral),
            Arr::get($organisationSettings, 'omega.accountant', ''),
            '',
            '',
            '',
            '',
            '',
            '',
            '1374',
            '',
            date('H:i:s'),
            '',
            'Total '.$store->code.' '.$invoice->taxCategory->label,
            0,
            '',
            '',
            '',
            0,
            0,
            'EJA',
            Arr::get($organisationSettings, 'omega.responsible', ''),
            $store->code,
            0,
            $store->code,
            Arr::get($organisationSettings, 'omega.accountant', ''),
            $invoice->reference,
            '',
            '',
            '/',
            0,
            '',
            '',
            0
        ];

        return implode("\t", $header_data)."\r\n";
    }

    private function generateInvoiceTotalsRow(Invoice $invoice, $invoiceCodes): string
    {
        $row_data = [
            'R02',
            0,
            311,
            $invoiceCodes['items_code'],
            '',
            '',
            $invoice->total_amount * $invoice->org_exchange,
            $invoice->total_amount,
            $invoice->customer_name,
            'S',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            'X',
        ];

        return implode("\t", $row_data)."\r\n";
    }

    private function generateItemsRow(Invoice $invoice, $store, $invoiceCodes, $code_sum, $code_tax): string
    {
        $undefinedLiteral = self::UNDEFINED_LITERAL;


        $row_data = [
            'R02',
            0,
            '',
            '',
            604,
            $invoiceCodes['numeric_code_total'],
            $invoice->goods_amount * $invoice->org_exchange,
            $invoice->goods_amount,
            'Items '.$store->code.' '.$invoice->taxCategory->label,
            $code_sum,
            '',
            '',
            $undefinedLiteral,
            'X',
            $undefinedLiteral,
            'X',
            $undefinedLiteral,
            'X',
            $undefinedLiteral,
            '',
            '',
            '',
            '',
            '',
            '',
            $code_tax,
            '',
            '',
            '',
            0,
            0
        ];

        return implode("\t", $row_data)."\r\n";
    }

    private function generateShippingRow(Invoice $invoice, $store, $invoiceCodes, $code_sum, $code_tax): string
    {
        $undefinedLiteral = self::UNDEFINED_LITERAL;

        $row_data = [
            'R02',
            0,
            '',
            '',
            604,
            $invoiceCodes['numeric_code_shipping'],
            round($invoice->shipping_amount * $invoice->org_exchange, 2),
            $invoice->shipping_amount,
            'Shipping '.$store->code.' '.$invoice->taxCategory->label,
            $code_sum,
            '',
            '',
            $undefinedLiteral,
            'X',
            $undefinedLiteral,
            'X',
            $undefinedLiteral,
            'X',
            $undefinedLiteral,
            '',
            '',
            '',
            '',
            '',
            '',
            $code_tax,
            '',
            '',
            '',
            0,
            0
        ];

        return implode("\t", $row_data)."\r\n";
    }

    private function generateChargesRow(Invoice $invoice, $store, $invoiceCodes, $code_sum, $code_tax): string
    {
        $undefinedLiteral = self::UNDEFINED_LITERAL;

        $row_data = [
            'R02',
            0,
            '',
            '',
            604,
            $invoiceCodes['numeric_code_charges'],
            round($invoice->charges_amount * $invoice->org_exchange, 2),
            $invoice->charges_amount,
            'Charges '.$store->code.' '.$invoice->taxCategory->label,
            $code_sum,
            '',
            '',
            $undefinedLiteral,
            'X',
            $undefinedLiteral,
            'X',
            $undefinedLiteral,
            'X',
            $undefinedLiteral,
            '',
            '',
            '',
            '',
            '',
            '',
            $code_tax,
            '',
            '',
            '',
            0,
            0
        ];

        return implode("\t", $row_data)."\r\n";
    }

    private function generateTaxRow(Invoice $invoice, $store, $invoice_tax_code, $code_tax): string
    {
        $undefinedLiteral = self::UNDEFINED_LITERAL;

        if ($invoice_tax_code == 'SK-SR23') {
            $invoice_tax_code = '23%';
        }

        $row_data = [
            'R02',
            0,
            '',
            '',
            343,
            223,
            round($invoice->tax_amount * $invoice->org_exchange, 2),
            $invoice->tax_amount,
            'Tax '.$store->code.' '.$invoice_tax_code,
            '04',
            '',
            '',
            $undefinedLiteral,
            'X',
            $undefinedLiteral,
            'X',
            $undefinedLiteral,
            'X',
            $undefinedLiteral,
            '',
            '',
            '',
            '',
            '',
            '',
            $code_tax,
            '',
            '',
            '',
            0,
            0
        ];

        return implode("\t", $row_data)."\r\n";
    }
}
