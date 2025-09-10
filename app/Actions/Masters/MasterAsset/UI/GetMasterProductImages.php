<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 27-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Masters\MasterAsset\UI;

use App\Http\Resources\Helpers\ImagesResource;
use App\Models\Masters\MasterAsset;
use Lorisleiva\Actions\Concerns\AsObject;

class GetMasterProductImages
{
    use AsObject;

    public function handle(MasterAsset $masterAsset): array
    {
        return [
            'id' => $masterAsset->id,
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

    public function getImagesData(MasterAsset $masterAsset): array
    {

        return [
            [
                'label' => __('Main'),
                'type'  => 'image',
                'column_in_db' => 'image_id',
                'id' => $masterAsset->image_id,
                'images' => $masterAsset->imageSources(),
            ],
            [
                'label' => __('Video'),
                'type'  => 'video',
                'information' => __('You can use YouTube or Vimeo links'),
                'column_in_db' => 'video_url',
                'url' => $masterAsset->video_url,
            ],
            [
                'label' => __('Front side'),
                'type'  => 'image',
                'column_in_db' => 'front_image_id',
                'id' => $masterAsset->front_image_id,
                'images' => $masterAsset->imageSources(getImage:'frontImage'),
            ],
            [
                'label' => __('Left side'),
                'type'  => 'image',
                'column_in_db' => 'left_image_id',
                'id' => $masterAsset->left_image_id,
                'images' => $masterAsset->imageSources(getImage:'leftImage'),
            ],
            [
                'label' => __('3/4 angle side'),
                'type'  => 'image',
                'column_in_db' => '34_image_id',
                'id' => $masterAsset->{'34_image_id'},
                'images' => $masterAsset->imageSources(getImage:'threeQuarterImage'),
            ],
            [
                'label' => __('Right side'),
                'type'  => 'image',
                'column_in_db' => 'right_image_id',
                'id' => $masterAsset->right_image_id,
                'images' => $masterAsset->imageSources(getImage:'rightImage'),
            ],
            [
                'label' => __('Back side'),
                'type'  => 'image',
                'column_in_db' => 'back_image_id',
                'id' => $masterAsset->back_image_id,
                'images' => $masterAsset->imageSources(getImage:'backImage'),
            ],
            [
                'label' => __('Top side'),
                'type'  => 'image',
                'column_in_db' => 'top_image_id',
                'id' => $masterAsset->top_image_id,
                'images' => $masterAsset->imageSources(getImage:'topImage'),
            ],
            [
                'label' => __('Bottom side'),
                'type'  => 'image',
                'column_in_db' => 'bottom_image_id',
                'id' => $masterAsset->bottom_image_id,
                'images' => $masterAsset->imageSources(getImage:'bottomImage'),
            ],
            [
                'label' => __('Comparison image'),
                'type'  => 'image',
                'column_in_db' => 'size_comparison_image_id',
                'id' => $masterAsset->size_comparison_image_id,
                'images' => $masterAsset->imageSources(getImage:'sizeComparisonImage'),
            ],
            [
                'label' => __('Lifestyle image'),
                'type'  => 'image',
                'column_in_db' => 'size_comparison_image_id',
                'id' => $masterAsset->size_comparison_image_id,
                'images' => $masterAsset->imageSources(getImage:'sizeComparisonImage'),
            ],
        ];


    }

}
