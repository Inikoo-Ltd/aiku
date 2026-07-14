<?php

/*
 * Author: Andi Ferdiawan
 * Created: Mon, 14 Jul 2026 13:30:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Dispatching\DeliveryNoteLeaflet;

use App\Enums\Dispatching\DeliveryNoteLeaflet\DeliveryNoteLeafletStateEnum;
use App\Models\Dispatching\DeliveryNoteLeaflet;
use Illuminate\Support\Facades\Storage;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;
use Rawilk\Printing\Api\PrintNode\Resources\PrintJob;

trait WithPrintDeliveryNoteLeaflet
{
    /**
     * Sends the leaflet's media file to the given PrintNode printer and marks it printed.
     * Uses WithPrintNode::printPdf() (the consuming action must also use WithPrintNode).
     */
    protected function printDeliveryNoteLeaflet(DeliveryNoteLeaflet $deliveryNoteLeaflet, int $printerId): ?PrintJob
    {
        $media = $deliveryNoteLeaflet->media;

        if (!$media) {
            return null;
        }

        $bytes = Storage::disk($media->disk)->get($media->getPathRelativeToRoot());

        if ($media->mime_type && str_starts_with($media->mime_type, 'image/')) {
            $pdf       = PDF::loadHTML('<img src="data:'.$media->mime_type.';base64,'.base64_encode($bytes).'" style="width:100%">');
            $pdfBase64 = base64_encode($pdf->output());
        } else {
            $pdfBase64 = base64_encode($bytes);
        }

        $result = $this->printPdf(
            title: $deliveryNoteLeaflet->name,
            printId: $printerId,
            pdfBase64: $pdfBase64
        );

        $deliveryNoteLeaflet->update([
            'state'              => DeliveryNoteLeafletStateEnum::PRINTED,
            'printed_at'         => now(),
            'printed_by_user_id' => request()->user()?->id,
        ]);

        return $result;
    }
}
