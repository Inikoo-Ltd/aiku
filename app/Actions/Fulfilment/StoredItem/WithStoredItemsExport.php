<?php

/*
 * author Louis Perez
 * created on 07-05-2026-15h-01m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Fulfilment\StoredItem;

use App\Models\Fulfilment\FulfilmentCustomer;
use Exception;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;

trait WithStoredItemsExport
{
    public function processDataExportPdf(FulfilmentCustomer $parent): \Symfony\Component\HttpFoundation\Response
    {
        $title = null;
        $customer = null;
        $storedItems = [];
        if ($parent instanceof FulfilmentCustomer) {
            $customer = $parent->customer;
            $title = $customer->company_name;
            $storedItems = $parent->storedItems()->with('pallets')->get();
            // $storedItems = $parent->storedItems()->where('slug', 'ilike', '%ahbl%')->with('pallets')->get();
        }

        // dd($storedItems->toArray());

        $currTime = now()->format('Y-m-d');

        $config = [
            'title'                  => $title,
            'margin_left'            => 8,
            'margin_right'           => 8,
            'margin_top'             => 2,
            'margin_bottom'          => 2,
            'auto_page_break'        => true,
            'auto_page_break_margin' => 10,
            'orientation'            => 'L'
        ];
        $filename = "{$title}-SKUS-{$currTime}";
        $pdf      = PDF::loadView('pickings.templates.pdf.stored-item', [
            'shop'          => $parent->fulfilment->shop,
            'customer'      => $customer,
            'stored_items'  => $storedItems,
            'user'          => auth()->user(),
        ], [], $config);

        return response($pdf->stream(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="' . $filename . '.pdf"');
        // try {

        // } catch (Exception $e) {
        //     return response()->json(['error' => 'Failed to generate PDF'], 404);
        // }
    }
}
