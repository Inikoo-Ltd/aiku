<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 24 Apr 2023 20:23:18 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Picking;

use App\Actions\Traits\WithExportData;
use App\Models\Dispatching\Picking;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;
use Symfony\Component\HttpFoundation\Response;

class ExportPicking
{
    use AsAction;
    use WithAttributes;
    use WithExportData;

    /**
     * @throws \Mpdf\MpdfException
     */
    public function handle(?Picking $picking): Response
    {
        $filename = 'picking-' . now()->format('Y-m-d');
        $pdf      = PDF::loadView('pickings.templates.pdf.picking', [
            'picking'  => $picking,
            'filename' => $filename
        ]);

        return response($pdf->stream($filename . '.pdf'), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="' . $filename . '.pdf"');
    }

    /**
     * @throws \Mpdf\MpdfException
     */
    public function asController(?Picking $picking): Response
    {
        return $this->handle($picking);
    }
}
