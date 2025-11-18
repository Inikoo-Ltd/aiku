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
use Illuminate\Support\Facades\Log;

class ProcessPortfolioZipImages extends RetinaAction
{
    public string $jobQueue = 'urgent';

    public function handle(CustomerSalesChannel $customerSalesChannel, string $randomString): string
    {
        $filename   = "portfolio_images_$randomString.zip";
        $fullPath   = "portfolio_images/$customerSalesChannel->id/$filename";

        $result = $this->createAndUploadZip($customerSalesChannel, $filename, $fullPath);

        UploadPortfolioToR2Event::dispatch($result, $randomString);

        return $result;
    }


    private function generateDownloadResponse(string $fullPath): string
    {
        return GenerateDownloadLinkFileFromCatalogueIrisR2::run($fullPath);
    }

    private function createAndUploadZip(CustomerSalesChannel $customerSalesChannel, string $filename, string $fullPath): string
    {
        $tempZipArr = [];

        try {
            $tempZipArr = CreateCustomerSalesChannelPortfolioImagesZip::run(
                $customerSalesChannel,
                $filename
            );

            if (!UploadFileToCatalogueIrisR2::run($tempZipArr['file_path'], $fullPath)) {
                throw new \Exception('Failed to upload zip file to R2');
            }

            $downloadUrl = $this->generateDownloadResponse($fullPath);

            UpdatePortfolioZipImagesProcess::run([
                'id' => $tempZipArr['process_id'],
                'file_path' => $fullPath,
                'download_url' => $downloadUrl,
            ]);

            return $downloadUrl;
        } catch (\Exception $e) {
            return $e->getMessage();
        } finally {
            if (isset($tempZipArr['file_path']) && $tempZipArr['file_path'] && file_exists($tempZipArr['file_path'])) {
                unlink($tempZipArr['file_path']);
            }
        }
    }


}
