<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 24 Mar 2024 20:31:47 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Goods;

use App\Http\Resources\Catalogue\BrandResource;
use App\Http\Resources\Catalogue\TagsResource;
use App\Http\Resources\Helpers\ImageResource;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Media;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

/**
 * @property string $code
 * @property string $slug
 * @property float $net_weight
 * @property string $type
 * @property string $name
 * @property mixed $number_current_stocks
 * @property mixed $number_current_products
 * @property mixed $id
 */
class TradeUnitsForMasterResource extends JsonResource
{
    public function toArray($request): array
    {
        $media = null;
        if ($this->image_id) {
            $media = Media::find($this->image_id);
        }
        // $tags = DB::table('model_has_tags')->where('model_type', 'TradeUnit')->where('model_id', $this->id)
        //         ->pluck('tag_id')->toArray();

        // $brands = DB::table('model_has_brands')->where('model_type', 'TradeUnit')->where('model_id', $this->id)
        //         ->pluck('brand_id')->toArray();

        $tradeUnit = TradeUnit::find($this->id);

        return [
            'slug'                    => $this->slug,
            'code'                    => $this->code,
            'name'                    => $this->name,
            'description'             => $this->description,
            'description_title'       => $this->description_title,
            'description_extra'       => $this->description_extra,
            'type'                    => $this->type,
            'net_weight'              => $this->net_weight,
            'marketing_weight'        => $this->marketing_weight,
            'gross_weight'            => $this->gross_weight,
            'dimensions'              => $this->marketing_dimensions,
            'type'                    => $this->type,
            'number_current_stocks'   => $this->number_current_stocks,
            'number_current_products' => $this->number_current_products,
            'id'                      => $this->id,
            'image'                   => $this->image_id ? ImageResource::make($media)->getArray() : null,
            'cost_price'              => $this->cost_price ?? 0,
            'tags'                    => TagsResource::collection($tradeUnit->tags)->resolve(),
            'brands'                  => BrandResource::collection($tradeUnit->brands)->resolve(),
        ];
    }
}
