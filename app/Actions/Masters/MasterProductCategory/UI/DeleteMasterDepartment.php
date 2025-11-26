<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 09 Jun 2025 01:30:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateMasterDepartments;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateMasterProductCategories;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Validation\Validator;
use Illuminate\Support\Facades\Log;

class DeleteMasterDepartment extends OrgAction
{

    public function handle(MasterProductCategory $masterProductCategory, bool $forceDelete = false)
    {
        Log::info('Deleting master product category', ['id' => $masterProductCategory->id, 'name' => $masterProductCategory->name]);

        DB::table('product_categories')->where('master_product_category_id', $masterProductCategory->id)->update(['master_product_category_id' => null]);

        if ($forceDelete) {


            DB::table('master_product_category_stats')->where('master_product_category_id', $masterProductCategory->id)->delete();
            DB::table('master_product_category_time_series')->where('master_product_category_id', $masterProductCategory->id)->delete();
            DB::table('master_product_category_ordering_stats')->where('master_product_category_id', $masterProductCategory->id)->delete();
            DB::table('master_product_category_sales_intervals')->where('master_product_category_id', $masterProductCategory->id)->delete();
            DB::table('master_product_category_ordering_intervals')->where('master_product_category_id', $masterProductCategory->id)->delete();


            $masterProductCategory->forceDelete();
        } else {
            $masterProductCategory->delete();
        }

    }
}
