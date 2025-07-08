<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 13:34:03 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Media;

use App\Actions\Catalogue\Product\UpdateProductWebImages;
use App\Actions\Catalogue\ProductCategory\UpdateProductCategoryWebImages;
use App\Actions\Goods\TradeUnit\Hydrators\TradeUnitHydrateImages;
use App\Actions\Helpers\Media\Hydrators\MediaHydrateMultiplicity;
use App\Actions\Helpers\Media\Hydrators\MediaHydrateUsage;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Media;
use App\Models\Masters\MasterAsset;
use App\Models\Web\WebBlock;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use stdClass;

class SaveModelImages
{
    use AsAction;

    public function handle(MasterAsset|Product|WebBlock|ProductCategory|TradeUnit $model, array $mediaData, string $mediaScope = 'images', $modelHasMediaData = []): Media
    {
        $group_id        = $model->group_id;
        $organisation_id = $model->organisation_id;

        $checksum = md5_file($mediaData['path']);


        $media = Media::where('collection_name', $mediaScope)->where('group_id', $group_id)->where('checksum', $checksum)->first();

        if (!$media) {
            data_set($mediaData, 'checksum', $checksum);
            $media = StoreMediaFromFile::run($model, $mediaData, $mediaScope);
        } elseif ($model->images()->where('media_id', $media->id)->exists()) {
            return $media;
        }


        data_set($modelHasMediaData, 'group_id', $group_id);
        data_set($modelHasMediaData, 'organisation_id', $organisation_id);
        if (!Arr::has($modelHasMediaData, 'scope')) {
            data_set($modelHasMediaData, 'scope', 'image');
        }
        if (!Arr::has($modelHasMediaData, 'data')) {
            data_set($modelHasMediaData, 'data', json_encode(new stdClass()));
        }

        if ($media) {

            $model->images()->attach(
                [
                    $media->id => $modelHasMediaData
                ]
            );
            if (!$model instanceof WebBlock && $model->images()->count() == 1) {
                $model->updateQuietly(['image_id' => $media->id]);
                $model->refresh();
                if ($model instanceof Product) {
                    UpdateProductWebImages::run($model);
                } elseif ($model instanceof ProductCategory) {
                    UpdateProductCategoryWebImages::run($model);
                }
            }

            if ($model instanceof TradeUnit) {
                TradeUnitHydrateImages::dispatch($model)->delay(now()->addSeconds(5));
            }

            MediaHydrateUsage::dispatch($media);
            MediaHydrateMultiplicity::dispatch($media);
        }

        return $media;
    }


}
