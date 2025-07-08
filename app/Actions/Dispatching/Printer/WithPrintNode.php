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
use Rawilk\Printing\Api\PrintNode\Enums\ContentType;
use Rawilk\Printing\Api\PrintNode\PendingPrintJob;
use Rawilk\Printing\Api\PrintNode\PrintNode;
use Rawilk\Printing\Api\PrintNode\Resources\Printer;
use Rawilk\Printing\Api\PrintNode\Resources\PrintJob;

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
                $apiKey = Arr::get($group->settings, 'printing.api_key');
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

    public function printPdfFromPdfUri(string $title, int $printId, string $pdfUri) {
        $this->ensureClientInitialized();
        $pendingJob = PendingPrintJob::make()
            ->setContent($pdfUri)
            ->setContentType(ContentType::PdfUri)
            ->setPrinter($printId)
            ->setTitle($title)
            ->setSource(config('app.name'));

        return PrintJob::create($pendingJob);
    }
}
