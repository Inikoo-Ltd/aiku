<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 07 Jul 2025 22:55:37 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Printer;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf;
use Rawilk\Printing\Api\PrintNode\Enums\ContentType;
use Rawilk\Printing\Api\PrintNode\PendingPrintJob;
use Rawilk\Printing\Api\PrintNode\PrintNode;
use Rawilk\Printing\Api\PrintNode\Resources\Printer;
use Rawilk\Printing\Api\PrintNode\Resources\PrintJob;
use Illuminate\Validation\ValidationException;


trait WithPrintNode
{
    protected bool $clientInitialized = false;

    public function ensureClientInitialized(): void
    {
        $isProduction = app()->isProduction();

        if (!$this->clientInitialized) {
            if (!$isProduction) {
                $driver = config('printing.driver');
                $apiKey = config('printing.drivers.'.$driver.'.key');
            } else {
                $group  = group();
                $apiKey = Arr::get($group->settings, 'printnode.api_key');
            }
            if (empty($apiKey)) {
                throw ValidationException::withMessages([
                    'messages' => __('Printnode API key is not set for your group!'),
                ]);
            }
            PrintNode::setApiKey($apiKey);
            $this->clientInitialized = true;
        }
    }

    public function isExistPrinter(int $printerId): bool
    {
        $this->ensureClientInitialized();
        try {
            Printer::retrieve($printerId);

            return true;
        } catch (Exception $e) {
            Log::error('Error checking printer existence: '.$e->getMessage());

            return false;
        }
    }

    public function printPdf(string $title, int $printId, string $pdfBase64): PrintJob
    {
        $this->ensureClientInitialized();
        $content    = Str::fromBase64($pdfBase64);
        $pendingJob = PendingPrintJob::make()
            ->setContent($content)
            ->setContentType(ContentType::PdfBase64)
            ->setPrinter($printId)
            ->setTitle($title)
            ->setSource(config('app.name'));

        return PrintJob::create($pendingJob);
    }

    public function printRawBase64(string $title, int $printId, string $rawBase64): PrintJob
    {
        // Convert raw base64 content to PDF using LaravelMPDF
        $content = Str::fromBase64($rawBase64);
        $pdf = LaravelMpdf::loadHTML($content);
        $pdfContent = $pdf->output();

        $this->ensureClientInitialized();
        $pendingJob = PendingPrintJob::make()
            ->setContent($pdfContent)
            ->setContentType(ContentType::PdfBase64)
            ->setPrinter($printId)
            ->setTitle($title)
            ->setSource(config('app.name'));

        return PrintJob::create($pendingJob);
    }

    public function printPdfFromPdfUri(string $title, int $printId, string $pdfUri) {
        $this->ensureClientInitialized();
        $pendingJob = PendingPrintJob::make()
            ->setUrl($pdfUri)
            ->setContentType(ContentType::PdfUri)
            ->setPrinter($printId)
            ->setTitle($title)
            ->setSource(config('app.name'));

        return PrintJob::create($pendingJob);
    }
}
