<?php
/*
 * author Louis Perez
 * created on 17-11-2025-14h-25m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Actions\Accounting\TopUp;

use App\Models\Accounting\TopUp;
use Exception;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;
use Sentry;

trait WithSingleTopUpReceipt
{
    public function processDataExportPdf(TopUp $topUp): \Symfony\Component\HttpFoundation\Response
    {
        $customer = $topUp->customer;

        $locale = $customer->shop->language->code;
        app()->setLocale($locale);
        $auth = auth()->guard();
        if($auth->name == 'retina' && $customer->id !== $auth->user()->customer->id){
            // Disable receipt check if logged in user is retina and is trying to access other user invoice
            abort(404);
        }

        try {
            $config = [
                'title'                  => 'Topup - ' . $topUp->reference,
                'margin_left'            => 8,
                'margin_right'           => 8,
                'margin_top'             => 2,
                'margin_bottom'          => 2,
                'auto_page_break'        => true,
                'auto_page_break_margin' => 10,
            ];


            $filename = $config['title'].'-'.now()->format('Y-m-d');
            $pdf      = PDF::loadView('invoices.templates.pdf.topup-receipt', [
                'shop'                 => $customer->shop,
                'topup'                => $topUp,
                'payment'              => $topUp->payment,
                'reference'            => $config['title'],
                'customer' => $customer
            ], [], $config);

            return response($pdf->stream($filename.'.pdf'), 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="'.$filename.'.pdf"');
        } catch (Exception $e) {
            Sentry::captureException($e);

            return response()->json(['error' => 'Failed to generate PDF'], 404);
        }
    }
}
