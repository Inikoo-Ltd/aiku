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
use App\Actions\Web\Webpage\ReindexWebpageLuigiData;
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
        $originalImageId = $productCategory->image_id;


        if (Arr::has($modelData, 'department_id')) {
            $departmentId = Arr::pull($modelData, 'department_id');
            if ($productCategory->type == ProductCategoryTypeEnum::FAMILY) {
                $productCategory = UpdateFamilyDepartment::make()->action($productCategory, [
                    'department_id' => $departmentId,
                ]);
            } elseif ($productCategory->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
                $productCategory = UpdateSubDepartmentDepartment::make()->action($productCategory, [
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

        if (Arr::has($modelData, 'image')) {
            $imageData = ['image' => Arr::pull($modelData, 'image')];
            if ($imageData['image']) {
                $this->processCatalogueImage($imageData, $productCategory);
            } else {
                data_set($modelData, 'image_id', null, false);
            }

        }

        $originalMasterProductCategory = null;
        if (Arr::has($modelData, 'master_product_category_id')) {
            $originalMasterProductCategory = $productCategory->masterProductCategory;
        }

        if (Arr::has($modelData, 'name_i8n')) {
            UpdateProductCategoryTranslationsFromUpdate::make()->action($productCategory, [
                'translations' => [
                    'name' => [$productCategory->shop->language->code => Arr::pull($modelData, 'name_i8n')]
                ]
            ]);
        }

        if (Arr::has($modelData, 'description_title_i8n')) {
            UpdateProductCategoryTranslationsFromUpdate::make()->action($productCategory, [
                'translations' => [
                    'description_title' => [$productCategory->shop->language->code => Arr::pull($modelData, 'description_title_i8n')]
                ]
            ]);
        }

        if (Arr::has($modelData, 'description_i8n')) {
            UpdateProductCategoryTranslationsFromUpdate::make()->action($productCategory, [
                'translations' => [
                    'description' => [$productCategory->shop->language->code => Arr::pull($modelData, 'description_i8n')]
                ]
            ]);
        }

        if (Arr::has($modelData, 'description_extra_i8n')) {
            UpdateProductCategoryTranslationsFromUpdate::make()->action($productCategory, [
                'translations' => [
                    'description_extra' => [$productCategory->shop->language->code => Arr::pull($modelData, 'description_extra_i8n')]
                ]
            ]);
        }


        $productCategory = $this->update($productCategory, $modelData, ['data']);
        $productCategory->refresh();


        if (!$productCategory->image_id && $originalImageId) {
            $productCategory->images()->detach($originalImageId);
        }

        $changes = Arr::except($productCategory->getChanges(), ['updated_at']);

        if (Arr::hasAny($changes, ['code', 'name', 'type'])) {
            ProductCategoryRecordSearch::dispatch($productCategory);
        }

        if (Arr::has($changes, 'state')) {
            $this->productCategoryHydrators($productCategory);
        }

        if (Arr::has($changes, 'image_id')) {
            UpdateProductCategoryWebImages::run($productCategory);
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
            'state',
            'name_i8n'
        ])) {
            $this->productCategoryHydrators($productCategory);
            if ($productCategory->webpage_id) {
                ReindexWebpageLuigiData::dispatch($productCategory->webpage)->delay($this->hydratorsDelay);
            }
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
            'image_id'          => ['sometimes', Rule::exists('media', 'id')->where('group_id', $this->organisation->group_id)],
            'state'             => ['sometimes', 'required', Rule::enum(ProductCategoryStateEnum::class)],
            'description'       => ['sometimes', 'nullable', 'max:65500'],
            'description_title' => ['sometimes', 'nullable', 'max:255'],
            'description_extra' => ['sometimes', 'nullable', 'max:65500'],
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

            'follow_master'              => ['sometimes', 'boolean'],
            'image'                      => [
                'sometimes',
                'nullable',
                File::image()
                    ->max(12 * 1024)
            ],
            'webpage_id'                 => ['sometimes', 'integer', 'nullable', Rule::exists('webpages', 'id')->where('shop_id', $this->shop->id)],
            'url'                        => ['sometimes', 'nullable', 'string', 'max:250'],
            'images'                     => ['sometimes', 'array'],
            'master_product_category_id' => ['sometimes', 'integer', 'nullable', Rule::exists('master_product_categories', 'id')->where('master_shop_id', $this->shop->master_shop_id)],
            'name_i8n' =>               ['sometimes'],
            'description_title_i8n' =>  ['sometimes'],
            'description_i8n' =>        ['sometimes'],
            'description_extra_i8n' =>  ['sometimes'],
            'cost_price_ratio'         => ['sometimes', 'numeric', 'min:0']
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
