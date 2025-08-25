<?php

/*
 * author Arya Permana - Kirin
 * created on 30-06-2025-16h-26m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Masters\MasterProductCategory;

use App\Actions\GrpAction;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterDepartmentHydrateMasterAssets;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterDepartmentHydrateMasterSubDepartments;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterProductCategoryHydrateMasterFamilies;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateMasterFamiliesWithNoDepartment;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateMasterFamiliesWithNoDepartment;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UpdateMasterSubDepartmentMasterDepartment extends GrpAction
{
    use WithActionUpdate;
    private MasterShop $masterShop;

    public function handle(MasterProductCategory $subDepartment, array $modelData): MasterProductCategory
    {
        $oldDepartment    = $subDepartment->masterDepartment ?? null;

        data_set($modelData, 'master_parent_id', Arr::get($modelData, 'master_department_id'));
        data_set($modelData, 'master_department_id', Arr::get($modelData, 'master_department_id'));

        $subDepartment  = $this->update($subDepartment, $modelData);
        $changes = $subDepartment->getChanges();
        $subDepartment->refresh();

        DB::table('master_product_categories')
            ->where('master_sub_department_id', $subDepartment->id)
            ->update([
                'master_department_id'     => $subDepartment->master_department_id,
            ]);

        DB::table('master_assets')
            ->where('master_sub_department_id', $subDepartment->id)
            ->update([
                'master_department_id'     => $subDepartment->master_department_id,
            ]);

        if (Arr::has($changes, 'master_department_id')) {
            MasterDepartmentHydrateMasterAssets::dispatch($subDepartment->masterDepartment);
            MasterDepartmentHydrateMasterSubDepartments::dispatch($subDepartment->masterDepartment);
            MasterProductCategoryHydrateMasterFamilies::dispatch($subDepartment->masterDepartment);
            if ($oldDepartment) {
                MasterDepartmentHydrateMasterAssets::dispatch($oldDepartment);
                MasterProductCategoryHydrateMasterFamilies::dispatch($oldDepartment);
                MasterDepartmentHydrateMasterSubDepartments::dispatch($oldDepartment);
            } else {
                MasterShopHydrateMasterFamiliesWithNoDepartment::dispatch($subDepartment->masterShop);
                GroupHydrateMasterFamiliesWithNoDepartment::dispatch($subDepartment->group);
            }
        }

        return $subDepartment;
    }


    public function rules(): array
    {
        return [
            'master_department_id' => [
                'required',
                Rule::exists('master_product_categories', 'id')
                    ->where('type', MasterProductCategoryTypeEnum::DEPARTMENT)
                    ->where('master_shop_id', $this->masterShop->id)
            ]
        ];
    }

    public function action(MasterProductCategory $subDepartment, array $modelData): MasterProductCategory
    {
        $this->asAction = true;
        $this->masterShop = $subDepartment->masterShop;
        $this->initialisation($subDepartment->group, $modelData);

        return $this->handle($subDepartment, $this->validatedData);
    }
}
