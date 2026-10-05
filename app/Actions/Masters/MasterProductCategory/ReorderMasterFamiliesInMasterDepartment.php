<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class ReorderMasterFamiliesInMasterDepartment extends OrgAction
{
    use WithMastersEditAuthorisation;

    private MasterProductCategory $masterDepartment;

    /**
     * Hand picks the order of the master families of a department. The families left out of the
     * list go back to having no hand picked position, so the website falls back to latest arrivals
     * for them.
     *
     * @param array{master_families: array<int, int>} $modelData
     */
    public function handle(MasterProductCategory $masterDepartment, array $modelData): MasterProductCategory
    {
        $masterFamilyIds = array_values(array_unique(Arr::get($modelData, 'master_families', [])));

        DB::transaction(function () use ($masterDepartment, $masterFamilyIds) {
            MasterProductCategory::where('master_department_id', $masterDepartment->id)
                ->where('type', ProductCategoryTypeEnum::FAMILY)
                ->whereNotIn('id', $masterFamilyIds)
                ->whereNotNull('website_position')
                ->update(['website_position' => null]);

            foreach ($masterFamilyIds as $index => $masterFamilyId) {
                MasterProductCategory::where('id', $masterFamilyId)
                    ->update(['website_position' => $index + 1]);
            }
        });

        SyncFamiliesWebsitePositionFromMasterDepartment::run($masterDepartment);

        return $masterDepartment;
    }

    public function rules(): array
    {
        return [
            'master_families'   => ['required', 'array'],
            'master_families.*' => [
                'integer',
                Rule::exists('master_product_categories', 'id')
                    ->where('master_department_id', $this->masterDepartment->id)
                    ->where('type', ProductCategoryTypeEnum::FAMILY->value),
            ],
        ];
    }

    public function asController(MasterProductCategory $masterProductCategory, ActionRequest $request): MasterProductCategory
    {
        $this->masterDepartment = $masterProductCategory;
        $this->initialisationFromGroup($masterProductCategory->group, $request);

        return $this->handle($masterProductCategory, $this->validatedData);
    }

    public function action(MasterProductCategory $masterDepartment, array $modelData): MasterProductCategory
    {
        $this->masterDepartment = $masterDepartment;
        $this->asAction         = true;
        $this->initialisationFromGroup($masterDepartment->group, $modelData);

        return $this->handle($masterDepartment, $this->validatedData);
    }
}
