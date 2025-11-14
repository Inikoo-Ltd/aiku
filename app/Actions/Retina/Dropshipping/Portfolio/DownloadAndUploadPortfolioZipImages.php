<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 11 Jul 2025 17:38:10 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Portfolio;

use App\Actions\RetinaAction;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;

class DownloadAndUploadPortfolioZipImages extends RetinaAction
{
     public function handle(CustomerSalesChannel $customerSalesChannel, array $modelData): array
    {
        $group = Arr::get($modelData, 'group', 'all') ?? 'all';
        $fullPath = $this->buildFilePath($customerSalesChannel, $group);

        // Return existing file if available
        if (CheckCatalogueFileExistsInR2::run($fullPath)) {
            return $this->generateDownloadResponse($fullPath);
        }

        // Generate and upload new zip file
        return $this->createAndUploadZip($customerSalesChannel, $modelData, $fullPath);
    }

    private function buildFilePath(CustomerSalesChannel $customerSalesChannel, string $group): string
    {
        $bucketName = config('filesystems.disks.zip-r2.bucket', 'dev-storage');
        $slug = Str::slug($customerSalesChannel->name ?? $customerSalesChannel->reference);

        return sprintf(
            '%s/%s/image_%s_%s.zip',
            $bucketName,
            $customerSalesChannel->id,
            $slug,
            $group
        );
    }

    private function generateDownloadResponse(string $fullPath): array
    {
        return [
            'download_url' => GenerateDownloadLinkFileFromCatalogueIrisR2::run($fullPath),
        ];
    }

    private function createAndUploadZip(
        CustomerSalesChannel $customerSalesChannel,
        array $modelData,
        string $fullPath
    ): array {
        $tempZipPath = null;

        try {
            $tempZipPath = PortfoliosZipExportToLocal::run(
                $customerSalesChannel,
                Arr::get($modelData, 'ids', []),
                Arr::get($modelData, 'group', 'all')
            );

            if (!UploadFileToCatalogueIrisR2::run($tempZipPath, $fullPath)) {
                throw new \Exception('Failed to upload zip file to R2');
            }

            return $this->generateDownloadResponse($fullPath);

        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
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
