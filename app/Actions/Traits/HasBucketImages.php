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
                'label'        => __('Main'),
                'type'         => 'image',
                'column_in_db' => 'image_id',
                'id'           => $model->image_id,
                'images'       => $model->imageSources(),
                'dimensions'   => [
                    'width'  => $model->image->width ?? 0,
                    'height' => $model->image->height ?? 0
                ]
            ],
            [
                'label'        => __('Video'),
                'type'         => 'video',
                'information'  => __('You can use YouTube or Vimeo links'),
                'column_in_db' => 'video_url',
                'url'          => $model->video_url,
            ],
            [
                'label'        => __('Front side'),
                'type'         => 'image',
                'column_in_db' => 'front_image_id',
                'id'           => $model->front_image_id,
                'images'       => $model->imageSources(getImage: 'frontImage'),
                'dimensions'   => [
                    'width'  => $model->frontImage->width ?? 0,
                    'height' => $model->frontImage->height ?? 0
                ]
            ],
            [
                'label'        => __('Left side'),
                'type'         => 'image',
                'column_in_db' => 'left_image_id',
                'id'           => $model->left_image_id,
                'images'       => $model->imageSources(getImage: 'leftImage'),
                'dimensions'   => [
                    'width'  => $model->leftImage->width ?? 0,
                    'height' => $model->leftImage->height ?? 0
                ]
            ],
            [
                'label'        => __('3/4 angle side'),
                'type'         => 'image',
                'column_in_db' => '34_image_id',
                'id'           => $model->{'34_image_id'},
                'images'       => $model->imageSources(getImage: 'threeQuarterImage'),
                'dimensions'   => [
                    'width'  => $model->threeQuarterImage->width ?? 0,
                    'height' => $model->threeQuarterImage->height ?? 0
                ]
            ],
            [
                'label'        => __('Right side'),
                'type'         => 'image',
                'column_in_db' => 'right_image_id',
                'id'           => $model->right_image_id,
                'images'       => $model->imageSources(getImage: 'rightImage'),
                'dimensions'   => [
                    'width'  => $model->rightImage->width ?? 0,
                    'height' => $model->rightImage->height ?? 0
                ]
            ],
            [
                'label'        => __('Back side'),
                'type'         => 'image',
                'column_in_db' => 'back_image_id',
                'id'           => $model->back_image_id,
                'images'       => $model->imageSources(getImage: 'backImage'),
                'dimensions'   => [
                    'width'  => $model->backImage->width ?? 0,
                    'height' => $model->backImage->height ?? 0
                ]
            ],
            [
                'label'        => __('Top side'),
                'type'         => 'image',
                'column_in_db' => 'top_image_id',
                'id'           => $model->top_image_id,
                'images'       => $model->imageSources(getImage: 'topImage'),
                'dimensions'   => [
                    'width'  => $model->topImage->width ?? 0,
                    'height' => $model->topImage->height ?? 0
                ]
            ],
            [
                'label'        => __('Bottom side'),
                'type'         => 'image',
                'column_in_db' => 'bottom_image_id',
                'id'           => $model->bottom_image_id,
                'images'       => $model->imageSources(getImage: 'bottomImage'),
                'dimensions'   => [
                    'width'  => $model->bottomImage->width ?? 0,
                    'height' => $model->bottomImage->height ?? 0
                ]
            ],
            [
                'label'        => __('Comparison image'),
                'type'         => 'image',
                'column_in_db' => 'size_comparison_image_id',
                'id'           => $model->size_comparison_image_id,
                'images'       => $model->imageSources(getImage: 'sizeComparisonImage'),
                'dimensions'   => [
                    'width'  => $model->sizeComparisonImage->width ?? 0,
                    'height' => $model->sizeComparisonImage->height ?? 0
                ]
            ],
            [
                'label'        => __('Lifestyle image'),
                'type'         => 'image',
                'column_in_db' => 'lifestyle_image_id',
                'id'           => $model->lifestyle_image_id,
                'images'       => $model->imageSources(getImage: 'lifestyleImage'),
                'dimensions'   => [
                    'width'  => $model->lifestyleImage->width ?? 0,
                    'height' => $model->lifestyleImage->height ?? 0
                ]
            ],
            [
                'label'        => __('Art 1'),
                'type'         => 'image',
                'column_in_db' => 'art1_image_id',
                'id'           => $model->art1_image_id,
                'images'       => $model->imageSources(getImage: 'art1Image'),
                'dimensions'   => [
                    'width'  => $model->art1Image->width ?? 0,
                    'height' => $model->art1Image->height ?? 0
                ]
            ],
            [
                'label'        => __('Art 2'),
                'type'         => 'image',
                'column_in_db' => 'art2_image_id',
                'id'           => $model->art2_image_id,
                'images'       => $model->imageSources(getImage: 'art2Image'),
                'dimensions'   => [
                    'width'  => $model->art2Image->width ?? 0,
                    'height' => $model->art2Image->height ?? 0
                ]
            ],
            [
                'label'        => __('Art 3'),
                'type'         => 'image',
                'column_in_db' => 'art3_image_id',
                'id'           => $model->art3_image_id,
                'images'       => $model->imageSources(getImage: 'art3Image'),
                'dimensions'   => [
                    'width'  => $model->art3Image->width ?? 0,
                    'height' => $model->art3Image->height ?? 0
                ]
            ],
            [
                'label'        => __('Art 4'),
                'type'         => 'image',
                'column_in_db' => 'art4_image_id',
                'id'           => $model->art4_image_id,
                'images'       => $model->imageSources(getImage: 'art4Image'),
                'dimensions'   => [
                    'width'  => $model->art4Image->width ?? 0,
                    'height' => $model->art4Image->height ?? 0
                ]
            ],
            [
                'label'        => __('Art 5'),
                'type'         => 'image',
                'column_in_db' => 'art5_image_id',
                'id'           => $model->art5_image_id,
                'images'       => $model->imageSources(getImage: 'art5Image'),
                'dimensions'   => [
                    'width'  => $model->art5Image->width ?? 0,
                    'height' => $model->art5Image->height ?? 0
                ]
            ],
        ];
    }

    public function getSingleImageData(MasterProductCategory|ProductCategory $model): array
    {
        return [
            [
                'label'        => __('Main'),
                'type'         => 'image',
                'column_in_db' => 'image_id',
                'id'           => $model->image_id,
                'images'       => $model->imageSources(),
                'dimensions'   => [
                    'width'  => $model->image->width ?? 0,
                    'height' => $model->image->height ?? 0
                ]
            ],
        ];
    }
}
