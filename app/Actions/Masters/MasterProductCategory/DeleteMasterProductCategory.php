<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 09 Jun 2025 01:30:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Validation\Validator;

class DeleteMasterProductCategory extends OrgAction
{
    use WithMastersEditAuthorisation;

    private MasterProductCategory $masterProductCategory;

    public function handle(MasterProductCategory $masterProductCategory, bool $forceDelete = false): MasterProductCategory
    {

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

        return $masterProductCategory;
    }

    public function action(MasterProductCategory $masterProductCategory, bool $forceDelete = false): MasterProductCategory
    {
        $this->asAction = true;
        $this->masterProductCategory = $masterProductCategory;
        $this->initialisationFromGroup($masterProductCategory->group, []);
        return $this->handle($masterProductCategory, $forceDelete);
    }

    public function asController(MasterProductCategory $masterProductCategory, ActionRequest $request): MasterProductCategory
    {
        $this->masterProductCategory = $masterProductCategory;
        $this->initialisationFromGroup($masterProductCategory->group, $request);

        $forceDelete = $request->boolean('force_delete');

        return $this->handle($masterProductCategory, $forceDelete);
    }



    public function afterValidator(Validator $validator, ActionRequest $request): void
    {

        if ($this->masterProductCategory->children()->exists()) {
            $validator->errors()->add('children', 'This category has sub-categories associated with it.');
        }
    }

    public function htmlResponse(MasterProductCategory $masterProductCategory, ActionRequest $request): \Illuminate\Http\Response|array|\Illuminate\Http\RedirectResponse
    {
        return match ($masterProductCategory->type) {
            MasterProductCategoryTypeEnum::DEPARTMENT => Redirect::route('grp.masters.departments.index'),
            MasterProductCategoryTypeEnum::SUB_DEPARTMENT => Redirect::route('grp.masters.families.index'),
            default => []
        };
    }


}
