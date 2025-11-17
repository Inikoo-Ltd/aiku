<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 17 Nov 2025 15:24:04 Central Indonesia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Portfolio;

use App\Models\Catalogue\Product;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use ZipArchive;
use Sentry\Laravel\Facade as Sentry;

class CreteCustomerSalesChannelPortfolioImagesZip
{
    use AsAction;


    /**
     * @throws \Exception
     */
    public function handle(CustomerSalesChannel $customerSalesChannel, string $filename): string
    {

        $tempDir = sys_get_temp_dir() . '/' . uniqid('portfolios_zip_export_');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $zipFilename = $filename;
        $tempZipPath = sys_get_temp_dir() . '/' . $zipFilename;

        $zip = new ZipArchive();
        if ($zip->open($tempZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Cannot create ZIP file at $tempZipPath");
        }

        try {
            $imagesData = $this->getImages($customerSalesChannel);

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

                    // Add a file to zip
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
    public function getImages(CustomerSalesChannel $customerSalesChannel): array
    {
        $imagesData = [];


        $portfolios = $customerSalesChannel->portfolios()->where('status', true)->get();


        /** @var \App\Models\Dropshipping\Portfolio $portfolio */
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
