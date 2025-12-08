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
use Illuminate\Support\Facades\DB;  

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


        $product = $masterAsset->products()->select('products.id', 'products.slug', 'products.code', 'products.shop_id', 'products.organisation_id', 'products.is_for_sale')
            ->with(['shop:id,code,slug'])
            ->with(['organisation:id,code,slug'])
            ->get();

        $parentLink = null;
        if ($masterAsset->not_for_sale_from_trade_unit) {
            $parentLink = [
                'url'    => "grp.trade_units.units.edit",
                'params' => [
                    'tradeUnit' => $masterAsset->tradeUnits->where('is_for_sale', false)->first()->slug,
                ]
            ];
        }

        return [
            'images'              => $this->getImagesData($masterAsset),
            'main_image'          => $masterAsset->imageSources(),
            'masterProduct'       => MasterProductResource::make($masterAsset)->toArray(request()),
            'properties'          => $properties,
            'trade_units'         => $dataTradeUnits,
            'gpsr'                => $gpsr,
            'attachment_box'      => [
                'public'  => [],
                'private' => []
            ],
            'availability_status' => [
                'is_for_sale'            => $masterAsset->is_for_sale,
                'product'                => $product->toArray(),
                'total_product_for_sale' => $product->where('is_for_sale', true)->count(),
                'from_trade_unit'        => $masterAsset->not_for_sale_from_trade_unit,
                'parentLink'             => $parentLink,
            ],
        ];
    }

    private function getDataTradeUnit($tradeUnits): array
    {   
        $packedIn = DB::table('model_has_trade_units')
            ->where('model_type', 'Stock')
            ->whereIn('trade_unit_id', $tradeUnits->pluck('id'))
            ->pluck('quantity', 'trade_unit_id')
            ->toArray();

        return $tradeUnits->map(function (TradeUnit $tradeUnit) use ($packedIn) {
            return array_merge(
                ['pick_fractional' => riseDivisor(divideWithRemainder(findSmallestFactors($tradeUnit->pivot->quantity / $packedIn[$tradeUnit->id])), $packedIn[$tradeUnit->id])],
                GetTradeUnitShowcase::run($tradeUnit)
            );
        })->toArray();
    }


}
