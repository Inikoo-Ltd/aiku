<?php

/*
 * Author: Eka Yudinata <ekayudinatha@gmail.com>
 * Created on: 08-05-2025, Bali, Indonesia
 * Github: https://github.com/ekayudinatha
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Dropshipping\Portfolio;

use App\Models\Catalogue\Product;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use ZipArchive;
use Sentry\Laravel\Facade as Sentry;

class PortfoliosZipExportToLocal
{
    use AsAction;

    /**
     * Create a ZIP file in storage/app/temp and return its absolute path.
     *
     * @param CustomerSalesChannel $customerSalesChannel
     * @param array $ids  Portfolio IDs to export (empty = all)
     * @return string  Full path to the generated ZIP file
     */
    public function handle(CustomerSalesChannel $customerSalesChannel, $ids = [], $group = 'all'): string
    {
        $slug = Str::slug($customerSalesChannel->name ?? $customerSalesChannel->reference);

        $tempDir = sys_get_temp_dir() . '/' . uniqid('portfolios_zip_export_');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $zipFilename = 'images_'.$group.'_'.$slug.'.zip';
        $tempZipPath = sys_get_temp_dir() . '/' . $zipFilename;

        $zip = new ZipArchive();
        if ($zip->open($tempZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Cannot create ZIP file at {$tempZipPath}");
        }

    try {
        $imagesData = $this->getImages($customerSalesChannel, $ids);

        foreach ($imagesData as $imageId => $imageData) {
            $image = $imageData['image'];
            $disk = Storage::disk($image->disk);
            $filePath = $image->getPathRelativeToRoot();

            if (!$disk->exists($filePath)) {
                continue;
            }

            try {
                $stream = $disk->readStream($filePath);
                if (!$stream) {
                    continue;
                }

                // Read the file contents
                $contents = stream_get_contents($stream);
                fclose($stream);

                // Add file to zip
                $zip->addFromString($imageData['filename'], $contents);
            } catch (\Throwable $e) {
                Sentry::captureException($e, [
                    'extra' => [
                        'image_id' => $imageId,
                        'file_path' => $filePath,
                    ],
                ]);
            }
        }

        // Check if zip is empty and add error message if needed
        if ($zip->count() === 0) {
            $zip->addFromString('error.txt', 'No images were found to include in the zip file.');
        }
        $zip->close();

        return $tempZipPath;

    } catch (\Exception $e) {
        // Clean up in case of error
        if (file_exists($tempZipPath)) {
            unlink($tempZipPath);
        }
        if (is_dir($tempDir)) {
            @rmdir($tempDir);
        }
        throw $e;
    }
    }

    /**
     * Gather image data from the requested portfolios.
     */
    public function getImages(CustomerSalesChannel $customerSalesChannel, $ids = []): array
    {
        $imagesData = [];

        $query = $customerSalesChannel->portfolios();

        if (!blank($ids)) {
            $query->whereIn('id', $ids);
        }

        $portfolios = $query->get();

        foreach ($portfolios as $portfolio) {
            if ($portfolio->item instanceof Product) {
                /** @var Product $product */
                $product = $portfolio->item;

                foreach ($product->images as $image) {
                    $imagesData[$image->id] = [
                        'filename' => strtolower($product->code) . '__' . $image->id . '.' . $image->extension,
                        'image'    => $image,
                    ];
                }
            }
        }

        return $imagesData;
    }
}
