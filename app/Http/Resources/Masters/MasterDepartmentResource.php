<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 13 Dec 2025 11:02:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Masters;

use App\Models\Masters\MasterProductCategory;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class MasterDepartmentResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var MasterProductCategory $masterDepartment */
        $masterDepartment = $this;

        return [
            'id'               => $masterDepartment->id,
            'slug'             => $masterDepartment->slug,
            'code'             => $masterDepartment->code,
            'name'             => $masterDepartment->name,
            'image'            => Arr::get($masterDepartment->web_images, 'main.gallery'),
            'description'      => $masterDepartment->description,
            'show_in_website'  => $masterDepartment->show_in_website,
        ];
    }
}
