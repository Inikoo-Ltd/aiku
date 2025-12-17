<?php

namespace App\Http\Resources\Masters;

use App\Actions\Traits\HasBucketImages;
use App\Models\Masters\MasterAsset;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Helpers\NaturalLanguage;
use App\Actions\Traits\HasBucketAttachment;

class MasterProductResource extends JsonResource
{
    use HasBucketImages;
    use HasBucketAttachment;

    public function toArray($request): array
    {
        /** @var MasterAsset $masterProduct */
        $masterProduct = $this;

        $tradeUnits = $masterProduct->tradeUnits;

        $tradeUnits->loadMissing('ingredients');

        $ingredients = $tradeUnits->flatMap(function ($tradeUnit) {
            return $tradeUnit->ingredients->pluck('name');
        })->unique()->values()->all();


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
            'country_of_origin'             => NaturalLanguage::make()->country($masterProduct->tradeUnits()->first()?->country_of_origin),
            'marketing_dimensions'          => NaturalLanguage::make()->dimensions($masterProduct->marketing_dimensions),
            'marketing_ingredients'         => $masterProduct->marketing_ingredients,
            'marketing_weight'              => NaturalLanguage::make()->weight($masterProduct->marketing_weight),
            'gross_weight'                  => NaturalLanguage::make()->weight($masterProduct->gross_weight),
            'cpnp_number'                   => $masterProduct->cpnp_number,
            'ufi_number'                    => $masterProduct->ufi_number,
            'scpn_number'                   => $masterProduct->scpn_number,
            'hts_us'                        => $masterProduct->hts_us,
            'un_number'                     => $masterProduct->un_number,
            'un_class'                      => $masterProduct->un_class,
            'packing_group'                 => $masterProduct->packing_group,
            'proper_shipping_name'          => $masterProduct->proper_shipping_name,
            'hazard_identification_number'  => $masterProduct->hazard_identification_number,
        ];
    }
}
