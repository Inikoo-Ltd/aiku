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
use App\Actions\Traits\HasBucketImages;
use App\Http\Resources\Helpers\TradeUnitImagesResource;
use App\Models\Goods\TradeUnit;
use Lorisleiva\Actions\Concerns\AsObject;

class GetTradeUnitImages
{
    use AsObject;
    use HasBucketImages;

    public function handle(TradeUnit $tradeUnit): array
    {
        return [
            'id'                  => $tradeUnit->id,
            'bucket_images'       => $tradeUnit->bucket_images,
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
                    'media'     => ''
                ],
            ],
            'images'              => TradeUnitImagesResource::collection(IndexTradeUnitImages::run($tradeUnit))->resolve(),

        ];
    }


}
