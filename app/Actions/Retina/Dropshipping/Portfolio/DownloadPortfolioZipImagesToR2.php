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

class DownloadPortfolioZipImagesToR2 extends RetinaAction
{

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

        // get all files from r2 related to customer sales channel
        // $files = $this->r2Service->listFiles("",true);
        // Log::info($files);
        // get the group from model data
        $group = Arr::get($modelData, 'group', 'all');


        // TODO: can be update from env
        $expiresIn = 10;
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
            'ids' => ['nullable', 'array'],
            'group' => ['nullable', 'string']
        ];
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        if (! blank($request->get('ids'))) {
            $this->set('ids', explode(',', $request->get('ids')));
        }

        if ($request->has('group')) {
            $this->set('group', $request->input('group'));
        }
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): array
    {
        $this->initialisation($request);
        return $this->handle($customerSalesChannel, $this->validatedData);
    }
}
