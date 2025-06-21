<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Jun 2025 22:08:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product;

use App\Actions\Helpers\Images\GetPictureSources;
use App\Models\Catalogue\Product;
use App\Models\Helpers\Media;
use Lorisleiva\Actions\Concerns\AsObject;

class UpdateProductImagesJson
{
    use AsObject;

    public function handle(Product $product): Product
    {
        $imagesData = [
            'main' => $this->getMainImageData($product)
        ];

        $product->update(
            [
                'images' => $imagesData
            ]
        );
        $product->refresh();

        return $product;
    }

    public function getMainImageData(Product $product): array
    {
        $media = null;
        if ($product->image_id) {
            $media = Media::find($product->image_id);
        }

        if (!$media) {
            return [];
        }

        $imageOriginal  = $media->getImage();
        $imageGallery   = $media->getImage()->resize(0, 300);
        $imageThumbnail = $media->getImage()->resize(0, 48);

        return [
            'original'  => GetPictureSources::run($imageOriginal),
            'gallery'   => GetPictureSources::run($imageGallery),
            'thumbnail' => GetPictureSources::run($imageThumbnail),
        ];
    }


}
