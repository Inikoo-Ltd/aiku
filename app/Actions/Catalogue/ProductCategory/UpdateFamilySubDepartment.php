<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Jun 2025 21:43:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory;

use App\Actions\Catalogue\ProductCategory\Hydrators\DepartmentHydrateProducts;
use App\Actions\Catalogue\ProductCategory\Hydrators\ProductCategoryHydrateFamilies;
use App\Actions\Catalogue\ProductCategory\Hydrators\SubDepartmentHydrateProducts;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateFamiliesWithNoDepartment;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateFamiliesWithNoDepartment;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateFamiliesWithNoDepartment;
use App\Actions\Traits\Authorisations\WithCatalogueEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\ProductCategory;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UpdateFamilySubDepartment extends OrgAction
{
    use WithActionUpdate;
    use WithCatalogueEditAuthorisation;

    public function handle(ProductCategory $family, array $modelData): ProductCategory
    {

        $newSubDepartmentId = ProductCategory::find(Arr::get($modelData, 'sub_department_id'));

        $oldDepartment    = $family->department ?? null;
        $oldSubDepartment = $family->subDepartment ?? null;

        data_set($modelData, 'parent_id', $newSubDepartmentId->id);
        data_set($modelData, 'sub_department_id', $newSubDepartmentId->id);
        data_set($modelData, 'department_id', $newSubDepartmentId->department_id);

        $family  = $this->update($family, $modelData);
        $changes = $family->getChanges();
        $family->refresh();
        DB::table('products')
            ->where('family_id', $family->id)
            ->update([
                'department_id'     => $family->department_id,
                'sub_department_id' => $family->sub_department_id,
            ]);


        if (Arr::has($changes, 'department_id')) {
            DepartmentHydrateProducts::dispatch($family->department);
            ProductCategoryHydrateFamilies::dispatch($family->department);
            if ($oldDepartment) {
                DepartmentHydrateProducts::dispatch($oldDepartment);
                ProductCategoryHydrateFamilies::dispatch($oldDepartment);
            } else {
                ShopHydrateFamiliesWithNoDepartment::dispatch($family->shop);
                OrganisationHydrateFamiliesWithNoDepartment::dispatch($family->organisation);
                GroupHydrateFamiliesWithNoDepartment::dispatch($family->group);
            }
        }

        if (Arr::has($changes, 'sub_department_id')) {
            ProductCategoryHydrateFamilies::dispatch($newSubDepartmentId);
            SubDepartmentHydrateProducts::dispatch($newSubDepartmentId);
            if ($oldSubDepartment) {
                ProductCategoryHydrateFamilies::dispatch($oldSubDepartment);
                SubDepartmentHydrateProducts::dispatch($oldSubDepartment);
            }
        }

        return $family;
    }


    public function rules(): array
    {
        return [
            'sub_department_id' => [
                'required',
                Rule::exists('product_categories', 'id')
                    ->where('type', ProductCategoryTypeEnum::SUB_DEPARTMENT)
                    ->where('shop_id', $this->shop->id)
            ]
        ];
    }

    public function action(ProductCategory $family, array $modelData): ProductCategory
    {
        $this->asAction = true;
        $this->initialisationFromShop($family->shop, $modelData);

        return $this->handle($family, $this->validatedData);
    }


}
