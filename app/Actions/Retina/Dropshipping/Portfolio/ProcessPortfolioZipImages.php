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

use function Deployer\timestamp;

class ProcessPortfolioZipImages extends RetinaAction
{
    public string $jobQueue = 'urgent';

    public function handle(CustomerSalesChannel $customerSalesChannel, string $randomString): string
    {
        $filename   = "portfolio_images_$randomString.zip";
        $fullPath   = "portfolio_images/$customerSalesChannel->id/$filename";

        $result = $this->createAndUploadZip($customerSalesChannel, $filename,$fullPath);

        UploadPortfolioToR2Event::dispatch($result, $randomString);

        return $result;
    }


    private function generateDownloadResponse(string $fullPath): string
    {
        return GenerateDownloadLinkFileFromCatalogueIrisR2::run($fullPath);
    }

    private function createAndUploadZip(CustomerSalesChannel $customerSalesChannel,string $filename,string $fullPath): string {
        $tempZipPath = null;

        try {
            $tempZipPath = CreteCustomerSalesChannelPortfolioImagesZip::run(
                $customerSalesChannel,
                $filename
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


}
