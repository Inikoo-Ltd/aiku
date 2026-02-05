<?php

/*
 *  Author: Jonathan lopez <raul@inikoo.com>
 *  Created: Sat, 22 Oct 2022 18:53:15 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, inikoo
 */

namespace App\Http\Resources\Catalogue;

use App\Models\Catalogue\ProductCategory;
use Illuminate\Http\Resources\Json\JsonResource;

class IrisFamilyResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var ProductCategory $family */
        $family = $this->resource;

        return [
            'slug'                          => $family->slug,
            'id'                            => $family->id,
            'image_id'                      => $family->image_id,
            'code'                          => $family->code,
            'show_in_website'               => $family->show_in_website,
            'name'                          => $family->name,
            'department_name'               => $family->department_name,
            'department_slug'               => $family->department_slug,
            'sub_department_name'           => $family->sub_department_name,
            'sub_department_slug'           => $family->sub_department_slug,
            'state'                         => [
                'value' => $family->state->value ?? null,
                'label' => $family->state->labels()[$family->state->value] ?? ucfirst($family->state->value),
                'icon'  => $family->state->stateIcon()[$family->state->value]['icon'] ?? null,
                'class' => $family->state->stateIcon()[$family->state->value]['class'] ?? null,
            ],
            'description'                   => $family->description,
            'image'                         => $family->imageSources(720, 480),
            'created_at'                    => $family->created_at,
            'updated_at'                    => $family->updated_at,
            'stats'                         => $family->stats,
            'number_current_products'       => $family->number_current_products
        ];
    }
}
