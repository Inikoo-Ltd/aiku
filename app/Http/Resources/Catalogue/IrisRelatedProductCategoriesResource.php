<?php

/*
 * author Louis Perez
 * created on 29-05-2026-13h-56m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Http\Resources\Catalogue;

use App\Models\Catalogue\ProductCategory;
use Illuminate\Http\Resources\Json\JsonResource;

class IrisRelatedProductCategoriesResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var ProductCategory $productCategory */
        $productCategory = $this->resource;

        return [
            'slug'                          => $productCategory->slug,
            'id'                            => $productCategory->id,
            'image_id'                      => $productCategory->image_id,
            'code'                          => $productCategory->code,
            'show_in_website'               => $productCategory->show_in_website,
            'name'                          => $productCategory->name,
            'state'                         => [
                'value' => $productCategory->state->value ?? null,
                'label' => $productCategory->state->labels()[$productCategory->state->value] ?? ucfirst($productCategory->state->value),
                'icon'  => $productCategory->state->stateIcon()[$productCategory->state->value]['icon'] ?? null,
                'class' => $productCategory->state->stateIcon()[$productCategory->state->value]['class'] ?? null,
            ],
            'description'                   => $productCategory->description,
            'image'                         => $productCategory->imageSources(720, 480),
            'created_at'                    => $productCategory->created_at,
            'updated_at'                    => $productCategory->updated_at,
            'stats'                         => $productCategory->stats,
            'number_current_products'       => $productCategory->number_current_products
        ];
    }
}
