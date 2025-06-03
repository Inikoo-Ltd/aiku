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
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
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
        $imageData = ['image' => Arr::pull($modelData, 'image')];
        if ($imageData['image']) {
            $this->processCatalogue($imageData, $productCategory);
        }
        $originalMasterProductCategory = null;
        if (Arr::has($modelData, 'master_product_category_id')) {
            $originalMasterProductCategory = $productCategory->masterProductCategory;
        }

        $productCategory = $this->update($productCategory, $modelData, ['data']);
        $changes         = $productCategory->getChanges();

        ProductCategoryRecordSearch::dispatch($productCategory);

        if (Arr::has($changes, 'state')) {
            $this->productCategoryHydrators($productCategory);
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
            'description'       => ['sometimes', 'required', 'max:1500'],
            'department_id'     => ['sometimes', 'nullable', 'exists:product_categories,id'],
            'sub_department_id' => ['sometimes', 'nullable', 'exists:product_categories,id'],
            'follow_master'     => ['sometimes', 'boolean'],
            'image'       => [
                'sometimes',
                'nullable',
                File::image()
                    ->max(12 * 1024)
            ],

        ];

        if (!$this->strict) {
            $rules['source_department_id'] = ['sometimes', 'string', 'max:255'];
            $rules['source_family_id']     = ['sometimes', 'string', 'max:255'];
            $rules                         = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        if ($this->productCategory->type == ProductCategoryTypeEnum::DEPARTMENT) {
            $this->set('department_id', null);
        }
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

    public function asController(Organisation $organisation, Shop $shop, ProductCategory $productCategory, ActionRequest $request): ProductCategory
    {
        $this->productCategory = $productCategory;

        $this->initialisationFromShop($shop, $request);

        return $this->handle($productCategory, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function inSubDepartment(ProductCategory $productCategory, ActionRequest $request): ProductCategory
    {
        $this->productCategory = $productCategory;
        $this->initialisationFromShop($productCategory->shop, $request);

        return $this->handle($productCategory, modelData: $this->validatedData);
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
