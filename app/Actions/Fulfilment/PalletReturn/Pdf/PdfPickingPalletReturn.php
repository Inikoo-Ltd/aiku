<?php
/*
 * author Arya Permana - Kirin
 * created on 30-06-2025-11h-18m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Fulfilment\PalletReturn\Pdf;

use App\Actions\Traits\WithExportData;
use App\Models\Fulfilment\PalletReturn;
use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;
use Symfony\Component\HttpFoundation\Response;

class PdfPickingPalletReturn
{
    use AsAction;
    use WithAttributes;
    use WithExportData;

    /**
     * @throws MpdfException
     */
    public function handle(PalletReturn $palletReturn): Response
    {
        // Retrieve delivery note details
        // $totalItemsNet = (float) $deliveryNote->total_amount;
        // $totalShipping = (float) $deliveryNote->order?->shipping_amount ?? 0;
        // $totalNet = $totalItemsNet + $totalShipping;

        // Prepare data to pass to the Blade template
        $filename = 'pallet-return-' . $palletReturn->reference . '-' . Carbon::now()->format('Y-m-d');

        // Generate PDF using Blade template and data array
        $pdf = PDF::loadView('pickings.templates.pdf.picking-pallet', [
            'palletReturn' => $palletReturn,
            'customer'     => $palletReturn->customer,
            'deliveryAddress' => $palletReturn->deliveryAddress->formatted_address,
            'shop'         => $palletReturn->fulfilment->shop,
            'items'        => $palletReturn->items,
        ]);

        return $pdf->stream($filename . '.pdf');
    }

    /**
     * @throws MpdfException
     */
    public function asController(PalletReturn $palletReturn): Response
    {
        return $this->handle($palletReturn);
    }
}
