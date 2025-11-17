<?php

/*
 *  Author: Jonathan lopez <raul@inikoo.com>
 *  Created: Sat, 22 Oct 2022 18:53:15 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, inikoo
 */

namespace App\Http\Resources\Catalogue;

use App\Helpers\NaturalLanguage;
use App\Http\Resources\HasSelfCall;
use App\Http\Resources\Helpers\ImageResource;
use App\Models\Catalogue\Product;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var Product $product */
        $product = $this;


        $pickingFactor = [];
        foreach ($product->orgStocks as $orgStock) {
            $pickingFactor[] = [
                'org_stock_id'   => $orgStock->id,
                'org_stock_code' => $orgStock->code,
                'note'           => $orgStock->pivot->note,
                'picking_factor' => riseDivisor(
                    divideWithRemainder(findSmallestFactors($orgStock->pivot->quantity)),
                    $orgStock->packed_in
                )
            ];
        }


        return [
            'id'                            => $product->id,
            'slug'                          => $product->slug,
            'image_id'                      => $product->image_id,
            'code'                          => $product->code,
            'name'                          => $product->name,
            'units'                         => trim_decimal_zeros($product->units),
            'unit'                          => $product->unit,
            'rrp'                           => $product->rrp,
            'barcode'                       => $product->barcode,
            'price'                         => $product->price,
            'currency_code'                 => $product->currency->code,
            'description'                   => $product->description,
            'description_title'             => $product->description_title,
            'description_extra'             => $product->description_extra,
            'state'                         => $product->state,
            'created_at'                    => $product->created_at,
            'updated_at'                    => $product->updated_at,
            'images'                        => ImageResource::collection($product->images),
            'image_thumbnail'               => $product->imageSources(720, 480),
            'stock'                         => $product->available_quantity,
            'marketing_dimensions'          => NaturalLanguage::make()->dimensions($product->marketing_dimensions),
            'marketing_ingredients'         => $product->marketing_ingredients,
            'marketing_weight'              => NaturalLanguage::make()->weight($product->marketing_weight),
            'gross_weight'                  => NaturalLanguage::make()->weight($product->gross_weight),
            'is_name_reviewed'              => $product->is_name_reviewed,
            'is_description_title_reviewed' => $product->is_description_title_reviewed,
            'is_description_reviewed'       => $product->is_description_reviewed,
            'is_description_extra_reviewed' => $product->is_description_extra_reviewed,
            'cpnp_number'                   => $product->cpnp_number,
            'ufi_number'                    => $product->ufi_number,
            'scpn_number'                   => $product->scpn_number,
            'picking_factor'                => $pickingFactor,
            'country_of_origin'             => NaturalLanguage::make()->country($product->country_of_origin),
            'tariff_code'                   => $product->tariff_code,
            'duty_rate'                     => $product->duty_rate,
            'hts_us'                        => $product->hts_us,
            'un_number'                     => $product->un_number,
            'un_class'                      => $product->un_class,
            'packing_group'                 => $product->packing_group,
            'proper_shipping_name'          => $product->proper_shipping_name,
            'hazard_identification_number'  => $product->hazard_identification_number,
            'gpsr_manufacturer'             => $product->gpsr_manufacturer,
            'gpsr_eu_responsible'           => $product->gpsr_eu_responsible,
            'gpsr_warnings'                 => $product->gpsr_warnings,
            'gpsr_manual'                   => $product->gpsr_manual,
            'gpsr_class_category_danger'    => $product->gpsr_class_category_danger,
            'gpsr_product_languages'        => $product->gpsr_product_languages,


        ];
    }
}
