<?php

namespace App\Actions\Masters\MasterProductCategory;

use App\Actions\Catalogue\ProductCategory\Hydrators\DepartmentHydrateProducts;
use App\Actions\Catalogue\ProductCategory\Hydrators\ProductCategoryHydrateFamilies;
use App\Actions\Catalogue\ProductCategory\Hydrators\SubDepartmentHydrateProducts;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateFamiliesWithNoDepartment;
use App\Actions\GrpAction;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterDepartmentHydrateMasterAssets;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterProductCategoryHydrateMasterFamilies;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterSubDepartmentHydrateMasterAssets;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateMasterFamiliesWithNoDepartment;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateFamiliesWithNoDepartment;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateMasterFamiliesWithNoDepartment;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateFamiliesWithNoDepartment;
use App\Actions\Traits\Authorisations\WithCatalogueEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UpdateMasterFamilyMasterSubDepartment extends GrpAction
{
    use WithActionUpdate;

    private MasterShop $masterShop;

    public function handle(MasterProductCategory $family, array $modelData): MasterProductCategory
    {

        $newSubDepartmentId = MasterProductCategory::find(Arr::get($modelData, 'master_sub_department_id'));

        $oldDepartment    = $family->masterDepartment ?? null;
        $oldSubDepartment = $family->masterSubDepartment ?? null;

        data_set($modelData, 'master_parent_id', $newSubDepartmentId->id);
        data_set($modelData, 'master_sub_department_id', $newSubDepartmentId->id);
        data_set($modelData, 'master_department_id', $newSubDepartmentId->master_department_id);

        $family  = $this->update($family, $modelData);
        $changes = $family->getChanges();
        $family->refresh();
        DB::table('master_assets')
            ->where('master_family_id', $family->id)
            ->update([
                'master_department_id'     => $family->master_department_id,
                'master_sub_department_id' => $family->master_sub_department_id,
            ]);


        if (Arr::has($changes, 'master_department_id')) {
            MasterDepartmentHydrateMasterAssets::dispatch($family->masterDepartment);
            MasterProductCategoryHydrateMasterFamilies::dispatch($family->masterDepartment);
            if ($oldDepartment) {
                MasterDepartmentHydrateMasterAssets::dispatch($oldDepartment);
                MasterProductCategoryHydrateMasterFamilies::dispatch($oldDepartment);
            } else {
                MasterShopHydrateMasterFamiliesWithNoDepartment::dispatch($family->masterShop);
                GroupHydrateMasterFamiliesWithNoDepartment::dispatch($family->group);
            }
        }

        if (Arr::has($changes, 'master_sub_department_id')) {
            MasterProductCategoryHydrateMasterFamilies::dispatch($newSubDepartmentId);
            MasterSubDepartmentHydrateMasterAssets::dispatch($newSubDepartmentId);
            if ($oldSubDepartment) {
                MasterProductCategoryHydrateMasterFamilies::dispatch($oldSubDepartment);
                MasterSubDepartmentHydrateMasterAssets::dispatch($oldSubDepartment);
            }
        }

        return $family;
    }


    public function rules(): array
    {
        return [
            'master_sub_department_id' => [
                'required',
                Rule::exists('master_product_categories', 'id')
                    ->where('type', MasterProductCategoryTypeEnum::SUB_DEPARTMENT)
                    ->where('master_shop_id', $this->masterShop->id)
            ]
        ];
    }

    public function action(MasterProductCategory $family, array $modelData): MasterProductCategory
    {
        $this->asAction = true;
        $this->masterShop = $family->masterShop;
        $this->initialisation($family->group, $modelData);

        return $this->handle($family, $this->validatedData);
    }


}
