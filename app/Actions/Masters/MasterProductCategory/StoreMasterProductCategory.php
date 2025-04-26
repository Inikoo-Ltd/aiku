<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Dec 2024 16:21:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory;

use App\Actions\GrpAction;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterDepartmentHydrateMasterFamilies;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateMasterDepartments;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateMasterFamilies;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreMasterProductCategory extends GrpAction
{
    use WithNoStrictRules;

    public function handle(MasterProductCategory|MasterShop $parent, array $modelData): MasterProductCategory
    {
        data_set($modelData, 'group_id', $parent->group_id);
        if ($parent instanceof MasterProductCategory) {
            data_set($modelData, 'master_department_id', $parent->id);
            data_set($modelData, 'master_shop_id', $parent->master_shop_id);
            data_set($modelData, 'master_parent_id', $parent->id);

            if ($parent->type == ProductCategoryTypeEnum::DEPARTMENT) {
                data_set($modelData, 'master_department_id', $parent->id);
            } elseif ($parent->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
                data_set($modelData, 'master_sub_department_id', $parent->id);
            }
        } else {
            data_set($modelData, 'master_shop_id', $parent->id);
        }

        /** @var MasterProductCategory $masterProductCategory */
        $masterProductCategory = MasterProductCategory::create($modelData);

        $masterProductCategory->stats()->create();
        $masterProductCategory->orderingIntervals()->create();
        $masterProductCategory->orderingStats()->create();
        $masterProductCategory->salesIntervals()->create();
        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            $masterProductCategory->timeSeries()->create(['frequency' => $frequency]);
        }
        $masterProductCategory->refresh();

        if ($masterProductCategory->type == MasterProductCategoryTypeEnum::DEPARTMENT) {
            MasterShopHydrateMasterDepartments::dispatch($masterProductCategory->masterShop)->delay($this->hydratorsDelay);
        } elseif ($masterProductCategory->type == MasterProductCategoryTypeEnum::FAMILY) {
            MasterShopHydrateMasterFamilies::dispatch($masterProductCategory->masterShop)->delay($this->hydratorsDelay);
            if ($masterProductCategory->department) {
                MasterDepartmentHydrateMasterFamilies::dispatch($masterProductCategory->department)->delay($this->hydratorsDelay);
            }
        }

        return $masterProductCategory;
    }

    public function rules(): array
    {
        $rules = [
            'type'        => ['required', Rule::enum(MasterProductCategoryTypeEnum::class)],
            'code'        => [
                'required',
                $this->strict ? 'max:32' : 'max:255',
                new AlphaDashDot(),
                new IUnique(
                    table: 'master_product_categories',
                    extraConditions: [
                        ['column' => 'group_id', 'value' => $this->group->id],
                        ['column' => 'deleted_at', 'operator' => 'notNull'],
                    ]
                ),
            ],
            'name'        => ['required', 'max:250', 'string'],
            'image_id'    => ['sometimes', 'required', Rule::exists('media', 'id')->where('group_id', $this->group->id)],
            'status'      => [
                'sometimes',
                'required',
                'boolean',
            ],
            'description' => ['sometimes', 'required', 'max:1500'],
        ];

        if (!$this->strict) {
            $rules['source_family_id']     = ['sometimes', 'required', 'max:32', 'string'];
            $rules['source_department_id'] = ['sometimes', 'required', 'max:32', 'string'];
            $rules                         = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return false;
    }

    public function action(MasterShop|MasterProductCategory $parent, array $modelData, int $hydratorsDelay = 0, bool $strict = true): MasterProductCategory
    {
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->strict         = $strict;
        $group                = $parent->group;

        $this->initialisation($group, $modelData);

        return $this->handle($parent, $this->validatedData);
    }

    public function asController(MasterShop $masterShop, ActionRequest $request): MasterProductCategory
    {
        $this->initialisation($masterShop->group, $request);

        return $this->handle($masterShop, $this->validatedData);
    }

    public function inDepartment(MasterShop $masterShop, MasterProductCategory $masterProductCategory, ActionRequest $request): MasterProductCategory
    {
        $this->initialisation($masterShop->group, $request);

        return $this->handle(parent: $masterProductCategory, modelData: $this->validatedData);
    }


}
