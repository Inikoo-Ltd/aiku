<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 06-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Accounting\Invoice;

use App\Actions\OrgAction;
use App\Models\Accounting\Invoice;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class AttacheIsDocToInvoicePDF extends OrgAction
{
    public function handle(Invoice $invoice, $pdf, string $filename): string
    {
        $baseDir = 'tmp/isdoc';
        $disk = Storage::disk('local');

        if (!$disk->exists($baseDir)) {
            $disk->makeDirectory($baseDir);
        }

        $baseLocation = storage_path('app/' . $baseDir);
        $pdfLocation = $baseLocation . DIRECTORY_SEPARATOR . $filename . '_isdoc.pdf';
        $isdocLocation = $baseLocation . DIRECTORY_SEPARATOR . $filename . '_isdoc.xml';
        $outputFile = $baseLocation . DIRECTORY_SEPARATOR . $filename . '_isdoc_output.pdf';

        if (!$disk->exists($baseDir . '/' . $filename . '_isdoc.pdf')) {
            $pdf->save($pdfLocation);
        }

        if (!$disk->exists($baseDir . '/' . $filename . '_isdoc.xml')) {
            $xml = ISDocInvoice::run($invoice);
            $disk->put($baseDir . '/' . $filename . '_isdoc.xml', $xml);
        }

        $scriptLocation = base_path();
        $next = Process::path($scriptLocation)->run('./isdoc-pdf ' . $pdfLocation . ' ' . $isdocLocation . ' ' . $outputFile);

        if ($next->successful()) {
            return $outputFile;
        } else {
            throw new \Exception('ISDoc PDF generation failed: ' . $next->errorOutput());
        }
    }
}
