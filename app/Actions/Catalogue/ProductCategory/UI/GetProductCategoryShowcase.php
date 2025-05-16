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
use App\Models\Catalogue\ProductCategory;
use Lorisleiva\Actions\Concerns\AsObject;

class GetProductCategoryShowcase
{
    use AsObject;

    public function handle(ProductCategory $productCategory): array
    {
        $data = [];
        switch ($productCategory->type) {
            case ProductCategoryTypeEnum::DEPARTMENT :
                $data = [
                    'url_master' => route('grp.masters.departments.show', [
                        'masterDepartment' => $productCategory->masterProductCategory->slug,
                    ]),
                    'department' => DepartmentResource::make($productCategory),
                    'families'   => FamilyResource::collection($productCategory->getFamilies()),
                ];
                break;

            default:
                $data = [
                    'family' => FamilyResource::make($productCategory),
                ];
        }
        return $data;
    }
}
