<?php

/*
 * Author: Vika Aqordi <aqordeon@gmail.com>
 * Created: Mon, 14 Sep 2026, Bali, Indonesia
 * Copyright (c) 2026, Inikoo LTD
 */

namespace App\Actions\Production\Artefact\Label;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsObject;
use setasign\Fpdi\PdfParser\PdfParser;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\PdfReader\PdfReader;
use Throwable;

class MakeArtworkPdfReadable
{
    use AsObject;

    private const CONVERSION_TIMEOUT_SECONDS = 120;

    /**
     * The free FPDI parser only reads the classic cross reference table, so PDFs saved with the
     * compressed object streams of PDF 1.5 and later are rewritten by Ghostscript as PDF 1.4,
     * which keeps their text and vectors. The artwork on disk is never touched, the rewritten
     * copy is a temporary file the caller removes once the sheet is out.
     *
     * @return array{path: string, is_temporary: bool}|null
     */
    public function handle(string $path): ?array
    {
        if ($this->isReadable($path)) {
            return ['path' => $path, 'is_temporary' => false];
        }

        $convertedPath = sys_get_temp_dir().'/'.Str::uuid().'.pdf';

        $result = Process::timeout(self::CONVERSION_TIMEOUT_SECONDS)->run([
            'gs',
            '-q',
            '-dNOPAUSE',
            '-dBATCH',
            '-dSAFER',
            '-sDEVICE=pdfwrite',
            '-dCompatibilityLevel=1.4',
            '-o',
            $convertedPath,
            $path,
        ]);

        if ($result->successful() && $this->isReadable($convertedPath)) {
            return ['path' => $convertedPath, 'is_temporary' => true];
        }

        if (is_file($convertedPath)) {
            unlink($convertedPath);
        }

        return null;
    }

    private function isReadable(string $path): bool
    {
        if (!is_file($path)) {
            return false;
        }

        try {
            return (new PdfReader(new PdfParser(StreamReader::createByFile($path))))->getPageCount() > 0;
        } catch (Throwable) {
            return false;
        }
    }
}
