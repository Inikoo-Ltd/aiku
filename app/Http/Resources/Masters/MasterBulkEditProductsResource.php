<?php

namespace App\Http\Resources\Masters;
/*
 * Author: Vika Aqordi
 * Created on 22-12-2025-13h-24m
 * Github: https://github.com/aqordeon
 * Copyright: 2025
*/

use App\Actions\Traits\HasBucketImages;
use App\Models\Masters\MasterAsset;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Helpers\NaturalLanguage;
use App\Actions\Traits\HasBucketAttachment;

class MasterBulkEditProductsResource extends JsonResource
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
            'id'                         => $masterProduct->id,
            'shop_id'                    => $masterProduct->shop_id,
            'slug'                       => $masterProduct->slug,
            'code'                       => $masterProduct->code,
            'name'                       => $masterProduct->name,
            'price'                      => $masterProduct->price,
            'rrp'                        => $masterProduct->rrp ?? 0,
            'is_for_sale'                => $masterProduct->is_for_sale,
            'currency'                   => $masterProduct->group->currency->code,
            'master_family_id'           => $masterProduct->master_family_id,
            'master_family_data'         => $masterProduct->masterFamily ? [
                'id'    => $masterProduct->masterFamily->id,
                'name'  => $masterProduct->masterFamily->name,
            ] : null,
            'web_images'                   => $masterProduct->web_images,
            'description'                   => $masterProduct->description,
            'description_title'             => $masterProduct->description_title,
            'created_at'                    => $masterProduct->created_at,
            'updated_at'                    => $masterProduct->updated_at,
            'description_extra'             => $masterProduct->description_extra,
            'units'                         => trimDecimalZeros($masterProduct->units),
            'unit'                          => $masterProduct->unit,
            'unit_price'                          => $masterProduct->unit_price,
            'name_i8n'                      => $masterProduct->getTranslations('name_i8n'),
            'description_i8n'               => $masterProduct->getTranslations('description_i8n'),
            'description_title_i8n'         => $masterProduct->getTranslations('description_title_i8n'),
            'description_extra_i8n'         => $masterProduct->getTranslations('description_extra_i8n'),
            'country_of_origin'             => NaturalLanguage::make()->country($masterProduct->tradeUnits()->first()?->country_of_origin),
            'marketing_dimensions'          => NaturalLanguage::make()->dimensions($masterProduct->marketing_dimensions),
            'marketing_ingredients'         => $masterProduct->marketing_ingredients,
            'marketing_weight'              => NaturalLanguage::make()->weight($masterProduct->marketing_weight),
            'gross_weight'                  => $masterProduct->gross_weight ?? 0,
            'gross_weight_human'                  => NaturalLanguage::make()->weight($masterProduct->gross_weight),
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
