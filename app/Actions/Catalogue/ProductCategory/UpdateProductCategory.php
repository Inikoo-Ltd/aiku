<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Fri, 21 Oct 2022 08:31:09 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory;

use App\Actions\Catalogue\ProductCategory\Search\ProductCategoryRecordSearch;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\UI\WithImageCatalogue;
use Illuminate\Validation\Rules\File;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Http\Resources\Catalogue\DepartmentsResource;
use App\Http\Resources\Catalogue\FamilyResource;
use App\Http\Resources\Catalogue\SubDepartmentResource;
use App\Models\Catalogue\ProductCategory;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateProductCategory extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithImageCatalogue;
    use WithProductCategoryHydrators;

    private ProductCategory $productCategory;

    public function handle(ProductCategory $productCategory, array $modelData): ProductCategory
    {
        if (Arr::has($modelData, 'department_id')) {
            $departmentId = Arr::pull($modelData, 'department_id');
            if ($productCategory->type == ProductCategoryTypeEnum::FAMILY) {
                $productCategory = UpdateFamilyDepartment::make()->action($productCategory, [
                    'department_id' => $departmentId,
                ]);
            }
        }

        if (Arr::has($modelData, 'sub_department_id')) {
            $subDepartmentId = Arr::pull($modelData, 'sub_department_id');
            if ($productCategory->type == ProductCategoryTypeEnum::FAMILY) {
                $productCategory = UpdateFamilySubDepartment::make()->action($productCategory, [
                    'sub_department_id' => $subDepartmentId,
                ]);
            }
        }


        $imageData = ['image' => Arr::pull($modelData, 'image')];
        if ($imageData['image']) {
            $this->processCatalogueImage($imageData, $productCategory);
        }
        $originalMasterProductCategory = null;
        if (Arr::has($modelData, 'master_product_category_id')) {
            $originalMasterProductCategory = $productCategory->masterProductCategory;
        }


        $productCategory = $this->update($productCategory, $modelData, ['data']);
        $changes         = $productCategory->getChanges();

        if (Arr::hasAny($changes, ['code', 'name', 'type'])) {
            ProductCategoryRecordSearch::dispatch($productCategory);
        }

        if (Arr::has($changes, 'state')) {
            $this->productCategoryHydrators($productCategory);
        }

        if (Arr::has($changes, 'image_id')) {
            UpdateProductCategoryImages::run($productCategory);
        }

        if (Arr::hasAny($changes, ['type', 'state', 'master_product_category_id'])) {
            $this->masterProductCategoryUsageHydrators($productCategory, $productCategory->masterProductCategory);
            if ($originalMasterProductCategory != null && $originalMasterProductCategory->id != $productCategory->master_product_category_id) {
                $this->masterProductCategoryUsageHydrators($productCategory, $originalMasterProductCategory);
            }
        }

        if (Arr::hasAny($changes, [
            'code',
            'name',
            'type',
            'state'
        ])) {
            $this->productCategoryHydrators($productCategory);
        }
        $productCategory->refresh();

        return $productCategory;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("products.{$this->shop->id}.edit");
    }


    public function rules(): array
    {
        $rules = [
            'code'              => [
                'sometimes',
                $this->strict ? 'max:32' : 'max:255',
                new AlphaDashDot(),
                new IUnique(
                    table: 'product_categories',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],
                        ['column' => 'deleted_at', 'operator' => 'notNull'],
                        ['column' => 'type', 'value' => $this->productCategory->type, 'operator' => '='],
                        ['column' => 'id', 'value' => $this->productCategory->id, 'operator' => '!=']

                    ]
                ),
            ],
            'name'              => ['sometimes', 'max:250', 'string'],
            'image_id'          => ['sometimes', 'required', Rule::exists('media', 'id')->where('group_id', $this->organisation->group_id)],
            'state'             => ['sometimes', 'required', Rule::enum(ProductCategoryStateEnum::class)],
            'description'       => ['sometimes', 'required', 'max:65500'],
            'department_id'     => [
                'sometimes',
                Rule::exists('product_categories', 'id')
                    ->where('type', ProductCategoryTypeEnum::DEPARTMENT)
                    ->where('shop_id', $this->shop->id)
            ],
            'sub_department_id' => [
                'sometimes',
                Rule::exists('product_categories', 'id')
                    ->where('type', ProductCategoryTypeEnum::SUB_DEPARTMENT)
                    ->where('shop_id', $this->shop->id)
            ],

            'follow_master' => ['sometimes', 'boolean'],
            'image'         => [
                'sometimes',
                'nullable',
                File::image()
                    ->max(12 * 1024)
            ],
            'webpage_id'    => ['sometimes', 'integer', 'nullable', Rule::exists('webpages', 'id')->where('shop_id', $this->shop->id)],
            'url'           => ['sometimes', 'nullable', 'string', 'max:250'],
            'images'        => ['sometimes', 'array'],

        ];

        if (!$this->strict) {
            $rules['source_department_id'] = ['sometimes', 'string', 'max:255'];
            $rules['source_family_id']     = ['sometimes', 'string', 'max:255'];
            $rules                         = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }


    public function action(ProductCategory $productCategory, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): ProductCategory
    {
        if (!$audit) {
            ProductCategory::disableAuditing();
        }
        $this->asAction        = true;
        $this->productCategory = $productCategory;
        $this->hydratorsDelay  = $hydratorsDelay;
        $this->strict          = $strict;
        $this->initialisationFromShop($productCategory->shop, $modelData);

        return $this->handle($productCategory, $this->validatedData);
    }

    public function asController(ProductCategory $productCategory, ActionRequest $request): ProductCategory
    {
        $this->productCategory = $productCategory;

        $this->initialisationFromShop($productCategory->shop, $request);

        return $this->handle($productCategory, $this->validatedData);
    }


    public function jsonResponse(ProductCategory $productCategory): DepartmentsResource|SubDepartmentResource|FamilyResource
    {
        if ($productCategory->type == ProductCategoryTypeEnum::DEPARTMENT) {
            return new DepartmentsResource($productCategory);
        } elseif ($productCategory->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
            return new SubDepartmentResource($productCategory);
        } else {
            return new FamilyResource($productCategory);
        }
    }
}
