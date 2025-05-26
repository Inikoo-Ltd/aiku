<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 Jul 2024 16:42:55 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Catalogue;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $slug
 * @property string $shop_slug
 * @property string $department_slug
 * @property string $code
 * @property string $name
 * @property mixed $state
 * @property string $description
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property mixed $shop_code
 * @property mixed $shop_name
 */
class DepartmentResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var \App\Models\Catalogue\ProductCategory $department */
        $department = $this->resource;

        $urlMaster                              = null;
        if ($department->master_product_category_id) {
            $urlMaster = [
                'name'       => 'grp.masters.departments.show',
                'parameters' => [
                    $department->masterProductCategory->slug
                ]
            ];
        }


        return [
            'slug' => $department->slug,

            'code'             => $department->code,
            'name'             => $department->name,
            'state'            => [
                'label' => $department->state->labels()[$this->state->value],
                'icon'  => $department->state->stateIcon()[$this->state->value]['icon'],
                'class' => $department->state->stateIcon()[$this->state->value]['class']
            ],
            'description'      => $department->description,
            'created_at'       => $department->created_at,
            'updated_at'       => $department->updated_at,
            'current_families' => $department->stats->number_families ?? 0,
            'current_products' => $department->stats->number_products ?? 0,
            'type'             => $department->type,
            'show_in_website'  => $department->show_in_website,
            'url_master'       => $urlMaster,
            'image'           => $department->imageSources(720, 480),

        ];
    }
}
