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
use App\Models\Helpers\Currency;
use App\Models\SysAdmin\Organisation;

trait WithOmegaData
{
    public function getOmegaExportText(Invoice $invoice, $base_country = 'SK')
    {

        $surrogate_code = 'zGB';
        $surrogate = $invoice->external_invoicer_id ? true : false;
        $invoice_tax_code = $invoice->taxCategory->label;

        $european_union_2alpha = [
            'NL', 'BE', 'BG', 'ES', 'IE', 'IT', 'AT', 'GR', 'CY', 'LV', 'LT', 'LU', 'MT', 'PT', 'PL', 'FR', 'RO', 'SE', 'DE', 'SK', 'SI', 'FI', 'DK', 'CZ', 'HU', 'EE'
        ];

        $store = $invoice->shop;
        $order = $invoice->order;

        $exchange_rate = $this->calculateExchangeRate($invoice, $surrogate);

        $invoiceAddress = $this->getInvoiceAddress($invoice);
        $invoiceCodes = $this->determineInvoiceCodes($invoice, $invoiceAddress, $base_country, $surrogate, $store, $surrogate_code);

        $code_tax = $this->determineTaxCode($invoiceAddress, $invoice_tax_code, $european_union_2alpha, $invoice->shop->taxNumber);
        $code_sum = $this->determineSumCode($invoiceAddress, $invoice_tax_code, $european_union_2alpha);

        $invoiceDiscountAmount = $invoice->stats->discounts_amount;
        $invoiceNetAmountWithoutDiscount = $invoice->net_amount - $invoiceDiscountAmount;

        $_total_amount_exchange = $this->calculateTotalAmountExchange($invoice, $invoiceNetAmountWithoutDiscount, $exchange_rate);

        $text = '';
        $text .= $this->generateHeaderRow($invoice, $order, $store, $invoiceCodes, $exchange_rate, $_total_amount_exchange);
        $text .= $this->generateInvoiceRow($invoice, $store, $invoiceCodes, $_total_amount_exchange, $code_sum, $code_tax, $invoiceNetAmountWithoutDiscount, $exchange_rate);

        if ($invoice->shipping_amount != 0) {
            $text .= $this->generateShippingRow($invoice, $store, $invoiceCodes, $code_sum, $code_tax, $exchange_rate);
        }

        if ($invoice->charges_amount != 0) {
            $text .= $this->generateChargesRow($invoice, $store, $invoiceCodes, $code_sum, $code_tax, $exchange_rate);
        }

        if ($invoice->tax_amount != 0) {
            $text .= $this->generateTaxRow($invoice, $store, $invoice_tax_code, $code_tax, $exchange_rate);
        }

        return $text;
    }

    private function calculateExchangeRate(Invoice $invoice, $surrogate)
    {
        $exchangeRate = null;

        if ($invoice->currency->code == 'EUR') {
            $exchangeRate = 1;
        } elseif ($surrogate) {
            $eur = Currency::where('code', 'EUR')->first();
            $exchangeRate = $eur->exchanges()
                ->whereDate('date', '<=', $invoice->date)
                ->orderBy('date', 'desc')
                ->first()->exchange;
        } elseif ($invoice->type == InvoiceTypeEnum::REFUND) {
            $originalInvoice = $invoice->originalInvoice;
            $exchangeRate = $this->parent instanceof Organisation ? $originalInvoice->org_exchange : $originalInvoice->grp_exchange;
        } else {
            $exchangeRate = $this->parent instanceof Organisation ? $invoice->org_exchange : $invoice->grp_exchange;
        }

        return $exchangeRate;
    }

    private function getInvoiceAddress(Invoice $invoice)
    {
        if ($invoice->type == InvoiceTypeEnum::REFUND) {
            $invoice->tax_liability_at = $invoice->originalInvoice->tax_liability_at;
            return $invoice->originalInvoice->address;
        }

        return $invoice->address;
    }

    private function determineInvoiceCodes(Invoice $invoice, $invoiceAddress, $base_country, $surrogate, $store, $surrogate_code)
    {
        if ($base_country == $invoiceAddress->country_code) {
            return [
                'numeric_code' => 100,
                'alpha_code' => 'OF',
                'alpha_code_bis' => 'OF',
                'numeric_code_total' => 100,
                'numeric_code_shipping' => 101,
                'numeric_code_charges' => 102,
            ];
        }

        $alpha_code = $surrogate ? $surrogate_code : 'zOF';
        $alpha_code_bis = $surrogate ? $surrogate_code . $store->code : 'zOF' . $store->code;

        if ($invoice->type == InvoiceTypeEnum::REFUND) {
            $alpha_code = 'zOD';
            $alpha_code_bis = 'zOD' . $store->code;
        }

        return [
            'numeric_code' => 300,
            'alpha_code' => $alpha_code,
            'alpha_code_bis' => $alpha_code_bis,
            'numeric_code_total' => 200,
            'numeric_code_shipping' => 201,
            'numeric_code_charges' => 202,
        ];
    }

    private function determineTaxCode($invoiceAddress, $invoice_tax_code, $european_union_2alpha, $shopTaxNumber)
    {
        if ($invoiceAddress->country_code == 'SK') {
            return $shopTaxNumber ? 'A1' : 'D2';
        }

        if (in_array($invoiceAddress->country_code, $european_union_2alpha)) {
            return ($invoice_tax_code == 'S1' || $invoice_tax_code == 'XS1') ? 'A1' : 'X';
        }

        return ($invoice_tax_code == 'S1' || $invoice_tax_code == 'XS1') ? 'A1' : 'X';
    }

    private function determineSumCode($invoiceAddress, $invoice_tax_code, $european_union_2alpha)
    {
        if ($invoiceAddress->country_code == 'SK') {
            return '03';
        }

        if (in_array($invoiceAddress->country_code, $european_union_2alpha)) {
            return ($invoice_tax_code == 'S1' || $invoice_tax_code == 'XS1') ? '03' : '14';
        }

        return ($invoice_tax_code == 'S1' || $invoice_tax_code == 'XS1') ? '03' : '15t';
    }

    private function calculateTotalAmountExchange(Invoice $invoice, $invoiceNetAmountWithoutDiscount, $exchange_rate)
    {
        return round($invoiceNetAmountWithoutDiscount * $exchange_rate, 2) +
            round($invoice->shipping_amount * $exchange_rate, 2) +
            round($invoice->charges_amount * $exchange_rate, 2) +
            round($invoice->tax_amount * $exchange_rate, 2);
    }

    private function generateHeaderRow(Invoice $invoice, $order, $store, $invoiceCodes, $exchange_rate, $_total_amount_exchange)
    {
        $ZERO_LITERAL = '0.000';
        $taxNumber = $invoice->shop->taxNumber->number ?? '';
        $header_data = [
            'R01',
            $invoiceCodes['numeric_code'],
            $invoiceCodes['alpha_code'],
            $invoiceCodes['alpha_code_bis'],
            $invoice->reference,
            $order->reference ?? '',
            $invoice->customer->name,
            '',
            $taxNumber,
            $invoice->date->format('d.m.Y'),
            '',
            $invoice->tax_liability_at->format('d.m.Y'),
            $order?->date->format('d.m.Y') ?? '',
            $order?->date->format('d.m.Y') ?? '',
            $invoice->currency->code,
            1,
            1 / $exchange_rate,
            0,
            $invoice->total_amount,
            $_total_amount_exchange,
            19,
            23,
            $ZERO_LITERAL,
            $invoice->net_amount + $invoice->shipping_amount + $invoice->charges_amount,
            $ZERO_LITERAL,
            $ZERO_LITERAL,
            $ZERO_LITERAL,
            ($invoice->tax_amount == 0 ? '' : $invoice->tax_amount),
            ($invoice->tax_amount == 0 ? '' : $ZERO_LITERAL),
            'Tomášková Andrea',
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
            'Total ' . $store->code . ' ' . $invoice->taxCategory->label,
            0,
            '',
            '',
            '',
            0,
            0,
            'EJA',
            'José António Erika',
            $store->code,
            0,
            $store->code,
            'Tomášková Andrea',
            $invoice->reference,
            '',
            '',
            '/',
            0,
            '',
            '',
            0
        ];

        return implode("\t", $header_data) . "\r\n";
    }

    private function generateInvoiceRow(Invoice $invoice, $store, $invoiceCodes, $_total_amount_exchange, $code_sum, $code_tax, $invoiceNetAmountWithoutDiscount, $exchange_rate)
    {
        $UNDEFINED_LITERAL = '(Nedefinované)';

        $row_data = [
            'R02',
            0,
            311,
            $invoiceCodes['numeric_code'],
            '',
            '',
            $_total_amount_exchange,
            $invoice->total_amount,
            $invoice->customer->name,
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

        $row = implode("\t", $row_data) . "\r\n";

        $row_data = [
            'R02',
            0,
            '',
            '',
            604,
            $invoiceCodes['numeric_code_total'],
            round($invoiceNetAmountWithoutDiscount * $exchange_rate, 2),
            $invoiceNetAmountWithoutDiscount,
            'Items ' . $store->code . ' ' . $invoice->taxCategory->label,
            $code_sum,
            '',
            '',
            $UNDEFINED_LITERAL,
            'X',
            $UNDEFINED_LITERAL,
            'X',
            $UNDEFINED_LITERAL,
            'X',
            $UNDEFINED_LITERAL,
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

        return $row . implode("\t", $row_data) . "\r\n";
    }

    private function generateShippingRow(Invoice $invoice, $store, $invoiceCodes, $code_sum, $code_tax, $exchange_rate)
    {
        $UNDEFINED_LITERAL = '(Nedefinované)';

        $row_data = [
            'R02',
            0,
            '',
            '',
            604,
            $invoiceCodes['numeric_code_shipping'],
            round($invoice->shipping_amount * $exchange_rate, 2),
            $invoice->shipping_amount,
            'Shipping ' . $store->code . ' ' . $invoice->taxCategory->label,
            $code_sum,
            '',
            '',
            $UNDEFINED_LITERAL,
            'X',
            $UNDEFINED_LITERAL,
            'X',
            $UNDEFINED_LITERAL,
            'X',
            $UNDEFINED_LITERAL,
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

        return implode("\t", $row_data) . "\r\n";
    }

    private function generateChargesRow(Invoice $invoice, $store, $invoiceCodes, $code_sum, $code_tax, $exchange_rate)
    {
        $UNDEFINED_LITERAL = '(Nedefinované)';

        $row_data = [
            'R02',
            0,
            '',
            '',
            604,
            $invoiceCodes['numeric_code_charges'],
            round($invoice->charges_amount * $exchange_rate, 2),
            $invoice->charges_amount,
            'Charges ' . $store->code . ' ' . $invoice->taxCategory->label,
            $code_sum,
            '',
            '',
            $UNDEFINED_LITERAL,
            'X',
            $UNDEFINED_LITERAL,
            'X',
            $UNDEFINED_LITERAL,
            'X',
            $UNDEFINED_LITERAL,
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

        return implode("\t", $row_data) . "\r\n";
    }

    private function generateTaxRow(Invoice $invoice, $store, $invoice_tax_code, $code_tax, $exchange_rate)
    {
        $UNDEFINED_LITERAL = '(Nedefinované)';

        $row_data = [
            'R02',
            0,
            '',
            '',
            343,
            223,
            round($invoice->tax_amount * $exchange_rate, 2),
            $invoice->tax_amount,
            'Tax ' . $store->code . ' ' . $invoice_tax_code,
            '04',
            '',
            '',
            $UNDEFINED_LITERAL,
            'X',
            $UNDEFINED_LITERAL,
            'X',
            $UNDEFINED_LITERAL,
            'X',
            $UNDEFINED_LITERAL,
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

        return implode("\t", $row_data) . "\r\n";
    }
}
