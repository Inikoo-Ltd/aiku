<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Jun 2025 00:00:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue;

use App\Actions\Helpers\Images\GetPictureSources;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Helpers\Media;

trait WithUpdateWebImages
{
    public function updateWebImages(Product|ProductCategory|Collection $model): Product|ProductCategory|Collection
    {
        $webImagesData = [
            'main' => $this->getMainWebImageData($model)
        ];

        $model->update(
            [
                'web_images' => $webImagesData
            ]
        );
        $model->refresh();
        return $model;
    }

    public function getMainWebImageData(Product|ProductCategory|Collection $model): array
    {
        $media = null;
        if ($model->image_id) {
            $media = Media::find($model->image_id);
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
