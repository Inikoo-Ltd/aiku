<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 13 Dec 2025 14:35:51 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Masters;

use App\Models\Masters\MasterProductCategory;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class MasterSubDepartmentResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var MasterProductCategory $masterSubDepartment */
        $masterSubDepartment = $this;

        return [
            'id'                => $masterSubDepartment->id,
            'slug'              => $masterSubDepartment->slug,
            'code'              => $masterSubDepartment->code,
            'name'              => $masterSubDepartment->name,
            'description'       => $masterSubDepartment->description,
            'created_at'        => $masterSubDepartment->created_at,
            'updated_at'        => $masterSubDepartment->updated_at,
            'number_families'   => $masterSubDepartment->number_families,
            'number_products'   => $masterSubDepartment->number_products,
            'description_title' => $masterSubDepartment->description_title,
            'description_extra' => $masterSubDepartment->description_extra,
            'image'             => Arr::get($masterSubDepartment->web_images, 'main.gallery'),
            'status_icon'       => $masterSubDepartment->status
                ? [
                    'tooltip' => __('Active'),
                    'icon'    => 'fas fa-check-circle',
                    'class'   => 'text-green-400'
                ]
                : [
                    'tooltip' => __('Closed'),
                    'icon'    => 'fas fa-times-circle',
                    'class'   => 'text-red-400'
                ],
        ];
    }
}
