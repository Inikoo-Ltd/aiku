<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Dec 2024 21:46:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory;

use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateMasterDepartments;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateMasterFamilies;
use App\Actions\OrgAction;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Models\Masters\MasterProductCategory;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Validation\Rule;

class UpdateMasterProductCategory extends OrgAction
{
    use WithMasterProductCategoryAction;


    public function handle(MasterProductCategory $masterProductCategory, array $modelData): MasterProductCategory
    {
        $masterProductCategory = $this->update($masterProductCategory, $modelData, ['data']);
        if ($masterProductCategory->wasChanged('status')) {
            if ($masterProductCategory->type == MasterProductCategoryTypeEnum::DEPARTMENT) {
                MasterShopHydrateMasterDepartments::dispatch($masterProductCategory->masterShop)->delay($this->hydratorsDelay);
            } elseif ($masterProductCategory->type == MasterProductCategoryTypeEnum::FAMILY) {
                MasterShopHydrateMasterFamilies::dispatch($masterProductCategory->masterShop)->delay($this->hydratorsDelay);
            }
        }

        return $masterProductCategory;
    }


    public function rules(): array
    {
        $rules = [
            'code'                     => [
                'sometimes',
                $this->strict ? 'max:32' : 'max:255',
                new AlphaDashDot(),
                new IUnique(
                    table: 'master_product_categories',
                    extraConditions: [
                        ['column' => 'master_shop_id', 'value' => $this->masterShop->id],
                        ['column' => 'deleted_at', 'operator' => 'notNull'],
                        ['column' => 'type', 'value' => $this->masterProductCategory->type, 'operator' => '='],
                        ['column' => 'id', 'value' => $this->masterProductCategory->id, 'operator' => '!=']

                    ]
                ),
            ],
            'name'                     => ['sometimes', 'max:250', 'string'],
            'image_id'                 => ['sometimes', 'required', Rule::exists('media', 'id')->where('group_id', $this->group->id)],
            'status'                   => ['sometimes', 'required', 'boolean'],
            'description'              => ['sometimes', 'required', 'max:1500'],
            'master_department_id'     => ['sometimes', 'nullable', 'exists:product_categories,id'],
            'master_sub_department_id' => ['sometimes', 'nullable', 'exists:product_categories,id'],
            'show_in_website'          => ['sometimes', 'boolean'],
        ];

        if (!$this->strict) {
            $rules = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }
}
