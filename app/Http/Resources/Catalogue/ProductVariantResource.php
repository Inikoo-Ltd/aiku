<?php

/*
 * author Louis Perez
 * created on 31-12-2025-11h-05m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Http\Resources\Catalogue;

use App\Actions\Catalogue\Product\UI\GetProductTimeSeriesData;
use App\Actions\Traits\HasBucketImages;
use App\Models\Catalogue\Product;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Helpers\NaturalLanguage;
use App\Actions\Traits\HasBucketAttachment;
use App\Actions\Goods\TradeUnit\UI\GetTradeUnitShowcase;
use Illuminate\Support\Facades\DB;
use App\Models\Goods\TradeUnit;

class ProductVariantResource extends JsonResource
{
    use HasBucketImages;
    use HasBucketAttachment;

    public function toArray($request): array
    {
        /** @var Product $product */
        $product = $this;

        $dataTradeUnits = [];
        if ($product->tradeUnits) {
            $dataTradeUnits = $this->getDataTradeUnit($product->tradeUnits);
        }

        $gpsr = [
            'eu_responsible'             => $product->gpsr_eu_responsible,
            'warnings'                   => $product->gpsr_warnings,
            'how_to_use'                 => $product->gpsr_manual,
            'gpsr_class_category_danger' => $product->gpsr_class_category_danger,
            'product_languages'          => $product->gpsr_class_languages,
            'acute_toxicity'             => $product->pictogram_toxic,
            'corrosive'                  => $product->pictogram_corrosive,
            'explosive'                  => $product->pictogram_explosive,
            'flammable'                  => $product->pictogram_flammable,
            'gas_under_pressure'         => $product->pictogram_gas,
            'hazard_environment'         => $product->pictogram_environment,
            'health_hazard'              => $product->pictogram_health,
            'oxidising'                  => $product->pictogram_oxidising,
            'manufacturer'               => $product->gpsr_manufacturer,
        ];

        $properties = [
            'country_of_origin' => NaturalLanguage::make()->country($product->country_of_origin),
            'ingredients'       => $product->marketing_ingredients,
            'tariff_code'       => $product->tariff_code,
            'duty_rate'         => $product->duty_rate,
            'hts_us'            => $product->hts_us,
        ];



        return [
            'slug'                          => $product->slug,
            'code'                          => $product->code,
            'name'                          => $product->name,
            'price'                         => $product->price,
            'currency'                      => $product->group->currency->code,
            'description'                   => $product->description,
            'description_title'             => $product->description_title,
            'created_at'                    => $product->created_at,
            'updated_at'                    => $product->updated_at,
            'description_extra'             => $product->description_extra,
            'units'                         => trimDecimalZeros($product->units),
            'unit'                          => $product->unit,
            'name_i8n'                      => $product->getTranslations('name_i8n'),
            'description_i8n'               => $product->getTranslations('description_i8n'),
            'description_title_i8n'         => $product->getTranslations('description_title_i8n'),
            'description_extra_i8n'         => $product->getTranslations('description_extra_i8n'),
            'marketing_dimensions'          => NaturalLanguage::make()->dimensions($product->marketing_dimensions),
            'marketing_weight'              => NaturalLanguage::make()->weight($product->marketing_weight),
            'gross_weight'                  => NaturalLanguage::make()->weight($product->gross_weight),
            'cpnp_number'                   => $product->cpnp_number,
            'ufi_number'                    => $product->ufi_number,
            'scpn_number'                   => $product->scpn_number,
            'un_number'                     => $product->un_number,
            'un_class'                      => $product->un_class,
            'packing_group'                 => $product->packing_group,
            'proper_shipping_name'          => $product->proper_shipping_name,
            'hazard_identification_number'  => $product->hazard_identification_number,
            'id'                            => $product->id,
            'main_images'                   => $product->imageSources(),
            'trade_units'                   => $dataTradeUnits,
            'gpsr'                          => $gpsr,
            'properties'                    => $properties,
            'salesData'                     => GetProductTimeSeriesData::run($product->resource),
            'attachment_box'                => [
                'public'  => [],
                'private' => []
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
