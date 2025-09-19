<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Dec 2024 16:21:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory;

use App\Actions\Catalogue\ProductCategory\StoreProductCategory;
use App\Actions\GrpAction;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterProductCategoryHydrateMasterFamilies;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateMasterDepartments;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateMasterFamilies;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateMasterSubDepartments;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateMasterProductCategories;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\UI\WithImageCatalogue;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Helpers\SendSlackNotification;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreMasterProductCategory extends GrpAction
{
    use WithNoStrictRules;
    use WithMastersEditAuthorisation;
    use WithImageCatalogue;


    private MasterShop|MasterProductCategory $masterShop;

    /**
     * @throws \Throwable
     */
    public function handle(MasterProductCategory|MasterShop $parent, array $modelData): MasterProductCategory
    {

        $imageData = ['image' => Arr::pull($modelData, 'image')];
        data_set($modelData, 'group_id', $parent->group_id);
        if ($parent instanceof MasterProductCategory) {

            data_set($modelData, 'master_shop_id', $parent->master_shop_id);
            data_set($modelData, 'master_parent_id', $parent->id);

            if ($parent->type == MasterProductCategoryTypeEnum::DEPARTMENT) {
                data_set($modelData, 'master_department_id', $parent->id);
            } elseif ($parent->type == MasterProductCategoryTypeEnum::SUB_DEPARTMENT) {
                data_set($modelData, 'master_department_id', $parent->master_department_id);
                data_set($modelData, 'master_sub_department_id', $parent->id);
            }
        } else {
            data_set($modelData, 'master_shop_id', $parent->id);
        }

        $masterProductCategory = DB::transaction(function () use ($parent, $modelData) {

            $masterProductCategory = MasterProductCategory::create($modelData);

            $masterProductCategory->stats()->create();
            $masterProductCategory->orderingIntervals()->create();
            $masterProductCategory->orderingStats()->create();
            $masterProductCategory->salesIntervals()->create();
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                $masterProductCategory->timeSeries()->create(['frequency' => $frequency]);
            }
            $masterProductCategory->refresh();

            $collection = null;
            if ($masterProductCategory->type != MasterProductCategoryTypeEnum::FAMILY) { //FOR NOW ONLY FAMILY SUPPORT Selection Feature, just ignore this; everything will still work
                if ($parent instanceof MasterShop) {
                    $collection = $parent->shops;
                } elseif ($parent instanceof MasterProductCategory) {
                    $collection = $parent->productCategories;
                }

                if ($collection && $collection->isNotEmpty()) {
                    $type = match (Arr::get($modelData, 'type')) {
                        MasterProductCategoryTypeEnum::DEPARTMENT => ProductCategoryTypeEnum::DEPARTMENT,
                        MasterProductCategoryTypeEnum::FAMILY => ProductCategoryTypeEnum::FAMILY,
                        MasterProductCategoryTypeEnum::SUB_DEPARTMENT => ProductCategoryTypeEnum::SUB_DEPARTMENT,
                    };
                    $actionData = array_merge(
                        Arr::except($modelData, ['status', 'type']),
                        [
                            'type' => $type,
                            'master_product_category_id' => $masterProductCategory->id
                        ]
                    );

                    foreach ($collection as $item) {
                        StoreProductCategory::make()->action($item, $actionData);
                    }
                }
            }

            return $masterProductCategory;
        });

        if ($imageData['image']) {
            $this->processCatalogueImage($imageData, $masterProductCategory);
        }

        if ($masterProductCategory->type == MasterProductCategoryTypeEnum::DEPARTMENT) {
            MasterShopHydrateMasterDepartments::dispatch($masterProductCategory->masterShop)->delay($this->hydratorsDelay);
        } elseif ($masterProductCategory->type == MasterProductCategoryTypeEnum::FAMILY) {
            MasterShopHydrateMasterFamilies::dispatch($masterProductCategory->masterShop)->delay($this->hydratorsDelay);
            if ($masterProductCategory->department) {
                MasterProductCategoryHydrateMasterFamilies::dispatch($masterProductCategory->department)->delay($this->hydratorsDelay);
            }
        } elseif ($masterProductCategory->type == MasterProductCategoryTypeEnum::SUB_DEPARTMENT) {
            MasterShopHydrateMasterSubDepartments::dispatch($masterProductCategory->masterShop)->delay($this->hydratorsDelay);
        }

        GroupHydrateMasterProductCategories::dispatch($masterProductCategory->group)->delay($this->hydratorsDelay);

        SendSlackNotification::dispatch($masterProductCategory);

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
                        ['column' => 'master_shop_id', 'value' => $this->masterShop->id],
                        ['column' => 'deleted_at', 'operator' => 'notNull'],
                    ]
                ),
            ],
            'name'        => ['required', 'max:250', 'string'],
            'image_id'    => ['sometimes', 'required', Rule::exists('media', 'id')->where('group_id', $this->group->id)],
            'image'       => [
                'sometimes',
                'nullable',
                File::image()
                    ->max(12 * 1024)
            ],
            'status'      => [
                'sometimes',
                'required',
                'boolean',
            ],
            'description' => ['sometimes', 'nullable', 'max:65500'],
        ];

        if (!$this->strict) {
            $rules['source_family_id']     = ['sometimes', 'required', 'max:32', 'string'];
            $rules['source_department_id'] = ['sometimes', 'required', 'max:32', 'string'];
            $rules                         = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }

    /**
     * @throws \Throwable
     */
    public function action(MasterShop|MasterProductCategory $parent, array $modelData, int $hydratorsDelay = 0, bool $strict = true): MasterProductCategory
    {
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->strict         = $strict;
        $group                = $parent->group;

        if ($parent instanceof MasterProductCategory) {
            $this->masterShop = $parent->masterShop;
        } else {
            $this->masterShop = $parent;
        }

        $this->initialisation($group, $modelData);

        return $this->handle($parent, $this->validatedData);
    }


}
