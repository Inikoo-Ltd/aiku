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
use App\Models\Masters\MasterProductCategory;
use Lorisleiva\Actions\Concerns\AsObject;

class GetMasterProductCategoryShowcase
{
    use AsObject;

    public function handle(MasterProductCategory $productCategory): array
    {

        $data = [];
        switch ($productCategory->type) {
            case MasterProductCategoryTypeEnum::DEPARTMENT :
                $data = [
                    'department' => MasterProductCategoryResource::make($productCategory),
                    'families'   => MasterProductCategoryResource::collection($productCategory->masterFamilies()),
                ];
                break;

            default:
                $data = [
                    'family' => MasterProductCategoryResource::make($productCategory),
                ];
        }

        return $data;
    }
}
