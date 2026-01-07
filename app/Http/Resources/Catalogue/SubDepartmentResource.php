<?php

/*
 *  Author: Jonathan lopez <raul@inikoo.com>
 *  Created: Sat, 22 Oct 2022 18:53:15 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, inikoo
 */

namespace App\Http\Resources\Catalogue;

use App\Models\Catalogue\ProductCategory;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $slug
 * @property string $code
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property string $name
 * @property string $state
 * @property integer $image_id
 *
 */
class SubDepartmentResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var ProductCategory $subDepartment */
        $subDepartment = $this->resource;

        $urlMaster                              = null;
        if ($subDepartment->master_product_category_id) {
            $urlMaster = [
                'name'       => 'grp.helpers.redirect_master_product_category',
                'parameters' => [
                   $subDepartment->masterProductCategory->id
                ]
            ];
        }

        return [
            'slug'       => $subDepartment->slug,
            'id'        => $subDepartment->id,
            'image_id'   => $subDepartment->image_id,
            'image'      => $subDepartment->imageSources(720, 720),
            'code'       => $subDepartment->code,
            'name'       => $subDepartment->name,
            'state'      => $subDepartment->state,
            'created_at' => $subDepartment->created_at,
            'updated_at' => $subDepartment->updated_at,
            'url_master'       => $urlMaster,
             'is_name_reviewed' => $subDepartment->is_name_reviewed,
             'is_description_title_reviewed' => $subDepartment->is_description_title_reviewed,
             'is_description_reviewed' => $subDepartment->is_description_reviewed,
             'is_description_extra_reviewed' => $subDepartment->is_description_extra_reviewed,
            'stats' => $subDepartment->stats
        ];
    }
}
