<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 08-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Dropshipping\Portfolio;

use App\Models\CRM\Customer;
use App\Models\Dropshipping\Platform;
use Lorisleiva\Actions\Concerns\AsAction;
use Storage;

class PortfoliosZipExport
{
    use AsAction;

    public function handle(Customer $customer, Platform $platform): string
    {


        $imageItems = $customer->portfolios()
            ->where('platform_id', $platform->id)
            ->with(['item'])
            ->with(['item.images'])
            ->get()
            ->pluck('item.images');


        $mediaRoot = storage_path('media');
        $files = [];
        $counter = 1;

        foreach ($imageItems as $imageItem) {
            $images = $imageItem;

            if ($images->isEmpty()) {
                continue;
            }

            foreach ($images as $image) {
                $fullPath = $image->getPath();

                $disk = Storage::disk($image->disk);
                $relativePath = ltrim(str_replace($mediaRoot, '', $fullPath), '/');

                if (!$disk->exists($relativePath)) {
                    continue;
                }

                $files[] = [
                    'path' => $relativePath,
                    'name' => 'image_' . $counter . '.jpg',
                ];
                $counter++;
            }
        }

        $zip = new \ZipArchive();
        $zipFileName = 'portfolios_' . now()->format('Ymd') . '.zip';
        $zipFilePathRelative = 'tmp/' . $zipFileName;
        $zipDisk = Storage::disk('local');

        if (!$zipDisk->exists('tmp')) {
            $zipDisk->makeDirectory('tmp');
        }

        $zipFilePath = $zipDisk->path($zipFilePathRelative);

        if ($zip->open($zipFilePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            foreach ($files as $file) {
                $absolutePath = Storage::disk('media')->path($file['path']);
                $zip->addFile($absolutePath, $file['name']);
            }
            $zip->close();
        } else {
            throw new \Exception('Could not create zip file');
        }


        return $zipFilePath;
    }
}
