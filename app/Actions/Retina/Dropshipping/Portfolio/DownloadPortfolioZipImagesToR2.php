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
use App\Actions\Retina\Dropshipping\Portfolio\DownloadPortfolioZipImagesToR2Service;
use Lorisleiva\Actions\ActionRequest;

class DownloadPortfolioZipImagesToR2 extends RetinaAction
{

    protected $r2Service;

    public function __construct(DownloadPortfolioZipImagesToR2Service $r2Service)
    {
        $this->r2Service = $r2Service;
    }
    /**
     * Create a zip file containing the image_not_found.png
     *
     * @return array Returns an array with 'success' status and 'tempZipPath' if successful
     */
    private function createImageZip(): array
    {
        // Create a temporary directory
        $tempDir = sys_get_temp_dir() . '/' . uniqid('portfolio_zip_');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // Define source and destination paths
        $sourceImage = public_path('image_not_found.png');
        $zipFilename = 'portfolio_images_' . uniqid() . '.zip';
        $tempZipPath = sys_get_temp_dir() . '/' . $zipFilename;

        // Create a new ZipArchive instance
        $zip = new \ZipArchive();

        if ($zip->open($tempZipPath, \ZipArchive::CREATE) !== true) {
            return [
                'success' => false,
                'message' => 'Failed to create zip file'
            ];
        }

        try {
            // Add the image to the zip
            if (file_exists($sourceImage)) {
                $zip->addFile($sourceImage, 'image_not_found.png');
            } else {
                // If the image doesn't exist, create a simple text file as fallback
                $fallbackContent = "Image not found at: " . $sourceImage;
                $zip->addFromString('error.txt', $fallbackContent);
            }

            // Close the zip file
            $zip->close();

            return [
                'success' => true,
                'tempZipPath' => $tempZipPath,
                'tempDir' => $tempDir,
                'zipFilename' => $zipFilename,
                'filePath' => $zipFilename  // Add this for consistency with handle method
            ];
        } catch (\Exception $e) {
            // Clean up in case of error
            if (file_exists($tempZipPath)) {
                unlink($tempZipPath);
            }
            if (is_dir($tempDir)) {
                rmdir($tempDir);
            }

            return [
                'success' => false,
                'message' => 'Error creating zip file: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Handle the request to create and upload a zip file
     */
    public function handle(CustomerSalesChannel $customerSalesChannel, array $modelData): array
    {
        // get all files from r2 related to customer sales channel
        // $files = $this->r2Service->listFiles("",true);
        // Log::info($files);


        // return [
        //     'success' => true,
        //     'files' => $files
        // ];


        $expiresIn = 10;
        // check if zip file already exists related to customer sales channel
        $bucketName = (string) config('filesystems.disks.zip-r2.bucket', 'dev-storage');
        $customerId = (string) $customerSalesChannel->id;
        $filePath = $customerId . '/image.zip';
        $fullPath = $bucketName . '/' . $filePath;
        Log::info('Checking file existence: ' . $fullPath);

        $fileExists = $this->r2Service->fileExists("{$fullPath}");

        if ($fileExists) {
            // Generate authenticated URL with Cloudflare WAF token
            $downloadUrl = $this->r2Service->generateAuthenticatedUrl("{$fullPath}", $expiresIn);
            Log::info('Authenticated URL: ' . $downloadUrl);

            return [
                'success' => true,
                'message' => 'Zip file already exists',
                'filename' => 'image.zip',
                'url' => $downloadUrl,
                'exists' => true
            ];
        } else {
            // Create a temporary file for the zip
            $tempDir = sys_get_temp_dir() . '/' . uniqid('portfolio_zip_');
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }
            $tempZipPath = $tempDir . '/' . 'image.zip';

            try {
                // Generate the zip file using PortfoliosZipExport
                $zip = new PortfoliosZipExport();
                $zip->handle($customerSalesChannel, Arr::get($modelData, 'ids', []), $tempZipPath);

                // Upload the zip file to R2
                $uploadResult = $this->r2Service->uploadZip($tempZipPath, $filePath);

                if (!$uploadResult) {
                    throw new \Exception('Failed to upload zip file to R2');
                }

                // Clean up temporary files
                if (file_exists($tempZipPath)) {
                    unlink($tempZipPath);
                }
                if (is_dir($tempDir)) {
                    rmdir($tempDir);
                }

                // Generate authenticated URL for the new file
                $downloadUrl = $this->r2Service->generateAuthenticatedUrl($fullPath, $expiresIn);

                return [
                    'success' => true,
                    'message' => 'Zip file created and uploaded successfully',
                    'filename' => 'image.zip',
                    'url' => $downloadUrl,
                    'exists' => false
                ];

            } catch (\Exception $e) {
                // Clean up in case of error
                if (isset($tempZipPath) && file_exists($tempZipPath)) {
                    unlink($tempZipPath);
                }
                if (isset($tempDir) && is_dir($tempDir)) {
                    @rmdir($tempDir);
                }

                Log::error('Error creating portfolio zip: ' . $e->getMessage());

                return [
                    'success' => false,
                    'message' => 'Error creating portfolio zip: ' . $e->getMessage()
                ];
            }
        }




        // Create the zip file use existing function
        $zipResult = $this->createImageZip();

        if (!$zipResult['success']) {
            return [
                'success' => false,
                'message' => $zipResult['message']
            ];
        }

        // Upload the zip file to R2
        try {
            $fileContents = file_get_contents($zipResult['tempZipPath']);
            $bucketName = config('filesystems.disks.zip-r2.bucket', 'dev-storage');
            $customerSalesChannelId = $customerSalesChannel->id;
            $bucketPath = $bucketName . '/' . $customerSalesChannelId . '/' . $zipResult['zipFilename'];



            $this->r2Service->uploadZip($zipResult['tempZipPath'], $bucketPath);

            return [
                'success' => true,
                'message' => 'Zip file created and uploaded to R2 bucket',
                'filename' => $zipResult['zipFilename'],
                'url' => $this->r2Service->getPublicUrl($bucketPath)
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to upload zip file: ' . $e->getMessage()
            ];
        } finally {
            // Clean up temporary files
            if (isset($zipResult['tempZipPath']) && file_exists($zipResult['tempZipPath'])) {
                unlink($zipResult['tempZipPath']);
            }
            if (isset($zipResult['tempDir']) && is_dir($zipResult['tempDir'])) {
                rmdir($zipResult['tempDir']);
            }
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

    public function rules(): array
    {
        return [
            'ids' => ['nullable', 'array']
        ];
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        if (! blank($request->get('ids'))) {
            $this->set('ids', explode(',', $request->get('ids')));
        }
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): array
    {
        $this->initialisation($request);
        return $this->handle($customerSalesChannel, $this->validatedData);
    }
}
