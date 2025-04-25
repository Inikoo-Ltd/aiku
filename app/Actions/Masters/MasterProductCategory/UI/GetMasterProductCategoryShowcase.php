<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 14-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Http\Resources\Catalogue\FamilyResource;
use App\Models\Goods\MasterProductCategory;
use Lorisleiva\Actions\Concerns\AsObject;

class GetMasterProductCategoryShowcase
{
    use AsObject;

    public function handle(MasterProductCategory $productCategory): array
    {
        return [
            'family' => FamilyResource::make($productCategory),
        ];
    }
}
