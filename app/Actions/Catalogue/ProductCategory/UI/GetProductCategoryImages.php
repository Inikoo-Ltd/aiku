<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 27-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Catalogue\ProductCategory\UI;

use App\Actions\Traits\HasBucketDescriptionImages;
use App\Actions\Traits\HasBucketImages;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Http\Resources\Helpers\ImagesResource;
use App\Models\Catalogue\ProductCategory;
use Lorisleiva\Actions\Concerns\AsObject;

class GetProductCategoryImages
{
    use AsObject;
    use HasBucketImages;
    use HasBucketDescriptionImages;

    public function handle(ProductCategory $productCategory): array
    {
        return [
            'editable'            => !$productCategory->master_product_category_id,
            'id'                  => $productCategory->id,
            // 'images_category_box' => $this->getSingleImageData($productCategory), // Old One
            'images_category_box' => $productCategory->type == ProductCategoryTypeEnum::DEPARTMENT ? array_merge(
                    $this->getSingleImageData($productCategory), 
                    $this->getShowcaseImageData($productCategory), 
                    $this->getDescriptionImages($productCategory)
            ) : array_merge(
                    $this->getSingleImageData($productCategory), 
                    $this->getDescriptionImages($productCategory)
            ),
            'images_update_route' => [
                'method'     => 'patch',
                'name'       => 'grp.models.product_category.update_images',
                'parameters' => [
                    'productCategory' => $productCategory->id,
                ],
            ],
            'upload_images_route' => [
                'method'     => 'post',
                'name'       => 'grp.models.product_category.upload_images',
                'parameters' => [
                    'productCategory' => $productCategory->id,
                ],
            ],
            'delete_images_route' => [
                'method'     => 'post',
                'name'       => 'grp.models.product_category.delete_images',
                'parameters' => [
                    'productCategory' => $productCategory->id,
                    'media'           => ''
                ],
            ],
            'images'              => ImagesResource::collection(IndexProductCategoryImages::run($productCategory))->resolve(),

        ];
    }
}
