<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 27-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Masters\MasterAsset\UI;

use App\Actions\Traits\HasBucketImages;
use App\Helpers\NaturalLanguage;
use App\Http\Resources\Catalogue\TagsResource;
use App\Http\Resources\Goods\IngredientsResource;
use App\Http\Resources\Goods\TradeUnitResource;
use App\Http\Resources\Masters\MasterProductResource;
use App\Models\Goods\TradeUnit;
use App\Models\Masters\MasterAsset;
use Lorisleiva\Actions\Concerns\AsObject;

class GetMasterProductShowcase
{
    use AsObject;
    use HasBucketImages;

    public function handle(MasterAsset $masterAsset): array
    {
        $tradeUnits = $masterAsset->tradeUnits;


        $tradeUnits->loadMissing(['ingredients']);

        $ingredients = $tradeUnits->flatMap(function ($tradeUnit) {
            return $tradeUnit->ingredients->pluck('name');
        })->unique()->values()->all();

        $properties = [
            'country_of_origin' => NaturalLanguage::make()->country($masterAsset->tradeUnits()->first()->country_of_origin),
            'ingredients'       => $ingredients,
        ];

        return [
            'properties' => $properties,
            'masterProduct' => MasterProductResource::make($masterAsset)->toArray(request()),
            'images' => $this->getImagesData($masterAsset),
        ];
    }


}
