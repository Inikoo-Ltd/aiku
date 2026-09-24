<?php

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\OrgAction;
use App\Models\Dispatching\DeliveryNote;
use App\Actions\Traits\WithExportData;
use App\Models\Dispatching\DeliveryNoteItem;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\Response;

class PdfPackingList extends OrgAction
{
    use WithExportData;
    /**
     * @throws \Mpdf\MpdfException
     */
    public function handle(DeliveryNote $deliveryNote): Response
    {
        // 1. Eager load relasi yang dibutuhkan agar tidak terjadi N+1 query error / memory overload
        $deliveryNote->loadMissing([
            'orders',
            'deliveryAddress',
            'deliveryNoteItems.orgStock'
        ]);

        $filename = 'packing-list-'.$deliveryNote->slug.'-'.Carbon::now()->format('Y-m-d');

        $boxes = $deliveryNote->deliveryNoteItems
            ->flatMap(fn (DeliveryNoteItem $item) => collect($item->boxes ?? [])->map(fn (array $row) => [
                'box'      => $row['box'],
                'quantity' => $row['quantity'],
                'item'     => $item,
            ]))
            ->groupBy('box')
            ->sortKeys();

        $pdf = PDF::loadView('deliveryNote.templates.pdf.packing-list', [
            'deliveryNote'    => $deliveryNote,
            'order'           => $deliveryNote->orders->first(),
            'items'           => $deliveryNote->deliveryNoteItems,
            'boxes'           => $boxes,
            'numberBoxes'     => max(count($deliveryNote->parcels ?? []), (int)$boxes->keys()->max()),
            'deliveryAddress' => $deliveryNote->deliveryAddress?->formatted_address,
        ]);

        return response($pdf->stream($filename), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$filename.'.pdf"');
    }

    public function asController(DeliveryNote $deliveryNote): Response
    {
        return $this->handle($deliveryNote);
    }
}
