<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 27-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Actions\OrgAction;
use App\Models\Masters\MasterProductCategory;

class GetMasterProductCategoryRecomendation extends OrgAction
{
    public function handle(MasterProductCategory $masterProductCategory): array
    {
        return [
            'id' => $masterProductCategory->id,
            'data' => $masterProductCategory->relatedMasterAssets,
            'editable' => true
        ];
    }
}
