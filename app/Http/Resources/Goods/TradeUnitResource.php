<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 24 Mar 2024 20:31:47 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Goods;

use App\Actions\Api\Retina\Dropshipping\Resource\ImageResource;
use App\Models\Goods\TradeUnit;
use Illuminate\Http\Resources\Json\JsonResource;

class TradeUnitResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var TradeUnit $tradeUnit */
        $tradeUnit = $this;

        $ingredients = $tradeUnit->ingredients->pluck('name');

        $specifications = [
            'ingredients'       => $ingredients,
            'gross_weight'      => $tradeUnit->gross_weight,
            'marketing_weights' => $tradeUnit->marketing_weights,
            'barcode'           => $tradeUnit->barcode,
            'dimensions'        => $tradeUnit->marketing_dimensions,
        ];

        return array(
            'slug'   => $tradeUnit->slug,
            'status' => $tradeUnit->status,
            'code'   => $tradeUnit->code,
            'id'     => $tradeUnit->id,

            'barcode'              => $tradeUnit->barcode,
            'gross_weight'         => $tradeUnit->gross_weight,
            'marketing_weight'     => $tradeUnit->marketing_weight,
            'marketing_dimensions' => $tradeUnit->marketing_dimensions,
            'volume'               => $tradeUnit->volume,
            'type'                 => $tradeUnit->type,
            'image_id'             => $tradeUnit->image_id,
            'images'          => ImageResource::collection($tradeUnit->images),
            'image_thumbnail' => $tradeUnit->imageSources(720, 480),
            'name'                  => $tradeUnit->name,
            'description'           => $tradeUnit->description,
            'description_title'     => $tradeUnit->description_title,
            'description_extra'     => $tradeUnit->description_extra,
            'name_i8n'              => $tradeUnit->getTranslations('name_i8n'),
            'description_i8n'       => $tradeUnit->getTranslations('description_i8n'),
            'description_title_i8n' => $tradeUnit->getTranslations('description_title_i8n'),
            'description_extra_i8n' => $tradeUnit->getTranslations('description_extra_i8n'),
            'specifications'        => $specifications,
        );
    }

    protected function safeDecode(?string $json): array
    {
        return $json ? json_decode($json, true) ?? [] : [];
    }
}
