<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 11 Aug 2025 16:08:50 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory;

use App\Actions\GrpAction;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterDepartmentHydrateMasterFamilies;
use App\Actions\Traits\WithActionUpdate;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use App\Http\Resources\Catalogue\MasterProductCategoryResource;
use App\Models\Masters\MasterProductCategory;

class AttachFamiliesToMasterSubDepartment extends GrpAction
{
    use WithActionUpdate;

    public function handle(MasterProductCategory $masterSubDepartment, array $modelData): MasterProductCategory
    {
        $departmentsToHydrate = [];
        $subDepartmentsToHydrate = [];

        $departmentsToHydrate[$masterSubDepartment->id] = $masterSubDepartment->id;
        $subDepartmentsToHydrate[$masterSubDepartment->id] = $masterSubDepartment->id;

        foreach ($modelData['families_id'] as $familyID) {
            $family = MasterProductCategory::find($familyID);
            if ($family->master_department_id) {
                $departmentsToHydrate[$family->master_department_id] = $family->master_department_id;
            }
            if ($family->master_sub_department_id) {
                $subDepartmentsToHydrate[$family->master_sub_department_id] = $family->master_sub_department_id;
            }

            $family->update([
                'master_sub_department_id' => $masterSubDepartment->id,
                'master_department_id' => $masterSubDepartment->master_department_id,
            ]);
        }

        foreach ($departmentsToHydrate as $departmentID) {
            $department = MasterProductCategory::find($departmentID);
            MasterDepartmentHydrateMasterFamilies::dispatch($department);
        }

        return $masterSubDepartment;
    }

    public function rules(): array
    {
        return [
            'families_id' => ['required', 'array'],
            'families_id.*' => [
                'integer',
                Rule::exists('master_product_categories', 'id'),
            ],
        ];
    }

    public function asController(MasterProductCategory $masterSubDepartment, ActionRequest $request): MasterProductCategory
    {
        $this->initialisation($masterSubDepartment->group, $request);

        return $this->handle($masterSubDepartment, $this->validatedData);
    }

    public function jsonResponse(MasterProductCategory $masterSubDepartment): MasterProductCategoryResource
    {
        return new MasterProductCategoryResource($masterSubDepartment);
    }

    public function action(MasterProductCategory $masterSubDepartment, array $familiesToAttach): MasterProductCategory
    {
        $this->asAction = true;
        $this->initialisation($masterSubDepartment->group, $familiesToAttach);
        return $this->handle($masterSubDepartment, $this->validatedData);
    }
}
