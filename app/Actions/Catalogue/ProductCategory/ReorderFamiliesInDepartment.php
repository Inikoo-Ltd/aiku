<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueEditAuthorisation;
use App\Actions\Web\Webpage\BreakWebpageCache;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class ReorderFamiliesInDepartment extends OrgAction
{
    use WithCatalogueEditAuthorisation;

    private ProductCategory $department;

    /**
     * Hand picks the order of the families of a shop department. The families left out of the list
     * go back to having no hand picked position, so the website falls back to latest arrivals for
     * them. Refused while the shop follows the master order, otherwise the next cascade from master
     * would silently undo the work.
     *
     * @param array{families: array<int, int>} $modelData
     */
    public function handle(ProductCategory $department, array $modelData): ProductCategory
    {
        if (data_get($department->shop->settings, 'catalog.family_order_follow_master', true)) {
            abort(403, 'This shop follows the master family order, turn that off in the shop settings first');
        }

        $familyIds = array_values(array_unique(Arr::get($modelData, 'families', [])));

        DB::transaction(function () use ($department, $familyIds) {
            ProductCategory::where('department_id', $department->id)
                ->where('shop_id', $department->shop_id)
                ->where('type', ProductCategoryTypeEnum::FAMILY)
                ->whereNotIn('id', $familyIds)
                ->whereNotNull('website_position')
                ->update(['website_position' => null]);

            foreach ($familyIds as $index => $familyId) {
                ProductCategory::where('id', $familyId)
                    ->update(['website_position' => $index + 1]);
            }
        });

        BreakWebpageCache::make()->breakProductCategoryWebpagesCache($department);

        foreach ($department->getSubDepartments() as $subDepartment) {
            BreakWebpageCache::make()->breakProductCategoryWebpagesCache($subDepartment);
        }

        return $department;
    }

    public function rules(): array
    {
        return [
            'families'   => ['required', 'array'],
            'families.*' => [
                'integer',
                Rule::exists('product_categories', 'id')
                    ->where('department_id', $this->department->id)
                    ->where('shop_id', $this->department->shop_id)
                    ->where('type', ProductCategoryTypeEnum::FAMILY->value),
            ],
        ];
    }

    public function asController(ProductCategory $productCategory, ActionRequest $request): ProductCategory
    {
        $this->department = $productCategory;
        $this->initialisationFromShop($productCategory->shop, $request);

        return $this->handle($productCategory, $this->validatedData);
    }

    public function action(ProductCategory $department, array $modelData): ProductCategory
    {
        $this->department = $department;
        $this->asAction   = true;
        $this->initialisationFromShop($department->shop, $modelData);

        return $this->handle($department, $this->validatedData);
    }
}
