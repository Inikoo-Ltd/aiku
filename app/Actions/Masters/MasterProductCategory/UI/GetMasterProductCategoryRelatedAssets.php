<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 08 May 2026 15:02:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Actions\OrgAction;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Support\Facades\DB;

class GetMasterProductCategoryRelatedAssets extends OrgAction
{
    public function handle(MasterProductCategory $masterProductCategory): \Illuminate\Support\Collection
    {
        return DB::table('master_product_category_has_related_assets')
            ->leftJoin('master_assets', 'master_asset_id', 'master_assets.id')
            ->where('master_product_category_id', $masterProductCategory->id)
            ->orderBy('position')->get();
    }
}
