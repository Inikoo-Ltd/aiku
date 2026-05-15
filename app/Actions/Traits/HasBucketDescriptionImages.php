<?php

/*
 * author Louis Perez
 * created on 13-03-2026-11h-18m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Traits;

use App\Models\Catalogue\ProductCategory;
use App\Models\Masters\MasterProductCategory;

trait HasBucketDescriptionImages
{
    public function getDescriptionImages(MasterProductCategory|ProductCategory $model): array
    {
        return [
            [
                'label'        => __('Description Image 1'),
                'type'         => 'image',
                'column_in_db' => 'desc_art1',
                'id'           => $model->desc_art1,
                'images'       => $model->imageSources(getImage: 'descArt1Image'),
                'dimensions'   => [
                    'width'  => $model->descArt1Image->width ?? 0,
                    'height' => $model->descArt1Image->height ?? 0
                ]
            ],
            [
                'label'        => __('Description Image 2'),
                'type'         => 'image',
                'column_in_db' => 'desc_art2',
                'id'           => $model->desc_art2,
                'images'       => $model->imageSources(getImage: 'descArt2Image'),
                'dimensions'   => [
                    'width'  => $model->descArt2Image->width ?? 0,
                    'height' => $model->descArt2Image->height ?? 0
                ]
            ],
            [
                'label'        => __('Description Image 3'),
                'type'         => 'image',
                'column_in_db' => 'desc_art3',
                'id'           => $model->desc_art3,
                'images'       => $model->imageSources(getImage: 'descArt3Image'),
                'dimensions'   => [
                    'width'  => $model->descArt3Image->width ?? 0,
                    'height' => $model->descArt3Image->height ?? 0
                ]
            ],
            [
                'label'        => __('Description Image 4'),
                'type'         => 'image',
                'column_in_db' => 'desc_art4',
                'id'           => $model->desc_art4,
                'images'       => $model->imageSources(getImage: 'descArt4Image'),
                'dimensions'   => [
                    'width'  => $model->descArt4Image->width ?? 0,
                    'height' => $model->descArt4Image->height ?? 0
                ]
            ],
            [
                'label'        => __('Description Image 5'),
                'type'         => 'image',
                'column_in_db' => 'desc_art5',
                'id'           => $model->desc_art5,
                'images'       => $model->imageSources(getImage: 'descArt5Image'),
                'dimensions'   => [
                    'width'  => $model->descArt5Image->width ?? 0,
                    'height' => $model->descArt5Image->height ?? 0
                ]
            ],
            [
                'label'        => __('Description Video'),
                'type'         => 'video',
                'information'  => __('You can use YouTube or Vimeo links'),
                'column_in_db' => 'desc_video_url',
                'url'          => $model->desc_video_url,
            ],
            [
                'label'        => __('Extra Description Image'),
                'type'         => 'image',
                'column_in_db' => 'extra_desc_art1',
                'id'           => $model->extra_desc_art1,
                'images'       => $model->imageSources(getImage: 'extraDescArt1Image'),
                'dimensions'   => [
                    'width'  => $model->extraDescArt1Image->width ?? 0,
                    'height' => $model->extraDescArt1Image->height ?? 0
                ]
            ],
            [
                'label'        => __('Extra Description Image 2'),
                'type'         => 'image',
                'column_in_db' => 'extra_desc_art2',
                'id'           => $model->extra_desc_art2,
                'images'       => $model->imageSources(getImage: 'extraDescArt2Image'),
                'dimensions'   => [
                    'width'  => $model->extraDescArt2Image->width ?? 0,
                    'height' => $model->extraDescArt2Image->height ?? 0
                ]
            ],
            [
                'label'        => __('Extra Description Image 3'),
                'type'         => 'image',
                'column_in_db' => 'extra_desc_art3',
                'id'           => $model->extra_desc_art3,
                'images'       => $model->imageSources(getImage: 'extraDescArt3Image'),
                'dimensions'   => [
                    'width'  => $model->extraDescArt3Image->width ?? 0,
                    'height' => $model->extraDescArt3Image->height ?? 0
                ]
            ],
            [
                'label'        => __('Extra Description Image 4'),
                'type'         => 'image',
                'column_in_db' => 'extra_desc_art4',
                'id'           => $model->extra_desc_art4,
                'images'       => $model->imageSources(getImage: 'extraDescArt4Image'),
                'dimensions'   => [
                    'width'  => $model->extraDescArt4Image->width ?? 0,
                    'height' => $model->extraDescArt4Image->height ?? 0
                ]
            ],

        ];
    }
}
