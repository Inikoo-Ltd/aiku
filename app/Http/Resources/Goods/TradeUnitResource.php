<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 24 Mar 2024 20:31:47 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Goods;

use App\Actions\Api\Retina\Dropshipping\Resource\ImageResource;
use App\Http\Resources\Catalogue\BrandResource;
use App\Http\Resources\Catalogue\TagsResource;
use App\Models\Goods\TradeUnit;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Helpers\NaturalLanguage;

class TradeUnitResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var TradeUnit $tradeUnit */
        $tradeUnit = $this;

        $ingredients = $tradeUnit->ingredients->pluck('name');

        $specifications = [
            'ingredients'      => $ingredients,
            'gross_weight'     => $tradeUnit->gross_weight,
            'marketing_weight' => $tradeUnit->marketing_weight,
            'barcode'          => $tradeUnit->barcode,
            'dimensions'       => $tradeUnit->marketing_dimensions,
        ];

        return array(
            'created_at'                   => $tradeUnit->created_at,
            'updated_at'                   => $tradeUnit->updated_at,
            'slug'                         => $tradeUnit->slug,
            'status'                       => $tradeUnit->status,
            'code'                         => $tradeUnit->code,
            'id'                           => $tradeUnit->id,
            'cpnp_number'                  => $tradeUnit->cpnp_number,
            'ufi_number'                   => $tradeUnit->ufi_number,
            'scpn_number'                  => $tradeUnit->scpn_number,
            'stock'                        => $tradeUnit->orgStocks->sum('quantity_in_locations'),
            'barcode'                      => $tradeUnit->barcode,
            'gross_weight'                 => NaturalLanguage::make()->weight($tradeUnit->gross_weight),
            'marketing_weight'             => NaturalLanguage::make()->weight($tradeUnit->marketing_weight),
            'marketing_dimensions'         => NaturalLanguage::make()->dimensions($tradeUnit->marketing_dimensions),
            'volume'                       => $tradeUnit->volume,
            'type'                         => $tradeUnit->type,
            'image_id'                     => $tradeUnit->image_id,
            'units'                        => $tradeUnit->type,
            'images'                       => ImageResource::collection($tradeUnit->images),
            'image_thumbnail'              => $tradeUnit->imageSources(720, 480),
            'name'                         => $tradeUnit->name,
            'description'                  => $tradeUnit->description,
            'description_title'            => $tradeUnit->description_title,
            'description_extra'            => $tradeUnit->description_extra,
            'name_i8n'                     => $tradeUnit->getTranslations('name_i8n'),
            'description_i8n'              => $tradeUnit->getTranslations('description_i8n'),
            'description_title_i8n'        => $tradeUnit->getTranslations('description_title_i8n'),
            'description_extra_i8n'        => $tradeUnit->getTranslations('description_extra_i8n'),
            'specifications'               => $specifications,
            'brands'                       => BrandResource::collection($tradeUnit->brands)->resolve(),
            'tags'                         => TagsResource::collection($tradeUnit->tags)->resolve(),
            'un_number'                    => $tradeUnit->un_number,
            'un_class'                     => $tradeUnit->un_class,
            'packing_group'                => $tradeUnit->packing_group,
            'proper_shipping_name'         => $tradeUnit->proper_shipping_name,
            'hazard_identification_number' => $tradeUnit->hazard_identification_number
        );
    }

    protected function safeDecode(?string $json): array
    {
        return $json ? json_decode($json, true) ?? [] : [];
    }
}
