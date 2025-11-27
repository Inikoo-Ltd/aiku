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
use App\Http\Resources\Masters\MasterProductResource;
use App\Models\Masters\MasterAsset;
use Lorisleiva\Actions\Concerns\AsObject;
use App\Actions\Traits\HasBucketAttachment;
use App\Helpers\NaturalLanguage;
use App\Actions\Goods\TradeUnit\UI\GetTradeUnitShowcase;
use App\Models\Goods\TradeUnit;

class GetMasterProductShowcase
{
    use AsObject;
    use HasBucketImages;
    use HasBucketAttachment;

    public function handle(MasterAsset $masterAsset): array
    {

        $tradeUnits = $masterAsset->tradeUnits;
        $tradeUnits->loadMissing(['ingredients']);
        $properties = [
            'country_of_origin' => NaturalLanguage::make()->country($masterAsset->country_of_origin),
            'ingredients'       => $masterAsset->marketing_ingredients,
            'tariff_code'       => $masterAsset->tariff_code,
            'duty_rate'         => $masterAsset->duty_rate,
            'hts_us'            => $masterAsset->hts_us,
        ];

        $gpsr = [
            'manufacturer'               => $masterAsset->gpsr_manufacturer,
            'eu_responsible'             => $masterAsset->gpsr_eu_responsible,
            'warnings'                   => $masterAsset->gpsr_warnings,
            'how_to_use'                 => $masterAsset->gpsr_manual,
            'gpsr_class_category_danger' => $masterAsset->gpsr_class_category_danger,
            'product_languages'          => $masterAsset->gpsr_class_languages,
            'acute_toxicity'             => $masterAsset->pictogram_toxic,
            'corrosive'                  => $masterAsset->pictogram_corrosive,
            'explosive'                  => $masterAsset->pictogram_explosive,
            'flammable'                  => $masterAsset->pictogram_flammable,
            'gas_under_pressure'         => $masterAsset->pictogram_gas,
            'hazard_environment'         => $masterAsset->pictogram_environment,
            'health_hazard'              => $masterAsset->pictogram_health,
            'oxidising'                  => $masterAsset->pictogram_oxidising,
        ];


        $dataTradeUnits = [];
        if ($masterAsset->tradeUnits) {
            $dataTradeUnits = $this->getDataTradeUnit($masterAsset->tradeUnits);
        }


        return [
            'images'                => $this->getImagesData($masterAsset),
            'main_image'            => $masterAsset->imageSources(),
            'masterProduct'         => MasterProductResource::make($masterAsset)->toArray(request()),
            'properties'            => $properties,
            'trade_units'           => $dataTradeUnits,
            'gpsr'                  => $gpsr,
            'attachment_box'        => [
                'public'      => [],
                'private'     => []
            ]
        ];
    }

    private function getDataTradeUnit($tradeUnits): array
    {
        return $tradeUnits->map(function (TradeUnit $tradeUnit) {
            return GetTradeUnitShowcase::run($tradeUnit);
        })->toArray();
    }


}
