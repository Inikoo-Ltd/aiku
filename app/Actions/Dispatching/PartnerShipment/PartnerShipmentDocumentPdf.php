<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\PartnerShipment;

use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;
use Symfony\Component\HttpFoundation\Response;

/**
 * The PDF of a customs invoice or a consolidated delivery note (HELP-4482), drawn from the same rows
 * as its XLSX, so the two never differ.
 */
class PartnerShipmentDocumentPdf
{
    /**
     * @param array<int, array<int, mixed>> $rows
     *
     * @throws \Mpdf\MpdfException
     */
    public static function response(array $rows, string $reference): Response
    {
        $pdf = PDF::loadView('dispatching.partner-shipment-document', ['rows' => $rows], [], [
            'title'         => $reference,
            'format'        => 'A4-L',
            'margin_left'   => 8,
            'margin_right'  => 8,
            'margin_top'    => 8,
            'margin_bottom' => 8,
        ]);

        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$reference.'.pdf"');
    }
}
