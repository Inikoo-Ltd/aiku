<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 30 May 2025 16:12:46 Central Indonesia Time, Sanur, change, China
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Web;

use App\Actions\Traits\HasBucketImages;
use App\Helpers\NaturalLanguage;
use App\Http\Resources\Catalogue\TagResource;
use App\Http\Resources\HasSelfCall;
use App\Models\Catalogue\Product;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Helpers\ImageResource;

class WebBlockProductResource extends JsonResource
{
    use HasSelfCall;
    use HasBucketImages;


    public function toArray($request): array
    {
        /** @var Product $product */
        $product = $this->resource;


        $tradeUnits = $product->tradeUnits;


        $tradeUnits->loadMissing(['ingredients']);

        $ingredients = $tradeUnits->flatMap(function ($tradeUnit) {
            return $tradeUnit->ingredients->pluck('name');
        })->unique()->values()->all();


        $specifications = [
            'country_of_origin' => NaturalLanguage::make()->country($product->country_of_origin),
            'ingredients'       => $ingredients,
            'gross_weight'      => $product->gross_weight,
            'marketing_weights' => $tradeUnits->pluck('marketing_weights')->flatten()->filter()->values()->all(),
            'barcode'           => $product->barcode,
            'dimensions'        => NaturalLanguage::make()->dimensions(json_encode($product->marketing_dimensions)),
            'cpnp'              => $product->cpnp_number,
            'net_weight'        => $product->marketing_weight,
            'unit'              => $product->unit,
        ];

        $margin     = '';
        $rrpPerUnit = '';
        $profit     = '';
        if ($product->rrp > 0) {
            $margin     = percentage(round((($product->rrp - $product->price) / $this->rrp) * 100, 1), 100);
            $rrpPerUnit = round($product->rrp / $product->units, 2);
            $profit     = round(($product->price - $product->rrp) / $product->units, 2);
        }


        return [
            'luigi_identity'    => $product->getLuigiIdentity(),
            'slug'              => $product->slug,
            'code'              => $product->code,
            'name'              => $product->name,
            'description'       => $product->description,
            'description_title' => $product->description_title,
            'description_extra' => $product->description_extra,
            'stock'             => $product->available_quantity,
            'specifications'    => $tradeUnits->count() > 0 ? $specifications : null,
            'contents'          => ModelHasContentsResource::collection($product->contents)->toArray($request),
            'id'                => $product->id,
            'image_id'          => $product->image_id,
            'currency_code'     => $product->currency->code,
            'rrp'               => $product->rrp,
            'rrp_per_unit'      => $rrpPerUnit,
            'margin'            => $margin,
            'profit'            => $profit,
            'price'             => $product->price,
            'status'            => $product->status,
            'state'             => $product->state,
            'units'             => $product->units,
            'unit'              => $product->unit,
            'web_images'        => $product->web_images,
            'created_at'        => $product->created_at,
            'updated_at'        => $product->updated_at,
            'images'            => $product->bucket_images ? $this->getImagesData($product) : ImageResource::collection($product->images)->toArray($request),
            'tags'              => TagResource::collection($product->tradeUnitTagsViaTradeUnits())->toArray($request),
        ];
    }


}
