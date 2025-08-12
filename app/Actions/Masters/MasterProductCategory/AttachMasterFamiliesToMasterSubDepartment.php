<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 11 Aug 2025 16:08:50 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory;

use App\Actions\Masters\MasterProductCategory\Hydrators\MasterDepartmentHydrateMasterAssets;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterProductCategoryHydrateMasterFamilies;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterSubDepartmentHydrateMasterAssets;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use App\Http\Resources\Catalogue\MasterProductCategoryResource;
use App\Models\Masters\MasterProductCategory;

class AttachMasterFamiliesToMasterSubDepartment extends OrgAction
{
    use WithActionUpdate;

    /**
     * @var \App\Models\Masters\MasterProductCategory
     */
    private MasterProductCategory $masterSubDepartment;

    public function handle(MasterProductCategory $masterSubDepartment, array $modelData): MasterProductCategory
    {
        $masterDepartmentsToHydrate    = [];
        $masterSubDepartmentsToHydrate = [];

        $masterDepartmentsToHydrate[$masterSubDepartment->id]    = $masterSubDepartment->id;
        $masterSubDepartmentsToHydrate[$masterSubDepartment->id] = $masterSubDepartment->id;

        foreach ($modelData['master_families_id'] as $masterFamilyID) {
            $masterFamily = MasterProductCategory::find($masterFamilyID);
            if ($masterFamily->master_department_id) {
                $masterDepartmentsToHydrate[$masterFamily->master_department_id] = $masterFamily->master_department_id;
            }
            if ($masterFamily->master_sub_department_id) {
                $masterSubDepartmentsToHydrate[$masterFamily->master_sub_department_id] = $masterFamily->master_sub_department_id;
            }

            $masterFamily->update([
                'master_parent_id'         => $masterSubDepartment->id,
                'master_department_id'     => $masterSubDepartment->master_department_id,
                'master_sub_department_id' => $masterSubDepartment->id,
            ]);

            DB::table('master_assets')->where('master_family_id', $masterFamily->id)->update([
                'department_id'     => $masterSubDepartment->master_department_id,
                'sub_department_id' => $masterSubDepartment->id,
            ]);
        }

        foreach ($masterDepartmentsToHydrate as $masterDepartmentID) {
            $masterDepartment = MasterProductCategory::find($masterDepartmentID);
            MasterDepartmentHydrateMasterAssets::dispatch($masterDepartment);
            MasterProductCategoryHydrateMasterFamilies::dispatch($masterDepartment);
        }

        foreach ($masterSubDepartmentsToHydrate as $masterSubDepartmentsToHydrateID) {
            $masterSubDepartment = MasterProductCategory::find($masterSubDepartmentsToHydrateID);
            MasterProductCategoryHydrateMasterFamilies::dispatch($masterSubDepartment);
            MasterSubDepartmentHydrateMasterAssets::dispatch($masterSubDepartment);
        }


        return $masterSubDepartment;
    }

    public function rules(): array
    {
        return [
            'master_families_id'   => ['required', 'array'],
            'master_families_id.*' => [
                'integer',
                Rule::exists('master_product_categories', 'id')->where(function ($query) {
                    $query->where('master_shop_id', $this->masterSubDepartment->master_shop_id);
                }),
            ],
        ];
    }

    public function asController(MasterProductCategory $masterSubDepartment, ActionRequest $request): MasterProductCategory
    {
        $this->masterSubDepartment = $masterSubDepartment;

        $this->initialisationFromGroup($masterSubDepartment->group, $request);

        return $this->handle($masterSubDepartment, $this->validatedData);
    }

    public function jsonResponse(MasterProductCategory $masterSubDepartment): MasterProductCategoryResource
    {
        return new MasterProductCategoryResource($masterSubDepartment);
    }

    public function action(MasterProductCategory $masterSubDepartment, array $familiesToAttach): MasterProductCategory
    {
        $this->masterSubDepartment = $masterSubDepartment;
        $this->asAction            = true;
        $this->initialisationFromGroup($masterSubDepartment->group, $familiesToAttach);

        return $this->handle($masterSubDepartment, $this->validatedData);
    }
}
