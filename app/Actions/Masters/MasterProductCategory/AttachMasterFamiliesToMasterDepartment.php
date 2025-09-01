<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 12 Aug 2025 15:09:41 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory;

use App\Actions\Masters\UpdateMasterFamilyMasterDepartment;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class AttachMasterFamiliesToMasterDepartment extends OrgAction
{
    use WithActionUpdate;
    use WithMastersEditAuthorisation;

    /**
     * @var \App\Models\Masters\MasterProductCategory
     */
    private MasterProductCategory $masterDepartment;

    public function handle(MasterProductCategory $masterDepartment, array $modelData): void
    {
        foreach ($modelData['master_families'] as $masterFamilyId) {
            $masterFamily = MasterProductCategory::find($masterFamilyId);
            UpdateMasterFamilyMasterDepartment::make()->action($masterFamily, [
                'master_department_id' => $masterDepartment->id
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'master_families' => ['required', 'array'],
            'master_families.*' => [
                'required',
                Rule::exists('master_product_categories', 'id')->where(function ($query) {
                    $query->where('master_shop_id', $this->masterDepartment->master_shop_id);
                }),
            ],
        ];
    }

    public function asController(MasterProductCategory $masterDepartment, ActionRequest $request): void
    {
        $this->masterDepartment = $masterDepartment;
        $this->initialisationFromGroup($masterDepartment->group, $request);
        $this->handle($masterDepartment, $this->validatedData);
    }

    public function action(MasterProductCategory $masterDepartment, array $familiesToAttach): void
    {
        $this->masterDepartment = $masterDepartment;
        $this->asAction = true;
        $this->initialisationFromGroup($masterDepartment->group, $familiesToAttach);
        $this->handle($masterDepartment, $this->validatedData);
    }
}
