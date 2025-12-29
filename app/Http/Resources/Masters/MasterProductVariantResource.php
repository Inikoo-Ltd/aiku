<?php

namespace App\Http\Resources\Masters;

use App\Actions\Traits\HasBucketImages;
use App\Models\Masters\MasterAsset;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Helpers\NaturalLanguage;
use App\Actions\Traits\HasBucketAttachment;
use App\Actions\Goods\TradeUnit\UI\GetTradeUnitShowcase;
use Illuminate\Support\Facades\DB;
use App\Models\Goods\TradeUnit;
use App\Actions\Masters\MasterAsset\GetMasterProductSalesData;

class MasterProductVariantResource extends JsonResource
{
    use HasBucketImages;
    use HasBucketAttachment;

    public function toArray($request): array
    {
        /** @var MasterAsset $masterProduct */
        $masterProduct = $this;

        $dataTradeUnits = [];
        if ($masterProduct->tradeUnits) {
            $dataTradeUnits = $this->getDataTradeUnit($masterProduct->tradeUnits);
        }

        $gpsr = [
            'eu_responsible'             => $masterProduct->gpsr_eu_responsible,
            'warnings'                   => $masterProduct->gpsr_warnings,
            'how_to_use'                 => $masterProduct->gpsr_manual,
            'gpsr_class_category_danger' => $masterProduct->gpsr_class_category_danger,
            'product_languages'          => $masterProduct->gpsr_class_languages,
            'acute_toxicity'             => $masterProduct->pictogram_toxic,
            'corrosive'                  => $masterProduct->pictogram_corrosive,
            'explosive'                  => $masterProduct->pictogram_explosive,
            'flammable'                  => $masterProduct->pictogram_flammable,
            'gas_under_pressure'         => $masterProduct->pictogram_gas,
            'hazard_environment'         => $masterProduct->pictogram_environment,
            'health_hazard'              => $masterProduct->pictogram_health,
            'oxidising'                  => $masterProduct->pictogram_oxidising,
            'manufacturer'               => $masterProduct->gpsr_manufacturer,
        ];

        $properties = [
            'country_of_origin' => NaturalLanguage::make()->country($masterProduct->country_of_origin),
            'ingredients'       => $masterProduct->marketing_ingredients,
            'tariff_code'       => $masterProduct->tariff_code,
            'duty_rate'         => $masterProduct->duty_rate,
            'hts_us'            => $masterProduct->hts_us,
        ];



        return [
            'slug'                          => $masterProduct->slug,
            'code'                          => $masterProduct->code,
            'name'                          => $masterProduct->name,
            'price'                         => $masterProduct->price,
            'currency'                      => $masterProduct->group->currency->code,
            'description'                   => $masterProduct->description,
            'description_title'             => $masterProduct->description_title,
            'created_at'                    => $masterProduct->created_at,
            'updated_at'                    => $masterProduct->updated_at,
            'description_extra'             => $masterProduct->description_extra,
            'units'                         => trimDecimalZeros($masterProduct->units),
            'unit'                          => $masterProduct->unit,
            'name_i8n'                      => $masterProduct->getTranslations('name_i8n'),
            'description_i8n'               => $masterProduct->getTranslations('description_i8n'),
            'description_title_i8n'         => $masterProduct->getTranslations('description_title_i8n'),
            'description_extra_i8n'         => $masterProduct->getTranslations('description_extra_i8n'),
            'marketing_dimensions'          => NaturalLanguage::make()->dimensions($masterProduct->marketing_dimensions),
            'marketing_weight'              => NaturalLanguage::make()->weight($masterProduct->marketing_weight),
            'gross_weight'                  => NaturalLanguage::make()->weight($masterProduct->gross_weight),
            'cpnp_number'                   => $masterProduct->cpnp_number,
            'ufi_number'                    => $masterProduct->ufi_number,
            'scpn_number'                   => $masterProduct->scpn_number,
            'un_number'                     => $masterProduct->un_number,
            'un_class'                      => $masterProduct->un_class,
            'packing_group'                 => $masterProduct->packing_group,
            'proper_shipping_name'          => $masterProduct->proper_shipping_name,
            'hazard_identification_number'  => $masterProduct->hazard_identification_number,
            'id'                            => $masterProduct->id,
            'main_images'                   => $masterProduct->imageSources(),
            'trade_units'                   => $dataTradeUnits,
            'gpsr'                          => $gpsr,
            'properties'                    => $properties,
            'salesData'                     => GetMasterProductSalesData::run($masterProduct->resource),
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
