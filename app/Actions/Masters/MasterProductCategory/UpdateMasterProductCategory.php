<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Dec 2024 21:46:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory;

use App\Actions\Catalogue\ProductCategory\UpdateProductCategory;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterDepartmentHydrateMasterSubDepartments;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateMasterDepartments;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateMasterFamilies;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateMasterSubDepartments;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateMasterProductCategories;
use App\Actions\Traits\UI\WithImageCatalogue;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Models\Masters\MasterProductCategory;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class UpdateMasterProductCategory extends OrgAction
{
    use WithMasterProductCategoryAction;
    use WithImageCatalogue;


    public function handle(MasterProductCategory $masterProductCategory, array $modelData): MasterProductCategory
    {
        $originalImageId = $masterProductCategory->image_id;
        if (Arr::has($modelData, 'master_department_id')) {
            $departmentId = Arr::pull($modelData, 'master_department_id');
            if ($masterProductCategory->type == MasterProductCategoryTypeEnum::FAMILY) {
                $masterProductCategory = UpdateMasterFamilyMasterDepartment::make()->action($masterProductCategory, [
                    'master_department_id' => $departmentId,
                ]);
            } elseif ($masterProductCategory->type == MasterProductCategoryTypeEnum::SUB_DEPARTMENT) {
                $masterProductCategory = UpdateMasterSubDepartmentMasterDepartment::make()->action($masterProductCategory, [
                    'master_department_id' => $departmentId,
                ]);
            }
        }

        if (Arr::has($modelData, 'master_sub_department_id')) {
            $subDepartmentId = Arr::pull($modelData, 'master_sub_department_id');
            if ($masterProductCategory->type == MasterProductCategoryTypeEnum::FAMILY) {
                $masterProductCategory = UpdateMasterFamilyMasterSubDepartment::make()->action($masterProductCategory, [
                    'master_sub_department_id' => $subDepartmentId,
                ]);
            }
        }

        if (Arr::has($modelData, 'image')) {
            $imageData = ['image' => Arr::pull($modelData, 'image')];
            if ($imageData['image']) {
                $this->processCatalogueImage($imageData, $masterProductCategory);
            } else {
                data_set($modelData, 'image_id', null, false);
            }
        }

        if (Arr::has($modelData, 'name_i8n')) {
            UpdateMasterProductCategoryTranslationsFromUpdate::make()->action($masterProductCategory, [
                'translations' => [
                    'name' => Arr::pull($modelData, 'name_i8n')
                ]
            ]);
        }

        if (Arr::has($modelData, 'description_title_i8n')) {
            UpdateMasterProductCategoryTranslationsFromUpdate::make()->action($masterProductCategory, [
                'translations' => [
                    'description_title' => Arr::pull($modelData, 'description_title_i8n')
                ]
            ]);
        }

        if (Arr::has($modelData, 'description_i8n')) {
            UpdateMasterProductCategoryTranslationsFromUpdate::make()->action($masterProductCategory, [
                'translations' => [
                    'description' => Arr::pull($modelData, 'description_i8n')
                ]
            ]);
        }

        if (Arr::has($modelData, 'description_extra_i8n')) {
            UpdateMasterProductCategoryTranslationsFromUpdate::make()->action($masterProductCategory, [
                'translations' => [
                    'description_extra' => Arr::pull($modelData, 'description_extra_i8n')
                ]
            ]);
        }

        $masterProductCategory = $this->update($masterProductCategory, $modelData, ['data']);

        $changed = Arr::except($masterProductCategory->getChanges(), ['updated_at']);

        if (Arr::hasAny($changed, ['code', 'name', 'description', 'rrp'])) {
            foreach ($masterProductCategory->productCategories as $productCategory) {
                UpdateProductCategory::make()->action($productCategory, [
                    'code' => $masterProductCategory->code,
                    'name' => $masterProductCategory->name,
                    'description' => $masterProductCategory->description,
                    'rrp' => $masterProductCategory->rrp
                ]);
            }
        }

        $masterProductCategory->refresh();

        if (!$masterProductCategory->image_id && $originalImageId) {
            $masterProductCategory->images()->detach($originalImageId);
        }

        if ($masterProductCategory->wasChanged('status')) {
            if ($masterProductCategory->type == MasterProductCategoryTypeEnum::DEPARTMENT) {
                MasterShopHydrateMasterDepartments::dispatch($masterProductCategory->masterShop)->delay($this->hydratorsDelay);
            } elseif ($masterProductCategory->type == MasterProductCategoryTypeEnum::FAMILY) {
                MasterShopHydrateMasterFamilies::dispatch($masterProductCategory->masterShop)->delay($this->hydratorsDelay);
            } elseif ($masterProductCategory->type == MasterProductCategoryTypeEnum::SUB_DEPARTMENT) {
                MasterDepartmentHydrateMasterSubDepartments::dispatch($masterProductCategory->masterDepartment)->delay($this->hydratorsDelay);
                MasterShopHydrateMasterSubDepartments::dispatch($masterProductCategory->masterShop)->delay($this->hydratorsDelay);
            }

            GroupHydrateMasterProductCategories::dispatch($masterProductCategory->group)->delay($this->hydratorsDelay);
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
            'description'      => ['sometimes', 'max:1500'],
            'description_title' => ['sometimes', 'nullable', 'max:255'],
            'description_extra' => ['sometimes', 'nullable', 'max:65500'],
            'master_department_id'     => ['sometimes', 'nullable', 'exists:master_product_categories,id'],
            'master_sub_department_id' => ['sometimes', 'nullable', 'exists:master_product_categories,id'],
            'show_in_website'          => ['sometimes', 'boolean'],
            'image'                      => [
                'sometimes',
                'nullable',
                File::image()
                    ->max(12 * 1024)
            ],
            'name_i8n' => ['sometimes', 'array'],
            'description_title_i8n' => ['sometimes', 'array'],
            'description_i8n' => ['sometimes', 'array'],
            'description_extra_i8n' => ['sometimes', 'array'],
            'cost_price_ratio'         => ['sometimes', 'numeric', 'min:0']
        ];

        if (!$this->strict) {
            $rules = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }
}
