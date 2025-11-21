<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 27-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Masters\MasterCollection\UI;

use App\Actions\Traits\HasBucketImages;
use App\Http\Resources\Helpers\ImagesResource;
use App\Models\Masters\MasterCollection;
use Lorisleiva\Actions\Concerns\AsObject;

class GetMasterCollectionsImages
{
    use AsObject;
    use HasBucketImages;

    public function handle(MasterCollection $masterCollection): array
    {
        return [
            'id' => $masterCollection->id,
            'images_category_box' => $this->getSingleImageData($masterCollection),
            'images_update_route' => [
                'method'     => 'patch',
                'name'       => 'grp.models.master_collection.update_images',
                'parameters' => [
                    'masterCollection' => $masterCollection->id,
                ],
            ],
            'upload_images_route' => [
                'method'     => 'post',
                'name'       => 'grp.models.master_collection.upload_images',
                'parameters' => [
                    'masterCollection' => $masterCollection->id,
                ],
            ],
            'delete_images_route' => [
                'method'     => 'post',
                'name'       => 'grp.models.master_collection.delete_images',
                'parameters' => [
                    'masterCollection' => $masterCollection->id,
                    'media'   => ''
                ],
            ],
           /*  'images' => ImagesResource::collection(IndexMasterProductCategoryImages::run($masterCollection))->resolve(), */

        ];
    }
}
