<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 27-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Goods\TradeUnit\UI;

use App\Actions\Goods\TradeUnit\IndexTradeUnitImages;
use App\Http\Resources\Helpers\TradeUnitImagesResource;
use App\Models\Goods\TradeUnit;
use Lorisleiva\Actions\Concerns\AsObject;

class GetTradeUnitImages
{
    use AsObject;

    public function handle(TradeUnit $tradeUnit): array
    {
        return [
            'id' => $tradeUnit->id,
            'images_category_box' => $this->getImagesData($tradeUnit),
            'images_update_route' => [
                'method'     => 'patch',
                'name'       => 'grp.models.trade-unit.update_images',
                'parameters' => [
                    'tradeUnit' => $tradeUnit->id,
                ],
            ],
            'upload_images_route' => [
                'method'     => 'post',
                'name'       => 'grp.models.trade-unit.upload_images',
                'parameters' => [
                    'tradeUnit' => $tradeUnit->id,
                ],
            ],
            'delete_images_route' => [
                'method'     => 'post',
                'name'       => 'grp.models.trade-unit.detach_image',
                'parameters' => [
                    'tradeUnit' => $tradeUnit->id,
                    'media'   => ''
                ],
            ],
            'images' => TradeUnitImagesResource::collection(IndexTradeUnitImages::run($tradeUnit))->resolve(),
            
        ];
    }

    public function getImagesData(TradeUnit $tradeUnit): array
    {

        return [
            [
                'label' => __('Main'),
                'type'  => 'image',
                'column_in_db' => 'image_id',
                'images' => $tradeUnit->imageSources(),
            ],
            [
                'label' => __('Video'),
                'type'  => 'video',
                'information' => __('You can use YouTube or Vimeo links'),
                'column_in_db' => 'video_url',
                'url' => $tradeUnit->video_url,
            ],
            [
                'label' => __('Front side'),
                'type'  => 'image',
                'column_in_db' => 'front_image_id',
                'images' => $tradeUnit->imageSources(getImage:'frontImage'),
            ],
            [
                'label' => __('Left side'),
                'type'  => 'image',
                'column_in_db' => 'left_image_id',
                'images' => $tradeUnit->imageSources(getImage:'leftImage'),
            ],
            [
                'label' => __('3/4 angle side'),
                'type'  => 'image',
                'column_in_db' => '34_image_id',
                'images' => $tradeUnit->imageSources(getImage:'threeQuarterImage'),
            ],
            [
                'label' => __('Right side'),
                'type'  => 'image',
                'column_in_db' => 'right_image_id',
                'images' => $tradeUnit->imageSources(getImage:'rightImage'),
            ],
            [
                'label' => __('Back side'),
                'type'  => 'image',
                'column_in_db' => 'back_image_id',
                'images' => $tradeUnit->imageSources(getImage:'backImage'),
            ],
            [
                'label' => __('Top side'),
                'type'  => 'image',
                'column_in_db' => 'top_image_id',
                'images' => $tradeUnit->imageSources(getImage:'topImage'),
            ],
            [
                'label' => __('Bottom side'),
                'type'  => 'image',
                'column_in_db' => 'bottom_image_id',
                'images' => $tradeUnit->imageSources(getImage:'bottomImage'),
            ],
            [
                'label' => __('Comparison image'),
                'type'  => 'image',
                'column_in_db' => 'size_comparison_image_id',
                'images' => $tradeUnit->imageSources(getImage:'sizeComparisonImage'),
            ],
        ];


    }

}
