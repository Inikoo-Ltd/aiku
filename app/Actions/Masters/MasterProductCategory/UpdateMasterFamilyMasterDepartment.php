<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 18 Sept 2025 14:39:46 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory;

use App\Actions\Masters\MasterProductCategory\Hydrators\MasterDepartmentHydrateMasterAssets;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterProductCategoryHydrateMasterFamilies;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterSubDepartmentHydrateMasterAssets;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateMasterFamiliesWithNoDepartment;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateMasterFamiliesWithNoDepartment;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateMasterFamilyMasterDepartment extends OrgAction
{
    use WithActionUpdate;
    use WithMastersEditAuthorisation;

    /**
     * @var \App\Models\Masters\MasterProductCategory
     */
    private MasterProductCategory $masterFamily;

    public function handle(MasterProductCategory $masterFamily, array $modelData): MasterProductCategory
    {


        $oldDepartment    = $masterFamily->masterDepartment ?? null;
        $oldSubDepartment = $masterFamily->masterSubDepartment ?? null;

        data_set($modelData, 'master_parent_id', Arr::get($modelData, 'master_department_id'));
        data_set($modelData, 'master_department_id', Arr::get($modelData, 'master_department_id'));
        data_set($modelData, 'master_sub_department_id', null);

        $masterFamily = $this->update($masterFamily, $modelData);
        $changes      = $masterFamily->getChanges();
        $masterFamily->refresh();
        DB::table('master_assets')
            ->where('master_family_id', $masterFamily->id)
            ->update([
                'master_department_id'     => $masterFamily->department_id,
                'master_sub_department_id' => null
            ]);


        if (Arr::has($changes, 'master_department_id')) {
            MasterDepartmentHydrateMasterAssets::dispatch($masterFamily->masterDepartment);
            MasterProductCategoryHydrateMasterFamilies::dispatch($masterFamily->masterDepartment);
            if ($oldDepartment) {
                MasterDepartmentHydrateMasterAssets::dispatch($oldDepartment);
                MasterProductCategoryHydrateMasterFamilies::dispatch($oldDepartment);
            } else {

                MasterShopHydrateMasterFamiliesWithNoDepartment::dispatch($masterFamily->masterShop);
                GroupHydrateMasterFamiliesWithNoDepartment::dispatch($masterFamily->group);
            }
        }

        if (Arr::has($changes, 'sub_department_id') && $oldSubDepartment) {
            MasterProductCategoryHydrateMasterFamilies::dispatch($oldSubDepartment);
            MasterSubDepartmentHydrateMasterAssets::dispatch($oldSubDepartment);
        }

        return $masterFamily;
    }


    public function rules(): array
    {
        return [
            'master_department_id' => [
                'required',
                Rule::exists('master_product_categories', 'id')
                    ->where('type', MasterProductCategoryTypeEnum::DEPARTMENT)
                    ->where('master_shop_id', $this->masterFamily->master_shop_id)
            ]
        ];
    }

    public function action(MasterProductCategory $masterFamily, array $modelData): MasterProductCategory
    {
        $this->asAction = true;
        $this->masterFamily = $masterFamily;
        $this->initialisationFromGroup($masterFamily->group, $modelData);

        return $this->handle($masterFamily, $this->validatedData);
    }


    public function asController(MasterProductCategory $masterFamily, ActionRequest $request): void
    {
        $this->masterFamily = $masterFamily;
        $this->initialisationFromGroup($masterFamily->group, $request);
        $this->handle($masterFamily, $this->validatedData);
    }
}
