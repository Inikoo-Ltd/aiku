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
use App\Models\Masters\MasterCollection;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Support\Collection;

trait HasBucketImages
{
    public function getImagesData(MasterAsset|Product|TradeUnit|MasterCollection $model, bool $withCaptions = false, int $maxWidth = 0): array
    {
        $captions = $withCaptions ? $this->getBucketImageCaptions($model) : new Collection();

        $imageSlots = [
            [__('Main'), 'image_id', 'image'],
            [__('Front side'), 'front_image_id', 'frontImage'],
            [__('Left side'), 'left_image_id', 'leftImage'],
            [__('3/4 angle side'), '34_image_id', 'threeQuarterImage'],
            [__('Right side'), 'right_image_id', 'rightImage'],
            [__('Back side'), 'back_image_id', 'backImage'],
            [__('Top side'), 'top_image_id', 'topImage'],
            [__('Bottom side'), 'bottom_image_id', 'bottomImage'],
            [__('Comparison image'), 'size_comparison_image_id', 'sizeComparisonImage'],
            [__('Lifestyle image'), 'lifestyle_image_id', 'lifestyleImage'],
            [__('Art 1'), 'art1_image_id', 'art1Image'],
            [__('Art 2'), 'art2_image_id', 'art2Image'],
            [__('Art 3'), 'art3_image_id', 'art3Image'],
            [__('Art 4'), 'art4_image_id', 'art4Image'],
            [__('Art 5'), 'art5_image_id', 'art5Image'],
        ];

        $imagesData = [];
        foreach ($imageSlots as [$label, $column, $relation]) {
            $imagesData[] = [
                'label'        => $label,
                'type'         => 'image',
                'column_in_db' => $column,
                'id'           => $model->{$column},
                'images'       => $model->imageSources($maxWidth, $maxWidth, $relation),
                'thumbnail'    => $model->imageSources(0, 192, $relation),
                'zoom'         => $model->imageSources(1600, 1600, $relation),
                'dimensions'   => [
                    'width'  => $model->{$relation}->width ?? 0,
                    'height' => $model->{$relation}->height ?? 0
                ]
            ];
        }

        array_splice($imagesData, 1, 0, [[
            'label'        => __('Video'),
            'type'         => 'video',
            'information'  => __('You can use YouTube or Vimeo links'),
            'column_in_db' => 'video_url',
            'url'          => $model->video_url,
        ]]);

        $imagesData[] = [
            'label'        => __('Sound sample'),
            'type'         => 'audio',
            'information'  => __('Audio sample, e.g. for musical instruments or singing bowls'),
            'column_in_db' => 'audio_id',
            'id'           => $model->audio_id,
            'audio'        => $model->audio ? [
                'url'  => route('grp.media.show', $model->audio->ulid),
                'name' => $model->audio->name,
            ] : null,
        ];

        if (!$withCaptions) {
            return $imagesData;
        }

        return array_map(function (array $imageBox) use ($captions): array {
            if (($imageBox['type'] ?? null) === 'image') {
                $imageBox['alt'] = $captions->get($imageBox['id']);
            }

            return $imageBox;
        }, $imagesData);
    }

    protected function getBucketImageCaptions(MasterAsset|Product|TradeUnit|MasterCollection $model): Collection
    {
        $captions = new Collection();

        if ($model instanceof Product || $model instanceof MasterAsset) {
            foreach ($model->tradeUnits as $tradeUnit) {
                foreach ($tradeUnit->images as $media) {
                    if (filled($media->pivot->caption)) {
                        $captions->put($media->id, $media->pivot->caption);
                    }
                }
            }
        }

        foreach ($model->images as $media) {
            if (filled($media->pivot->caption)) {
                $captions->put($media->id, $media->pivot->caption);
            }
        }

        return $captions;
    }

    public function getSingleImageData(MasterProductCategory|ProductCategory|MasterCollection $model): array
    {
        return [
            [
                'label'        => __('Main'),
                'type'         => 'image',
                'column_in_db' => 'image_id',
                'id'           => $model->image_id,
                'images'       => $model->imageSources(800, 800),
                'dimensions'   => [
                    'width'  => $model->image->width ?? 0,
                    'height' => $model->image->height ?? 0
                ]
            ],
        ];
    }

    public function getShowcaseImageData(MasterProductCategory|ProductCategory $model): array
    {
        return [
            [
                'label'        => __('Showcase'),
                'type'         => 'image',
                'column_in_db' => 'showcase_image_id',
                'id'           => $model->showcase_image_id,
                'images'       => $model->imageSources(0, 0, 'showcaseImage'),
                'dimensions'   => [
                    'width'  => $model->showcaseImage->width ?? 0,
                    'height' => $model->showcaseImage->height ?? 0
                ]
            ],
        ];
    }
}
