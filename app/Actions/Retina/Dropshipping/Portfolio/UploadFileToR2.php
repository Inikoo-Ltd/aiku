<?php

/*
 * Author: Eka Yudinata <ekayudinatha@gmail.com>
 * Created: Fri, 11 Jul 2025 17:38:10 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Eka Yudinata
 */

namespace App\Actions\Retina\Dropshipping\Portfolio;

use App\Actions\RetinaAction;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use App\Actions\Retina\Dropshipping\Portfolio\DownloadPortfolioZipImagesToR2Service;
use App\Actions\Retina\Dropshipping\Portfolio\PortfoliosTemporaryExport;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Console\Command;

class UploadFileToR2 extends RetinaAction
{


    public string $commandSignature   = 'upload-file-to-r2';
    public string $commandDescription = 'Upload file to R2';

    use AsAction;
    private DownloadPortfolioZipImagesToR2Service $r2Service;
    private PortfoliosTemporaryExport $portfoliosExport;

    public function __construct(
        DownloadPortfolioZipImagesToR2Service $r2Service,
        PortfoliosTemporaryExport $portfoliosExport
    ) {
        $this->r2Service = $r2Service;
        $this->portfoliosExport = $portfoliosExport;
    }


    /**
     * Handle the request to create and upload a zip file
     */
    public function handle(CustomerSalesChannel $customerSalesChannel, array $modelData) : array
    {
        // get the group from model data
        $group = Arr::get($modelData, 'group', 'all');


        // TODO: can be update from env
        $expiresIn = config('services.cloudflare-zip-r2.expires_in', 60);
        $slug = Str::slug($customerSalesChannel->name ?? $customerSalesChannel->reference);
        // check if zip file already exists related to customer sales channel
        $bucketName = (string) config('filesystems.disks.zip-r2.bucket', 'dev-storage');
        $customerId = (string) $customerSalesChannel->id;
        $filePath = $customerId . '/image_'. $slug . '_' . $group . '.zip';
        $fullPath = $bucketName . '/' . $filePath;

        $fileExists = $this->r2Service->fileExists("{$fullPath}");

        if ($fileExists) {
            // Generate authenticated URL with Cloudflare WAF token
            $downloadUrl = $this->r2Service->generateAuthenticatedUrl("{$fullPath}", $expiresIn);

            return [
                'download_url' => $downloadUrl,
            ];

        } else {

            try {
                // Generate the zip file using PortfoliosZipExport
                $tempZipPath = $this->portfoliosExport->handle($customerSalesChannel, Arr::get($modelData, 'ids', []));
                // Upload the zip file to R2
                $uploadResult = $this->r2Service->uploadZip($tempZipPath, $fullPath);

                if (!$uploadResult) {
                    throw new \Exception('Failed to upload zip file to R2');
                }

                // Clean up temporary files
                if (file_exists($tempZipPath)) {
                    unlink($tempZipPath);
                }

                // Generate authenticated URL for the new file
                $downloadUrl = $this->r2Service->generateAuthenticatedUrl($fullPath, $expiresIn);

                return [
                    'download_url' => $downloadUrl,
                ];

            } catch (\Exception $e) {
                // Clean up in case of error
                if (isset($tempZipPath) && file_exists($tempZipPath)) {
                    unlink($tempZipPath);
                }
                return [
                    'error' => $e->getMessage()
                ];
            }
            finally {
                // Clean up temporary files
                if (isset($tempZipPath) && file_exists($tempZipPath)) {
                    unlink($tempZipPath);
                }
            }
        }
    }

    public function asCommand(Command $command): int
    {
        $command->info("Hello World");
        return 0;
    }

}
