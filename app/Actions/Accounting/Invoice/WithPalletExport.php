<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 10-01-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Accounting\Invoice;

use App\Models\Fulfilment\Pallet;
use Exception;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;

trait WithPalletExport
{
    public function processDataExportPdf(Pallet $pallet): \Symfony\Component\HttpFoundation\Response
    {
        try {
            $config = [
                'title'                  => $pallet->reference,
                'margin_left'            => 8,
                'margin_right'           => 8,
                'margin_top'             => 2,
                'margin_bottom'          => 2,
                'auto_page_break'        => true,
                'auto_page_break_margin' => 10,
                'orientation'            => 'L'
            ];

            $filename = $pallet->slug . '-' . now()->format('Y-m-d');
            $pdf      = PDF::loadView('pickings.templates.pdf.pallet', [
                'shop'          => $pallet->fulfilment->shop,
                'pallet'       => $pallet,
                'customer'  => $pallet->fulfilmentCustomer->customer
            ], [], $config);

            return response($pdf->output(), 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="' . $filename . '.pdf"');
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to generate PDF'], 404);
        }
    }
}
