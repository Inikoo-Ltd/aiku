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


        $encoded_text = iconv(mb_detect_encoding($text), 'ISO-8859-15//IGNORE', mb_convert_encoding($text, 'UTF-8', 'auto'));
        return $encoded_text;

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

    public function action(Invoice $invoice, array $modelData, int $hydratorsDelay = 0, bool $strict = true): string
    {
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;

        $this->initialisationFromShop($invoice->shop, $modelData);

        return $this->handle($invoice);
    }


    public function getOmegaExportText(Invoice $invoice, $base_country = 'SK')
    {

        $surrogate_code = 'zGB';

        $surrogate = false;

        // if($invoice->get('Invoice External Invoicer Key')>0){
        //     $surrogate = true;
        // }
        if ($invoice->external_invoicer_id) {
            $surrogate = true;
        }




        // $invoice_tax_code =$invoice->get('Invoice Tax Code');
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


        // $store = get_object('Store', $invoice->get('Invoice Store Key'));
        $store = $invoice->shop;


        // $order = get_object('Order', $invoice->get('Invoice Order Key'));
        $order = $invoice->order;



        if ($invoice->currency->code == 'EUR') {
            $exchange_rate = 1;
        } else {


            if ($surrogate) {
                // $exchange_rate=get_historic_exchange($invoice->get('Invoice Currency'), 'EUR', gmdate('Y-m-d',strtotime($invoice->get('Invoice Date'))));

                /** @var Currency $eur */
                // TODO: i dont know this correct or not
                $eur = Currency::where('code', 'EUR')->first();
                $exchange_rate = $eur->exchanges()
                    ->whereDate('date', '<=', $invoice->date)
                    ->orderBy('date', 'desc')
                    ->first()->exchange;

            } else {
                // $exchange_rate = $invoice->get('Invoice Currency Exchange');

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

        // if ($base_country == $invoice->get('Invoice Address Country 2 Alpha Code')) {
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
                // $invoice_alpha_code_bis = $surrogate_code.$store->get('Store Code');
                $invoice_alpha_code_bis = $surrogate_code.$store->code;

                // if ($invoice->get('Invoice Type') == 'Refund') {
                //     $invoice_alpha_code = 'zOD';
                // }
                if ($invoice->type == InvoiceTypeEnum::REFUND) {
                    $invoice_alpha_code = 'zOD';
                }


            } else {

                $invoice_alpha_code = 'zOF';

                // $invoice_alpha_code_bis = 'zOF'.$store->get('Store Code');
                $invoice_alpha_code_bis = 'zOF'.$store->code;


                // if ($invoice->get('Invoice Type') == 'Refund') {
                //     $invoice_alpha_code     = 'zOD';
                //     $invoice_alpha_code_bis = 'zOD'.$store->get('Store Code');
                // }
                if ($invoice->type == InvoiceTypeEnum::REFUND) {
                    $invoice_alpha_code     = 'zOD';
                    $invoice_alpha_code_bis = 'zOD'.$store->code;
                }

            }

        }


        $_code = 200;


        // if ($invoice->get('Invoice Address Country 2 Alpha Code') == 'SK') {
        if ($invoiceAddress->country_code == 'SK') {
            $_code = 100;

            // if ($invoice->get('Invoice Registration Number') != '' or $invoice->get('Invoice Tax Number') != '') {
            // TODO: i dont know where to get invoice registration number
            if ($invoice->shop->taxNumber != null) {
                $code_tax = 'A1';

            } else {
                $code_tax = 'D2';

            }


            $code_sum = '03';
            // } elseif (in_array($invoice->get('Invoice Address Country 2 Alpha Code'), $european_union_2alpha)) {
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

        // $_total_amount_exchange =
        //     round(($invoice->get('Invoice Items Net Amount') - $invoice->get('Invoice Net Amount Off')) * $exchange_rate, 2) + round($invoice->get('Invoice Shipping Net Amount') * $exchange_rate, 2) + round($invoice->get('Invoice Charges Net Amount') * $exchange_rate, 2)
        //     + round(
        //         $invoice->get('Invoice Total Tax Amount') * $exchange_rate, 2
        //     );

        // TODO: i dont know where i get $invoice->get('Invoice Net Amount Off') or maybe this no need because the net amount already discounted

        $_total_amount_exchange =
            round($invoice->net_amount * $exchange_rate, 2) + round($invoice->shipping_amount * $exchange_rate, 2) + round($invoice->charges_amount * $exchange_rate, 2)
            + round(
                $invoice->tax_amount * $exchange_rate,
                2
            );


        $text                = '';
        $invoice_header_data = array(
            'R01',
            $invoice_numeric_code,
            $invoice_alpha_code,
            $invoice_alpha_code_bis,
            // $invoice->get('Invoice Public ID'),
            $invoice->reference,
            // $order->get('Order Public ID'),
            $order->reference ?? '',
            // $invoice->get('Invoice Customer Name'),
            $invoice->customer->name,
            // $invoice->get('Invoice Registration Number'),
            '123', // dont know where to get this
            // $invoice->get('Invoice Tax Number'),
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
            // $invoice->get('Invoice Items Net Amount') - $invoice->get('Invoice Net Amount Off') + $invoice->get('Invoice Shipping Net Amount') + $invoice->get('Invoice Charges Net Amount'),
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
            // 'Total '.$store->get('Code').' '.$invoice_tax_code, // dont know what difference store code and code
            'Total '.$store->code.' '.$invoice_tax_code,
            0,
            '',
            '',
            '',
            0,
            0,
            'EJA',
            'José António Erika',
            // $store->get('Code'),
            $store->code,
            0,
            // $store->get('Code'),
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
            // round(($invoice->get('Invoice Items Net Amount') - $invoice->get('Invoice Net Amount Off')) * $exchange_rate, 2),
            round($invoice->net_amount * $exchange_rate, 2),
            // $invoice->get('Invoice Items Net Amount') - $invoice->get('Invoice Net Amount Off'),
            $invoice->net_amount,
            // 'Items '.$store->get('Code').' '.$invoice_tax_code,
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


        // if ($invoice->get('Invoice Shipping Net Amount') != 0) {
        if ($invoice->shipping_amount != 0) {


            $row_data = array(
                'R02',
                0,
                '',
                '',
                604,
                $invoice_numeric_code_shipping,
                // round($invoice->get('Invoice Shipping Net Amount') * $exchange_rate, 2),
                round($invoice->shipping_amount * $exchange_rate, 2),
                // $invoice->get('Invoice Shipping Net Amount'),
                $invoice->shipping_amount,
                // 'Shipping '.$store->get('Code').' '.$invoice_tax_code,
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


        // if ($invoice->get('Invoice Charges Net Amount') != 0) {
        if ($invoice->charges_amount != 0) {


            $row_data = array(
                'R02',
                0,
                '',
                '',
                604,
                $invoice_numeric_code_charges,
                // round($invoice->get('Invoice Charges Net Amount') * $exchange_rate, 2),
                round($invoice->charges_amount * $exchange_rate, 2),
                // $invoice->get('Invoice Charges Net Amount'),
                $invoice->charges_amount,
                // 'Charges '.$store->get('Code').' '.$invoice_tax_code,
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


        // if ($invoice->get('Invoice Total Tax Amount') != 0) {
        if ($invoice->tax_amount != 0) {
            $row_data = array(
                'R02',
                0,
                '',
                '',
                343,
                223,
                // round($invoice->get('Invoice Total Tax Amount') * $exchange_rate, 2),
                round($invoice->tax_amount * $exchange_rate, 2),
                // $invoice->get('Invoice Total Tax Amount'),
                $invoice->tax_amount,
                // 'Tax '.$store->get('Code').' '.$invoice_tax_code,
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
