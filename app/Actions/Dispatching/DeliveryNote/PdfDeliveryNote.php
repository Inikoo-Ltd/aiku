<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 25-10-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\OrgAction;
use App\Actions\Traits\WithExportData;
use App\Models\Dispatching\DeliveryNote;
use Carbon\Carbon;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;
use Mpdf\MpdfException;
use Symfony\Component\HttpFoundation\Response;

class PdfDeliveryNote extends OrgAction
{
    use WithExportData;

    /**
     * @throws MpdfException
     * @throws \Mpdf\MpdfException
     */
    public function handle(DeliveryNote $deliveryNote): Response
    {
        $filename = $deliveryNote->slug.'-'.Carbon::now()->format('Y-m-d');

        $shop = $deliveryNote->shop;

        // Generate PDF using Blade template and data array
        $pdf = PDF::loadView('deliveryNote.templates.pdf.delivery-note', [
            'deliveryNote'    => $deliveryNote,
            'shop'            => $shop,
            'order'           => $deliveryNote->orders->first(),
            'customer'        => $deliveryNote->customer,
            'shopAddress'     => $shop->address->formatted_address,
            'deliveryAddress' => $deliveryNote->deliveryAddress->formatted_address,

            'items' => $deliveryNote->deliveryNoteItems,
        ]);

        return $pdf->stream($filename.'.pdf');
    }


    /**
     * @throws \Mpdf\MpdfException
     */
    public function asController(DeliveryNote $deliveryNote): Response
    {
        return $this->handle($deliveryNote);
    }
}
