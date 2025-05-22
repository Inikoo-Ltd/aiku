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
        if ($productCategory->type == ProductCategoryTypeEnum::DEPARTMENT) {

            $data = [
                'department' => DepartmentResource::make($productCategory),
                'families'   => FamilyResource::collection($productCategory->getFamilies()),
            ];
        } elseif ($productCategory->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
            // TODO: check this
            $data = [
                'department' => DepartmentResource::make($productCategory->department),
                'families'   => FamilyResource::collection($productCategory->getSubDepartmentFamilies()),
            ];
        } else {
            $data = [
                'family' => FamilyResource::make($productCategory),
            ];
        }

        return $data;
    }
}
