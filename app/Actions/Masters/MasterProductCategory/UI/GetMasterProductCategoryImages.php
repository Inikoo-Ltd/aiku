<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 27-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Actions\Traits\HasBucketImages;
use App\Http\Resources\Helpers\ImagesResource;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use Lorisleiva\Actions\Concerns\AsObject;

class GetMasterProductCategoryImages
{
    use AsObject;
    use HasBucketImages;

    public function handle(MasterProductCategory $masterProductCategory): array
    {
        return [
            'id' => $masterProductCategory->id,
            'images_category_box' => $this->getSingleImageData($masterProductCategory),
            'images_update_route' => [
                'method'     => 'patch',
                'name'       => 'grp.models.master_product_category.update_images',
                'parameters' => [
                    'masterProductCategory' => $masterProductCategory->id,
                ],
            ],
            'upload_images_route' => [
                'method'     => 'post',
                'name'       => 'grp.models.master_product_category.upload_images',
                'parameters' => [
                    'masterProductCategory' => $masterProductCategory->id,
                ],
            ],
            'delete_images_route' => [
                'method'     => 'post',
                'name'       => 'grp.models.master_product_category.delete_images',
                'parameters' => [
                    'masterProductCategory' => $masterProductCategory->id,
                    'media'   => ''
                ],
            ],
            'images' => ImagesResource::collection(IndexMasterProductCategoryImages::run($masterProductCategory))->resolve(),

        ];
    }
}
