<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 11 Jul 2025 17:38:10 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Portfolio;

use App\Actions\RetinaAction;
use App\Events\UploadPortfolioToR2Event;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;

class UploadPortfolioZipImages extends RetinaAction
{
     public function handle(CustomerSalesChannel $customerSalesChannel, string $randomString): string
    {
        $totalImagePortofolio = TotalImagePortfolios::run($customerSalesChannel);
        $fullPath = $this->buildFilePath($customerSalesChannel, $totalImagePortofolio);

        // dd($fullPath);
        // Return existing file if available
        // if (CheckCatalogueFileExistsInR2::run($fullPath)) {
        //     return $this->generateDownloadResponse($fullPath);
        // }

        // Generate and upload new zip file
        $result = $this->createAndUploadZip($customerSalesChannel, $totalImagePortofolio, $fullPath);

        UploadPortfolioToR2Event::dispatch($result, $randomString);
        return $result;
    }

    private function buildFilePath(CustomerSalesChannel $customerSalesChannel, int $totalImagePortofolio ): string
    {
        $bucketName = config('filesystems.disks.zip-r2.bucket', 'dev-storage');

        return sprintf(
            '%s/%s/portfolio_images_%s.zip',
            $bucketName,
            $customerSalesChannel->id,
            $totalImagePortofolio
        );
    }

    private function generateDownloadResponse(string $fullPath): string
    {
        return GenerateDownloadLinkFileFromCatalogueIrisR2::run($fullPath);
    }

    private function createAndUploadZip(
        CustomerSalesChannel $customerSalesChannel,
        int $totalImagePortfolios,
        string $fullPath
    ): string {
        $tempZipPath = null;

        try {
            $tempZipPath = PortfoliosZipExportToLocal::run(
                $customerSalesChannel,
                $totalImagePortfolios
            );

            if (!UploadFileToCatalogueIrisR2::run($tempZipPath, $fullPath)) {
                throw new \Exception('Failed to upload zip file to R2');
            }

            return $this->generateDownloadResponse($fullPath);

        } catch (\Exception $e) {
            return $e->getMessage();
        } finally {
            $this->cleanupTempFile($tempZipPath);
        }
    }

    private function cleanupTempFile(?string $filePath): void
    {
        if ($filePath && file_exists($filePath)) {
            unlink($filePath);
        }
    }


    public function authorize(ActionRequest $request): bool
    {
        $customerSalesChannel = $request->route()->customerSalesChannel;
        if ($customerSalesChannel->customer_id != $request->user()->customer->id) {
            return false;
        }

        return true;
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request) : string
    {
        $this->initialisation($request);

        // generte ramdon number
         $randomString = Str::random(10);

        UploadPortfolioZipImages::dispatch($customerSalesChannel, $randomString);

        return $randomString;
    }
}
