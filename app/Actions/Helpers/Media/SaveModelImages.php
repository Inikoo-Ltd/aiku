<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 13:34:03 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Media;

use App\Actions\Helpers\Media\Hydrators\MediaHydrateMultiplicity;
use App\Actions\Helpers\Media\Hydrators\MediaHydrateUsage;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Helpers\Media;
use App\Models\Masters\MasterAsset;
use App\Models\Web\WebBlock;
use Lorisleiva\Actions\Concerns\AsAction;
use stdClass;

class SaveModelImages
{
    use AsAction;

    public function handle(MasterAsset|Product|WebBlock|ProductCategory $model, array $imageData, string $scope = 'image', string $mediaScope = 'images'): Media
    {
        $group_id        = $model->group_id;
        $organisation_id = $model->organisation_id;

        $checksum = md5_file($imageData['path']);


        $media = Media::where('collection_name', $mediaScope)->where('group_id', $group_id)->where('checksum', $checksum)->first();

        if (!$media) {
            data_set($imageData, 'checksum', $checksum);
            $media = StoreMediaFromFile::run($model, $imageData, $mediaScope);
        } elseif ($model->images()->where('media_id', $media->id)->exists()) {
            return $media;
        }


        if ($media) {
            $model->images()->attach(
                [
                    $media->id => [
                        'group_id'        => $group_id,
                        'organisation_id' => $organisation_id,
                        'scope'           => $scope,
                        'data'            => json_encode(new stdClass())
                    ]
                ]
            );
            if (!$model instanceof WebBlock && $model->images()->count() == 1) {
                $model->updateQuietly(['image_id' => $media->id]);
            }

            MediaHydrateUsage::dispatch($media);
            MediaHydrateMultiplicity::dispatch($media);
        }

        return $media;
    }


}
