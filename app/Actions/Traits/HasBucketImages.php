<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 10 Sept 2025 09:46:39 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Goods\TradeUnit;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;

trait HasBucketImages
{
    public function getImagesData(MasterAsset|Product|TradeUnit $model): array
    {

        return [
            [
                'label' => __('Main'),
                'type'  => 'image',
                'column_in_db' => 'image_id',
                'id' => $model->image_id,
                'images' => $model->imageSources(),
                'dimensions' => [
                    'width' => $model->image->width ?? 0,
                    'height' => $model->image->height ?? 0
                ]
            ],
            [
                'label' => __('Video'),
                'type'  => 'video',
                'information' => __('You can use YouTube or Vimeo links'),
                'column_in_db' => 'video_url',
                'url' => $model->video_url,
            ],
            [
                'label' => __('Front side'),
                'type'  => 'image',
                'column_in_db' => 'front_image_id',
                'id' => $model->front_image_id,
                'images' => $model->imageSources(getImage:'frontImage'),
                'dimensions' => [
                    'width' => $model->frontImage->width ?? 0,
                    'height' => $model->frontImage->height ?? 0
                ]
            ],
            [
                'label' => __('Left side'),
                'type'  => 'image',
                'column_in_db' => 'left_image_id',
                'id' => $model->left_image_id,
                'images' => $model->imageSources(getImage:'leftImage'),
                'dimensions' => [
                    'width' => $model->leftImage->width ?? 0,
                    'height' => $model->leftImage->height ?? 0
                ]
            ],
            [
                'label' => __('3/4 angle side'),
                'type'  => 'image',
                'column_in_db' => '34_image_id',
                'id' => $model->{'34_image_id'},
                'images' => $model->imageSources(getImage:'threeQuarterImage'),
                'dimensions' => [
                    'width' => $model->threeQuarterImage->width ?? 0,
                    'height' => $model->threeQuarterImage->height ?? 0
                ]
            ],
            [
                'label' => __('Right side'),
                'type'  => 'image',
                'column_in_db' => 'right_image_id',
                'id' => $model->right_image_id,
                'images' => $model->imageSources(getImage:'rightImage'),
                'dimensions' => [
                    'width' => $model->rightImage->width ?? 0,
                    'height' => $model->rightImage->height ?? 0
                ]
            ],
            [
                'label' => __('Back side'),
                'type'  => 'image',
                'column_in_db' => 'back_image_id',
                'id' => $model->back_image_id,
                'images' => $model->imageSources(getImage:'backImage'),
                'dimensions' => [
                    'width' => $model->backImage->width ?? 0,
                    'height' => $model->backImage->height ?? 0
                ]
            ],
            [
                'label' => __('Top side'),
                'type'  => 'image',
                'column_in_db' => 'top_image_id',
                'id' => $model->top_image_id,
                'images' => $model->imageSources(getImage:'topImage'),
                'dimensions' => [
                    'width' => $model->topImage->width ?? 0,
                    'height' => $model->topImage->height ?? 0
                ]
            ],
            [
                'label' => __('Bottom side'),
                'type'  => 'image',
                'column_in_db' => 'bottom_image_id',
                'id' => $model->bottom_image_id,
                'images' => $model->imageSources(getImage:'bottomImage'),
                'dimensions' => [
                    'width' => $model->bottomImage->width ?? 0,
                    'height' => $model->bottomImage->height ?? 0
                ]
            ],
            [
                'label' => __('Comparison image'),
                'type'  => 'image',
                'column_in_db' => 'size_comparison_image_id',
                'id' => $model->size_comparison_image_id,
                'images' => $model->imageSources(getImage:'sizeComparisonImage'),
                'dimensions' => [
                    'width' => $model->sizeComparisonImage->width ?? 0,
                    'height' => $model->sizeComparisonImage->height ?? 0
                ]
            ],
            [
                'label' => __('Lifestyle image'),
                'type'  => 'image',
                'column_in_db' => 'lifestyle_image_id',
                'id' => $model->lifestyle_image_id,
                'images' => $model->imageSources(getImage:'lifestyleImage'),
                'dimensions' => [
                    'width' => $model->lifestyleImage->width ?? 0,
                    'height' => $model->lifestyleImage->height ?? 0
                ]
            ],
        ];


    }

    public function getSingleImageData(MasterProductCategory|ProductCategory $model): array
    {
        return [
            [
                'label' => __('Main'),
                'type'  => 'image',
                'column_in_db' => 'image_id',
                'id' => $model->image_id,
                'images' => $model->imageSources(),
                'dimensions' => [
                    'width' => $model->image->width ?? 0,
                    'height' => $model->image->height ?? 0
                ]
            ],
        ];


    }
}
