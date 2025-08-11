<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 08-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Dropshipping\Product;

use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsAction;
use ZipStream\ZipStream;
use Sentry\Laravel\Facade as Sentry;

class ProductZipExport
{
    use AsAction;

    /**
     * @throws \ZipStream\Exception\OverflowException
     */
    public function handle(Shop|ProductCategory|Product|Collection $parent, string $filename): void
    {
        $zip         = new ZipStream(
            sendHttpHeaders: true,
            outputName: $filename,
        );


        $imagesData = $this->getImages($parent);


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


    public function getImages(Shop|ProductCategory|Product|Collection $parent): array
    {
        $imagesData = [];
        $products = [];

        if ($parent instanceof Shop) {
            $products = $parent->products
                ->whereIn('state', [ProductStateEnum::ACTIVE, ProductStateEnum::DISCONTINUING])
                ->where('is_main', true);
        } elseif ($parent instanceof ProductCategory) {
            $products = $parent->getProducts();
        } elseif ($parent instanceof Product) {
            $product = $parent;
            foreach ($product->images as $image) {
                $imagesData[$image->id] = [
                    'filename' => strtolower($product->code) . '_' . $image->id . '.' . $image->extension,
                    'image'    => $image
                ];
            }
            return $imagesData;
        } elseif ($parent instanceof Collection) {
            $products = $parent->products
                ->whereIn('state', [ProductStateEnum::ACTIVE, ProductStateEnum::DISCONTINUING])
                ->where('is_main', true);
        }

        foreach ($products as $product) {
            /** @var Product $product */
            $product = $product;
            foreach ($product->images as $image) {
                $imagesData[$image->id] = [
                    'filename' => strtolower($product->code) . '_' . $image->id . '.' . $image->extension,
                    'image'    => $image
                ];
            }
        }

        return $imagesData;
    }
}
