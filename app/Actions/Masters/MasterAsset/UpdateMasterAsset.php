<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 29 Dec 2024 02:37:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset;

use App\Actions\Masters\MasterProductCategory\Hydrators\MasterDepartmentHydrateMasterAssets;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterFamilyHydrateMasterAssets;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateMasterAssets;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateMasterAssets;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class UpdateMasterAsset extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithMastersEditAuthorisation;


    private MasterAsset $masterAsset;

    public function handle(MasterAsset $masterAsset, array $modelData): MasterAsset
    {

        if (Arr::has($modelData, 'master_family_id')) {
            $masterDepartmentID = null;
            $masterFamily = null;
            if ($modelData['master_family_id']) {
                $masterFamily = MasterProductCategory::where('id', $modelData['master_family_id'])->first();
            }

            if ($masterFamily) {
                $masterDepartmentID = $masterFamily->master_department_id;
            }
            data_set($modelData, 'master_department_id', $masterDepartmentID);
        }

        $masterAsset = $this->update($masterAsset, $modelData);
        if ($masterAsset->wasChanged('status')) {
            GroupHydrateMasterAssets::dispatch($masterAsset->group)->delay($this->hydratorsDelay);
            MasterShopHydrateMasterAssets::dispatch($masterAsset->masterShop)->delay($this->hydratorsDelay);
            if ($masterAsset->masterdepartment) {
                MasterDepartmentHydrateMasterAssets::dispatch($masterAsset->masterDepartment)->delay($this->hydratorsDelay);
            }
            if ($masterAsset->masterFamily) {
                MasterFamilyHydrateMasterAssets::dispatch($masterAsset->masterFamily)->delay($this->hydratorsDelay);
            }
        }

        return $masterAsset;
    }

    public function rules(): array
    {
        $rules = [
            'code'             => [
                'sometimes',
                'required',
                'max:32',
                new AlphaDashDot(),
                new IUnique(
                    table: 'master_assets',
                    extraConditions: [
                        ['column' => 'group_id', 'value' => $this->group->id],
                        ['column' => 'deleted_at', 'operator' => 'notNull'],
                        ['column' => 'id', 'value' => $this->masterAsset->id, 'operator' => '!=']
                    ]
                ),
            ],
            'name'             => ['sometimes', 'required', 'max:250', 'string'],
            'price'            => ['sometimes', 'required', 'numeric', 'min:0'],
            'description'      => ['sometimes', 'required', 'max:1500'],
            'rrp'              => ['sometimes', 'required', 'numeric'],
            'data'             => ['sometimes', 'array'],
            'status'           => ['sometimes', 'required', 'boolean'],
            'master_family_id' => [
                'sometimes',
                'nullable',
                Rule::exists('master_product_categories', 'id')
                    ->where('master_shop_id', $this->masterAsset->master_shop_id)
                    ->where('type', MasterProductCategoryTypeEnum::FAMILY)
            ],
        ];

        if (!$this->strict) {
            $rules = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }

    /**
     * @throws \Throwable
     */
    public function action(MasterAsset $masterAsset, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): MasterAsset
    {
        $this->strict = $strict;
        if (!$audit) {
            MasterAsset::disableAuditing();
        }

        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->masterAsset    = $masterAsset;

        $this->initialisationFromGroup($masterAsset->group, $modelData);

        return $this->handle($masterAsset, $this->validatedData);
    }

}
