<?php

namespace App\Actions\Dispatching\Printer;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Rawilk\Printing\Api\PrintNode\Enums\ContentType;
use Rawilk\Printing\Api\PrintNode\PendingPrintJob;
use Rawilk\Printing\Api\PrintNode\PrintNode;
use Rawilk\Printing\Api\PrintNode\PrintNodeClient;
use Rawilk\Printing\Api\PrintNode\Resources\Computer;
use Rawilk\Printing\Api\PrintNode\Resources\Printer;
use Rawilk\Printing\Api\PrintNode\Resources\PrintJob;
use Rawilk\Printing\Api\PrintNode\Util\RequestOptions;
use Rawilk\Printing\Facades\Printing;

trait WithPrintNode
{
    protected bool $clientInitialized = false;

    public function ensureClientInitialized(): void
    {
        $isProduction = app()->isProduction();

        if (!$this->clientInitialized) {
            if (!$isProduction) {
                $driver = config('printing.driver');
                $apiKey = config('printing.drivers.' . $driver . '.key', null);
            }else {
                $group = group();
                $apiKey = Arr::get($group->settings, 'printing.api_key', null);
            }
            PrintNode::setApiKey($apiKey);
            $this->clientInitialized = true;
        }
    }
    
    public function isExistPrinter(int $printerId): bool
    {
        $this->ensureClientInitialized();
        try {
            $printer = Printer::retrieve($printerId);
            return $printer !== null;
        } catch (Exception $e) {
            Log::error('Error checking printer existence: ' . $e->getMessage());
            return false;
        }
    }

    public function printPdf(string $title, int $printId, string $pdfBaset64) {
        $this->ensureClientInitialized();
        $content = base64_decode($pdfBaset64);
        $pendingJob = PendingPrintJob::make()
            ->setContent($content)
            ->setContentType(ContentType::PdfBase64)
            ->setPrinter($printId)
            ->setTitle($title)
            ->setSource(config('app.name'));

        return PrintJob::create($pendingJob);
    }
}
