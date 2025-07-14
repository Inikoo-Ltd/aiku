<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 08-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Dropshipping\Portfolio;

use App\Models\Catalogue\Product;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use ZipStream\ZipStream;
use Sentry\Laravel\Facade as Sentry;

class PortfoliosZipExport
{
    use AsAction;

    /**
     * @throws \ZipStream\Exception\OverflowException
     */
    public function handle(CustomerSalesChannel $customerSalesChannel): void
    {
        $zipFileName = 'images_'.Str::slug($customerSalesChannel->name ?? $customerSalesChannel->reference).'.zip';
        $zip         = new ZipStream(
            sendHttpHeaders: true,
            outputName: $zipFileName,
        );


        $imagesData = $this->getImages($customerSalesChannel);


        foreach ($imagesData as $imageId => $imageData) {
            $image = $imageData['image'];
            $disk  = Storage::disk($image->disk);
            if (!$disk->exists($image->getPathRelativeToRoot())) {
                unset($imagesData[$imageId]);
                continue;
            }

            try {
                $stream = $disk->readStream($image->getPathRelativeToRoot());
                if (!$stream) {
                    continue;
                }

                $fileName = $imageData['filename'];

                $zip->addFileFromStream($fileName, $stream);
            } catch (\Exception $e) {
                Sentry::captureException($e, [
                    'extra' => [
                        'image_id'  => $imageId,
                        'file_path' => $image->getPathRelativeToRoot()
                    ]
                ]);
            } finally {
                if (isset($stream) && is_resource($stream)) {
                    fclose($stream);
                }
            }
        }

        $zip->finish();
    }


    public function getImages(CustomerSalesChannel $customerSalesChannel): array
    {
        $imagesData = [];
        foreach ($customerSalesChannel->portfolios as $portfolio) {
            if ($portfolio->item instanceof Product) {
                /** @var Product $product */
                $product = $portfolio->item;
                foreach ($product->images as $image) {
                    $imagesData[$image->id] = [
                        'filename' => strtolower($product->code).'__'.$image->id.'.'.$image->extension,
                        'image'    => $image
                    ];
                }
            }
        }

        return $imagesData;
    }

}
