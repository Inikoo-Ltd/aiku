<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 14-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Http\Resources\Catalogue\MasterProductCategoryResource;
use App\Http\Resources\Masters\MasterFamiliesResource;
use App\Http\Resources\Masters\MasterSubDepartmentsResource;
use App\Models\Masters\MasterProductCategory;
use Lorisleiva\Actions\Concerns\AsObject;

class GetMasterProductCategoryShowcase
{
    use AsObject;

    public function handle(MasterProductCategory $productCategory): array
    {

        return match ($productCategory->type) {
            MasterProductCategoryTypeEnum::DEPARTMENT => [
                'department' => MasterProductCategoryResource::make($productCategory),
                'families' => MasterProductCategoryResource::collection($productCategory->masterFamilies()),
            ],
            MasterProductCategoryTypeEnum::SUB_DEPARTMENT => [
                'subDepartment' => MasterSubDepartmentsResource::make($productCategory),
                'families' => MasterFamiliesResource::collection($productCategory->masterFamilies()),
            ],
            default => [
                'family' => MasterProductCategoryResource::make($productCategory),
            ],
        };
    }
}
