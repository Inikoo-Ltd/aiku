<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 27-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Masters\MasterAsset\UI;

use App\Actions\Traits\HasBucketImages;
use App\Http\Resources\Helpers\ImagesResource;
use App\Models\Masters\MasterAsset;
use Lorisleiva\Actions\Concerns\AsObject;

class GetMasterProductImages
{
    use AsObject;
    use HasBucketImages;

    public function handle(MasterAsset $masterAsset): array
    {
        return [
            'id' => $masterAsset->id,
            'bucket_images'       => $masterAsset->bucket_images,
            'images_category_box' => $this->getImagesData($masterAsset),
            'images_update_route' => [
                'method'     => 'patch',
                'name'       => 'grp.models.master_asset.update_images',
                'parameters' => [
                    'masterAsset' => $masterAsset->id,
                ],
            ],
            'upload_images_route' => [
                'method'     => 'post',
                'name'       => 'grp.models.master_asset.upload_images',
                'parameters' => [
                    'masterAsset' => $masterAsset->id,
                ],
            ],
            'delete_images_route' => [
                'method'     => 'post',
                'name'       => 'grp.models.master_asset.delete_images',
                'parameters' => [
                    'masterAsset' => $masterAsset->id,
                    'media'   => ''
                ],
            ],
            'images' => ImagesResource::collection(IndexMasterProductImages::run($masterAsset))->resolve(),

        ];
    }
}
