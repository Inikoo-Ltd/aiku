<?php

/*
 * author Louis Perez
 * created on 28-05-2026-14h-55m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Http\Resources\Web;

use App\Actions\Traits\HasBucketImages;
use App\Helpers\NaturalLanguage;
use App\Http\Resources\Catalogue\TagResource;
use App\Http\Resources\HasSelfCall;
use App\Http\Resources\Traits\HasPriceMetrics;
use App\Models\Catalogue\Product;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Helpers\ImageResource;

/**
 * @property mixed $units
 * @property mixed $rrp
 * @property mixed $id
 */
class WebBlockProductForWorkshopResource extends JsonResource
{
    use HasSelfCall;
    use HasBucketImages;
    use HasPriceMetrics;


    public function toArray($request): array
    {
        /** @var Product $product */
        $product = $this->resource;

        $specifications = [
            'country_of_origin' => NaturalLanguage::make()->country($product->country_of_origin),
            'ingredients'       => $product->marketing_ingredients,
            'gross_weight'      => $product->gross_weight,
            'barcode'           => $product->barcode,
            'dimensions'        => NaturalLanguage::make()->dimensions(json_encode($product->marketing_dimensions)),
            'cpnp'              => $product->cpnp_number,
            'marketing_weight'  => $product->marketing_weight,
            'unit'              => $product->unit,
        ];

        [$margin, $rrpPerUnit, $profit, $profitPerUnit, $units, $pricePerUnit] = $this->getPriceMetrics($product->rrp, $product->price, $product->units);

        return [
            'luigi_identity'    => $product->getLuigiIdentity(),
            'slug'              => $product->slug,
            'code'              => $product->code,
            'name'              => $product->name,
            'description'       => $product->description,
            'description_title' => $product->description_title,
            'description_extra' => $product->description_extra,
            'stock'             => $product->available_quantity,
            'specifications'    => $specifications,
            'contents'          => ModelHasContentsResource::collection($product->contents)->toArray($request),
            'id'                => $product->id,
            'currency_code'     => $product->currency->code,
            'rrp'               => $product->rrp,
            'rrp_per_unit'      => $rrpPerUnit,
            'margin'            => $margin,
            'profit'            => $profit,
            'profit_per_unit'   => $profitPerUnit,
            'price'             => $product->price,
            'price_per_unit'    => $pricePerUnit,
            'status_label'      => $product->status->labels()[$product->status->value],
            'units'             => $units,
            'unit'              => $product->unit,
            'web_images'        => $product->web_images,
            'images'            => $product->bucket_images ? $this->getImagesData($product, true) : ImageResource::collection($product->images)->toArray($request),
            'tags'              => TagResource::collection($product->tags)->toArray($request),
        ];
    }
}
