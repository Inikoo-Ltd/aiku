<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Dec 2024 21:46:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory;

use App\Actions\OrgAction;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;


class UpdateMasterSubDepartmentsMasterDepartment extends OrgAction
{

    public function handle(MasterProductCategory $masterProductCategory, array $modelData): bool
    {
        $updatedCount     = 0;
        $subDepartmentIds = Arr::pull($modelData, 'master_sub_department_ids');

        foreach ($subDepartmentIds as $subDepartmentId) {
            $subDepartment = MasterProductCategory::find($subDepartmentId);
            UpdateMasterSubDepartmentMasterDepartment::make()->action($subDepartment, [
                'master_department_id' => $masterProductCategory->id,
            ]);
            $updatedCount++;
        }


        return (bool)$updatedCount;
    }


    public function rules(): array
    {
        return [
            'master_sub_department_ids' => ['required', 'array'],
        ];
    }


    /**
     * Controller entry point.
     *
     * @param  MasterProductCategory  $masterProductCategory  parent Master Department (from route parameter)
     *                                                     To assign to each `master_sub_department_ids`.
     * @param  ActionRequest  $request  must include `master_sub_department_ids` (array).
     */
    public function asController(MasterProductCategory $masterProductCategory, ActionRequest $request): bool
    {
        $this->initialisationFromGroup($masterProductCategory->group, $request);

        return $this->handle($masterProductCategory, $this->validatedData);
    }

}
