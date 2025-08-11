<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 11 Aug 2025 16:09:39 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory;

use App\Actions\GrpAction;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterDepartmentHydrateMasterFamilies;
use App\Actions\Traits\WithActionUpdate;
use App\Http\Resources\Catalogue\MasterProductCategoryResource;
use Lorisleiva\Actions\ActionRequest;
use App\Models\Masters\MasterProductCategory;

class DetachFamilyToMasterSubDepartment extends GrpAction
{
    use WithActionUpdate;

    public function handle(MasterProductCategory $family): MasterProductCategory
    {
        $currentSubDepartment = $family->subDepartment;

        $family->update(
            [
                'sub_department_id' => null,
                'department_id'     => $currentSubDepartment->department_id,
                'parent_id'         => $currentSubDepartment->department_id,
            ]
        );

        MasterDepartmentHydrateMasterFamilies::dispatch($currentSubDepartment);

        return $family;
    }

    public function asController(MasterProductCategory $masterSubDepartment, MasterProductCategory $family, ActionRequest $request): void
    {
        $this->initialisationFromShop($family->shop, $request);

        $this->handle($family);
    }

    public function jsonResponse(MasterProductCategory $family): MasterProductCategoryResource
    {
        return new MasterProductCategoryResource($family);
    }
}
