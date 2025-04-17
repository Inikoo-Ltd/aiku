<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 16-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Accounting\Invoice;

use App\Actions\OrgAction;
use App\Models\Accounting\Invoice;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\Response;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Models\Helpers\Currency;

class OmegaInvoice extends OrgAction
{
    public function handle(Invoice $invoice): string
    {

        $text = "R00\tT00\r\n";

        $text .= $this->getOmegaExportText($invoice, 'SK');

        return iconv(mb_detect_encoding($text), 'ISO-8859-15//IGNORE', mb_convert_encoding($text, 'UTF-8', 'auto'));
    }


    public function asController(Organisation $organisation, Invoice $invoice, ActionRequest $request): Response
    {
        $this->initialisationFromShop($invoice->shop, $request);



        $omegaText = $this->handle($invoice);


        $filename = $invoice->slug.'.txt';

        return response($omegaText, 200)
            ->header('Content-Type', 'text/plain')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }


    public function getOmegaExportText(Invoice $invoice, $base_country = 'SK')
    {

        $surrogate_code = 'zGB';

        $surrogate = false;

        if ($invoice->external_invoicer_id) {
            $surrogate = true;
        }

        $invoice_tax_code = $invoice->taxCategory->label;

        $european_union_2alpha = array(
            'NL',
            'BE',
            //  'GB',
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
        );


        $store = $invoice->shop;

        $order = $invoice->order;



        if ($invoice->currency->code == 'EUR') {
            $exchange_rate = 1;
        } else {


            if ($surrogate) {
                /** @var Currency $eur */
                // TODO: i dont know this correct or not to get $exchange_rate like in aurora
                $eur = Currency::where('code', 'EUR')->first();
                $exchange_rate = $eur->exchanges()
                    ->whereDate('date', '<=', $invoice->date)
                    ->orderBy('date', 'desc')
                    ->first()->exchange;

            } else {

                if ($invoice->type == InvoiceTypeEnum::REFUND) {
                    $orginalInvoice = $invoice->originalInvoice;

                    if ($this->parent instanceof Organisation) {
                        $exchange_rate = $orginalInvoice->org_exchange;
                    } else {
                        $exchange_rate = $orginalInvoice->grp_exchange;
                    }
                } else {
                    if ($this->parent instanceof Organisation) {
                        $exchange_rate = $invoice->org_exchange;
                    } else {
                        $exchange_rate = $invoice->grp_exchange;
                    }
                }
            }




        }

        $invoiceAddress = $invoice->address;

        if ($invoice->type == InvoiceTypeEnum::REFUND) {
            $invoiceAddress = $invoice->originalInvoice->address;
            $invoice->tax_liability_at = $invoice->originalInvoice->tax_liability_at;
        }

        if ($base_country == $invoiceAddress->country_code) {
            $invoice_numeric_code          = 100;
            $invoice_alpha_code            = 'OF';
            $invoice_alpha_code_bis        = 'OF';
            $invoice_numeric_code_total    = 100;
            $invoice_numeric_code_shipping = 101;
            $invoice_numeric_code_charges  = 102;

        } else {
            $invoice_numeric_code          = 300;
            $invoice_numeric_code_total    = 200;
            $invoice_numeric_code_shipping = 201;
            $invoice_numeric_code_charges  = 202;


            if ($surrogate) {

                $invoice_alpha_code     = $surrogate_code;
                $invoice_alpha_code_bis = $surrogate_code.$store->code;

                if ($invoice->type == InvoiceTypeEnum::REFUND) {
                    $invoice_alpha_code = 'zOD';
                }


            } else {

                $invoice_alpha_code = 'zOF';

                // TODO: in aurora use $store->get('Store Code') instead of $store->get('Code') so i doubt about this
                $invoice_alpha_code_bis = 'zOF'.$store->code;


                if ($invoice->type == InvoiceTypeEnum::REFUND) {
                    $invoice_alpha_code     = 'zOD';
                    $invoice_alpha_code_bis = 'zOD'.$store->code;
                }

            }

        }


        $_code = 200;


        if ($invoiceAddress->country_code == 'SK') {
            $_code = 100;

            // TODO: i dont know where to get $invoice->get('Invoice Registration Number' like in aurora
            if ($invoice->shop->taxNumber != null) {
                $code_tax = 'A1';

            } else {
                $code_tax = 'D2';

            }


            $code_sum = '03';
        } elseif (in_array($invoiceAddress->country_code, $european_union_2alpha)) {

            if ($invoice_tax_code == 'S1'  or   $invoice_tax_code == 'XS1') {
                $code_sum = '03';
                $code_tax = 'A1';
            } else {
                $code_sum = '14';
                $code_tax = 'X';
            }




        } else {

            if ($invoice_tax_code == 'S1'  or   $invoice_tax_code == 'XS1') {
                $code_sum = '03';
                $code_tax = 'A1';
            } else {

                $code_sum = '15t';
                $code_tax = 'X';
            }
        }

        $invoiceDiscountAmount = $invoice->stats->discounts_amount;
        $invoiceNetAmountWIthoutDiscount = $invoice->net_amount - $invoiceDiscountAmount;

        $_total_amount_exchange =
            round($invoiceNetAmountWIthoutDiscount * $exchange_rate, 2) + round($invoice->shipping_amount * $exchange_rate, 2) + round($invoice->charges_amount * $exchange_rate, 2)
            + round($invoice->tax_amount * $exchange_rate, 2);


        $text                = '';
        $invoice_header_data = array(
            'R01',
            $invoice_numeric_code,
            $invoice_alpha_code,
            $invoice_alpha_code_bis,
            $invoice->reference,
            $order->reference ?? '',
            $invoice->customer->name,
            '', // TODO: i dont know where to get $invoice->get('Invoice Registration Number')
            $invoice->shop->taxNumber->number,
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
            '0.000',
            $invoice->net_amount + $invoice->shipping_amount + $invoice->charges_amount,
            '0.000',
            '0.000',
            '0.000',
            ($invoice->tax_amount == 0 ? '' : $invoice->tax_amount),
            ($invoice->tax_amount == 0 ? '' : '0.000'),
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
            'Total '.$store->code.' '.$invoice_tax_code,
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

        );

        $invoice_header = "";
        foreach ($invoice_header_data as $header_item) {
            $invoice_header .= $header_item."\t";
        }
        $invoice_header .= "\r\n";


        $text .= $invoice_header;

        $row_data = array(
            'R02',
            0,
            311,
            $_code,
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
            '',
            'X',


        );

        $invoice_row = "";
        foreach ($row_data as $column) {
            $invoice_row .= $column."\t";
        }
        $invoice_row .= "\r\n";
        $text        .= $invoice_row;

        $row_data = array(
            'R02',
            0,
            '',
            '',
            604,
            $invoice_numeric_code_total,
            round($invoiceNetAmountWIthoutDiscount * $exchange_rate, 2),
            $invoiceNetAmountWIthoutDiscount,
            'Items '.$store->code.' '.$invoice_tax_code,
            $code_sum,
            '',
            '',
            '(Nedefinované)',
            'X',
            '(Nedefinované)',
            'X',
            '(Nedefinované)',
            'X',
            '(Nedefinované)',
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
        );


        $invoice_row = "";
        foreach ($row_data as $column) {
            $invoice_row .= $column."\t";
        }
        $invoice_row .= "\r\n";
        $text        .= $invoice_row;

        if ($invoice->shipping_amount != 0) {


            $row_data = array(
                'R02',
                0,
                '',
                '',
                604,
                $invoice_numeric_code_shipping,
                round($invoice->shipping_amount * $exchange_rate, 2),
                $invoice->shipping_amount,
                'Shipping '.$store->code.' '.$invoice_tax_code,
                $code_sum,
                '',
                '',
                '(Nedefinované)',
                'X',
                '(Nedefinované)',
                'X',
                '(Nedefinované)',
                'X',
                '(Nedefinované)',
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
            );

            $invoice_row = "";
            foreach ($row_data as $column) {
                $invoice_row .= $column."\t";
            }
            $invoice_row .= "\r\n";
            $text        .= $invoice_row;

        }


        if ($invoice->charges_amount != 0) {


            $row_data = array(
                'R02',
                0,
                '',
                '',
                604,
                $invoice_numeric_code_charges,
                round($invoice->charges_amount * $exchange_rate, 2),
                $invoice->charges_amount,
                'Charges '.$store->code.' '.$invoice_tax_code,
                $code_sum,
                '',
                '',
                '(Nedefinované)',
                'X',
                '(Nedefinované)',
                'X',
                '(Nedefinované)',
                'X',
                '(Nedefinované)',
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
            );

            $invoice_row = "";
            foreach ($row_data as $column) {
                $invoice_row .= $column."\t";
            }
            $invoice_row .= "\r\n";
            $text        .= $invoice_row;

        }


        if ($invoice_tax_code == 'SK-SR23') {
            $invoice_tax_code = '23%';
        }


        if ($invoice->tax_amount != 0) {
            $row_data = array(
                'R02',
                0,
                '',
                '',
                343,
                223,
                round($invoice->tax_amount * $exchange_rate, 2),
                $invoice->tax_amount,
                'Tax '.$store->code.' '.$invoice_tax_code,
                '04',
                '',
                '',
                '(Nedefinované)',
                'X',
                '(Nedefinované)',
                'X',
                '(Nedefinované)',
                'X',
                '(Nedefinované)',
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
            );

            $invoice_row = "";
            foreach ($row_data as $column) {
                $invoice_row .= $column."\t";
            }
            $invoice_row .= "\r\n";
            $text        .= $invoice_row;

        }


        return $text;


    }


}
