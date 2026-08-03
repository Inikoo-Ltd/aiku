<?php

/*
 * author Louis Perez
 * created on 02-06-2026-11h-05m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Http\Resources\Catalogue;

use App\Http\Resources\HasSelfCall;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductCategoryForRelatedWebBlockResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var ProductCategory $productCategory */
        $productCategory = $this->resource;

        $image = data_get($productCategory->web_images, 'main.gallery', []);

        return [
            'id'            => $productCategory->id,
            'key'           => "{$productCategory->type->value}-{$productCategory->id}",
            'slug'          => $productCategory->slug,
            'code'          => $productCategory->code,
            'name'          => $productCategory->name,
            'description'   => $productCategory->description,
            'image'         => $image,
            'shorthand_url' => $productCategory->shorthand_url,
            'canonical_url' => $productCategory->canonical_url,
        ];
    }
}
