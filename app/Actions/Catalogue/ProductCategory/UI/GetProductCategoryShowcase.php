<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 May 2023 20:59:08 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory\UI;

use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Http\Resources\Catalogue\DepartmentResource;
use App\Http\Resources\Catalogue\FamilyResource;
use App\Http\Resources\Catalogue\SubDepartmentResource;
use App\Models\Catalogue\ProductCategory;
use Lorisleiva\Actions\Concerns\AsObject;

class GetProductCategoryShowcase
{
    use AsObject;

    public function handle(ProductCategory $productCategory): array
    {
        if ($productCategory->type == ProductCategoryTypeEnum::DEPARTMENT) {

            $data = [
                'department' => DepartmentResource::make($productCategory)->toArray(request()),
                'subDepartments' => $productCategory?->children ? SubDepartmentResource::collection($productCategory?->children)->toArray(request()) : [],
                'families'   => FamilyResource::collection($productCategory->getFamilies())->toArray(request()),
            ];
        } elseif ($productCategory->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
            $data = [
                'subDepartment' => SubDepartmentResource::make($productCategory->department)->toArray(request()),
                'families'   => FamilyResource::collection($productCategory->getFamilies())->toArray(request()),
            ];
        } else {
            $data = [
                'family' => FamilyResource::make($productCategory),
            ];
        }

        $data['routes'] = [
            'detach_family' => [
                'name'       => 'grp.models.sub-department.family.detach',
                'parameters' => [
                    'subDepartment' => $productCategory->slug,
                ],
                'method'     => 'delete'
            ],
        ];

        return $data;
    }
}
