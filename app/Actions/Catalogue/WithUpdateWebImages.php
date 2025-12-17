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
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterCollection;
use App\Models\Masters\MasterProductCategory;

trait WithUpdateWebImages
{
    public function updateWebImages(Product|ProductCategory|Collection|MasterAsset|MasterProductCategory|MasterCollection $model): Product|ProductCategory|Collection|MasterAsset|MasterProductCategory|MasterCollection
    {
        $webImagesData = [
            'main' => $this->getMainWebImageData($model),
            'all'  => $this->getAllWebImageData($model)
        ];


        $model->update(
            [
                'web_images' => $webImagesData
            ]
        );


        if ($model instanceof Product && $model->wasChanged('web_images')) {
            $model->update([
                'images_updated_at' => now()
            ]);
        }


        $model->refresh();

        return $model;
    }

    public function getMainWebImageData(Product|ProductCategory|Collection|MasterAsset|MasterProductCategory|MasterCollection $model): array
    {
        $media = null;
        if ($model->image_id) {
            $media = Media::find($model->image_id);
        }

        if (!$media) {
            return [];
        }

        $imageOriginal  = $media->getImage();
        $imageGallery   = $media->getImage()->resize(0, 600);
        $imageThumbnail = $media->getImage()->resize(0, 48);

        return [
            'original'  => GetPictureSources::run($imageOriginal),
            'gallery'   => GetPictureSources::run($imageGallery),
            'thumbnail' => GetPictureSources::run($imageThumbnail),
        ];
    }

    public function getAllWebImageData(Product|ProductCategory|Collection|MasterAsset|MasterProductCategory|MasterCollection $model): array
    {
        $images = [];

        /** @var Media $media */
        foreach ($model->images()->orderBy('model_has_media.position')->get() as $media) {
            $imageOriginal  = $media->getImage();
            $imageGallery   = $media->getImage()->resize(0, 600);
            $imageThumbnail = $media->getImage()->resize(0, 48);

            $images[] = [
                'original'  => GetPictureSources::run($imageOriginal),
                'gallery'   => GetPictureSources::run($imageGallery),
                'thumbnail' => GetPictureSources::run($imageThumbnail),
            ];
        }

        return $images;
    }


}
