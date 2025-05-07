<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 06-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Accounting\Invoice;

use App\Models\Accounting\Invoice;
use Exception;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsObject;

class AttacheIsDocToInvoicePdf
{
    use AsObject;
    /**
     * @throws \Exception
     */
    public function handle(Invoice $invoice, $pdf, string $filename): string
    {
        $baseDir = 'tmp/isdoc';
        $disk = Storage::disk('local');

        if (!$disk->exists($baseDir)) {
            $disk->makeDirectory($baseDir);
        }

        $baseLocation = storage_path('app/' . $baseDir);
        $pdfLocation = $baseLocation . '/' . $filename . '_isdoc.pdf';
        $isdocLocation = $baseLocation . '/' . $filename . '_isdoc.xml';
        $outputFile = $baseLocation . '/' . $filename . '_isdoc_output.pdf';

        if ($disk->missing($baseDir . '/' . $filename . '_isdoc.pdf')) {
            $pdf->save($pdfLocation);
        }

        if ($disk->missing($baseDir . '/' . $filename . '_isdoc.xml')) {
            $xml = ISDocInvoice::run($invoice);
            $disk->put($baseDir . '/' . $filename . '_isdoc.xml', $xml);
        }

        $scriptLocation = base_path();

        $result = Process::path($scriptLocation)->run('./isdoc-pdf ' . $pdfLocation . ' ' . $isdocLocation . ' ' . $outputFile);


        if ($result->successful()) {
            return $outputFile;
        } else {

            $errorMsg = 'ISDoc PDF generation failed '.$result->output().' '.$result->errorOutput();

            \Sentry\captureMessage($errorMsg);

            throw new Exception($errorMsg);
        }
    }
}
