<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 22-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Catalogue\ProductCategory;

use App\Actions\Catalogue\ProductCategory\Hydrators\DepartmentHydrateProducts;
use App\Actions\Catalogue\ProductCategory\Hydrators\ProductCategoryHydrateFamilies;
use App\Actions\Catalogue\ProductCategory\Hydrators\SubDepartmentHydrateProducts;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use App\Http\Resources\Catalogue\SubDepartmentResource;
use App\Models\Catalogue\ProductCategory;

class AttachFamiliesToSubDepartment extends OrgAction
{
    use WithActionUpdate;

    public function handle(ProductCategory $subDepartment, array $modelData): ProductCategory
    {

        $departmentsToHydrate = [];
        $subDepartmentsToHydrate = [];

        $departmentsToHydrate[$subDepartment->department_id] = $subDepartment->department_id;
        $subDepartmentsToHydrate[$subDepartment->id] = $subDepartment->id;


        foreach ($modelData['families_id'] as $familyID) {
            $family = ProductCategory::find($familyID);
            if ($family->department_id) {
                $departmentsToHydrate[$family->department_id] = $family->department_id;
            }
            if ($family->sub_department_id) {
                $subDepartmentsToHydrate[$family->sub_department_id] = $family->sub_department_id;
            }

            $family->update([
                'sub_department_id' => $subDepartment->id,
                'parent_id' => $subDepartment->id,
                'department_id' => $subDepartment->department_id,
            ]);

            DB::table('products')->where('family_id', $family->id)->update([
                'sub_department_id' => $subDepartment->id,
                'department_id' => $subDepartment->department_id,
            ]);

        }

        foreach ($departmentsToHydrate as $departmentID) {
            $department = ProductCategory::find($departmentID);
            DepartmentHydrateProducts::dispatch($department);
            ProductCategoryHydrateFamilies::dispatch($department);

        }

        foreach ($subDepartmentsToHydrate as $subDepartmentsToHydrateID) {
            $subDepartmentsToHydrateID = ProductCategory::find($subDepartmentsToHydrateID);
            ProductCategoryHydrateFamilies::dispatch($subDepartmentsToHydrateID);
            SubDepartmentHydrateProducts::dispatch($subDepartmentsToHydrateID);
        }


        return $subDepartment;
    }

    public function rules(): array
    {
        return [
            'families_id' => ['required', 'array'],
            'families_id.*' => [
                'integer',
                Rule::exists('product_categories', 'id')->where('shop_id', $this->shop->id),
            ],
        ];
    }

    public function asController(ProductCategory $subDepartment, ActionRequest $request): ProductCategory
    {

        $this->initialisationFromShop($subDepartment->shop, $request);

        return $this->handle($subDepartment, $this->validatedData);
    }


    public function jsonResponse(ProductCategory $subDepartment): SubDepartmentResource
    {
        return new SubDepartmentResource($subDepartment);
    }
}
