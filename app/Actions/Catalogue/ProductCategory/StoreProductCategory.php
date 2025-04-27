<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Fri, 21 Oct 2022 08:27:33 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory;

use App\Actions\Catalogue\ProductCategory\Search\ProductCategoryRecordSearch;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreProductCategory extends OrgAction
{
    use WithNoStrictRules;
    use WithProductCategoryHydrators;

    /**
     * @throws \Throwable
     */
    public function handle(Shop|ProductCategory $parent, array $modelData): ProductCategory
    {
        data_set($modelData, 'group_id', $parent->group_id);
        data_set($modelData, 'organisation_id', $parent->organisation_id);

        if (class_basename($parent) == 'ProductCategory') {
            data_set($modelData, 'shop_id', $parent->shop_id);
            data_set($modelData, 'department_id', $parent->id);
            data_set($modelData, 'parent_id', $parent->id);

            if ($parent->type == ProductCategoryTypeEnum::DEPARTMENT) {
                data_set($modelData, 'department_id', $parent->id);
            } elseif ($parent->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
                data_set($modelData, 'sub_department_id', $parent->id);
            }
        } else {
            $modelData['shop_id'] = $parent->id;
        }

        $productCategory = DB::transaction(function () use ($modelData) {
            /** @var ProductCategory $productCategory */
            $productCategory = ProductCategory::create($modelData);

            $productCategory->stats()->create();
            $productCategory->orderingIntervals()->create();
            $productCategory->salesIntervals()->create();
            $productCategory->orderingStats()->create();
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                $productCategory->timeSeries()->create(['frequency' => $frequency]);
            }

            $productCategory->refresh();

            return $productCategory;
        });

        ProductCategoryRecordSearch::dispatch($productCategory);
        $this->productCategoryHydrators($productCategory);
        $this->masterProductCategoryUsageHydrators($productCategory, $productCategory->masterProductCategory);

        return $productCategory;
    }

    public function rules(): array
    {
        $rules = [
            'type'        => ['required', Rule::enum(ProductCategoryTypeEnum::class)],
            'code'        => [
                'required',
                $this->strict ? 'max:32' : 'max:255',
                new AlphaDashDot(),
                new IUnique(
                    table: 'product_categories',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],
                        ['column' => 'deleted_at', 'operator' => 'notNull'],
                    ]
                ),
            ],
            'name'        => ['required', 'max:250', 'string'],
            'image_id'    => ['sometimes', 'required', Rule::exists('media', 'id')->where('group_id', $this->organisation->group_id)],
            'state'       => ['sometimes', Rule::enum(ProductCategoryStateEnum::class)],
            'description' => ['sometimes', 'required', 'max:1500'],

        ];

        if (!$this->strict) {
            $rules['source_department_id'] = ['sometimes', 'string', 'max:255'];
            $rules['source_family_id']     = ['sometimes', 'string', 'max:255'];
            $rules                         = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }


    /**
     * @throws \Throwable
     */
    public function action(Shop|ProductCategory $parent, array $modelData, int $hydratorsDelay = 0, bool $strict = true, $audit = true): ProductCategory
    {
        if (!$audit) {
            ProductCategory::disableAuditing();
        }
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->strict         = $strict;
        if ($parent instanceof Shop) {
            $shop = $parent;
        } else {
            $shop = $parent->shop;
        }

        $this->initialisationFromShop($shop, $modelData);

        return $this->handle($parent, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): ProductCategory
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $this->validatedData);
    }

    /** @noinspection PhpUnusedParameterInspection */
    /**
     * @throws \Throwable
     */
    public function inDepartment(Organisation $organisation, Shop $shop, ProductCategory $productCategory, ActionRequest $request): ProductCategory
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle(parent: $productCategory, modelData: $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function inSubDepartment(ProductCategory $productCategory, ActionRequest $request): ProductCategory
    {
        $this->initialisationFromShop($productCategory->shop, $request);

        return $this->handle(parent: $productCategory, modelData: $this->validatedData);
    }

    public function htmlResponse(ProductCategory $productCategory, ActionRequest $request): RedirectResponse
    {
        if (class_basename($productCategory->parent) == 'ProductCategory') {
            return Redirect::route('grp.org.shops.show.catalogue.departments.show.families.show', [
                'organisation' => $productCategory->organisation->slug,
                'shop'         => $productCategory->shop->slug,
                'department'   => $productCategory->parent->slug,
                'family'       => $productCategory->slug,
            ]);
        } else {
            return Redirect::route('grp.org.shops.show.catalogue.departments.show', [
                'organisation' => $productCategory->organisation->slug,
                'shop'         => $productCategory->shop->slug,
                'department'   => $productCategory->slug,
            ]);
        }
    }


}
