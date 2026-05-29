<?php

/*
 * author Louis Perez
 * created on 29-05-2026-11h-11m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Catalogue\ProductCategory\RelatedChild\RelatedProductCategories;

use App\Actions\OrgAction;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class SyncProductCategoryRelatedProductCategories extends OrgAction
{
    public function handle(ProductCategory $productCategory, array $modelData): ProductCategory
    {
        $productCategoriesId = array_unique(Arr::get($modelData, 'product_categories_id', []));

        $relatedProductCategories = [];
        $position        = 0;
        foreach ($productCategoriesId as $productCategoryId) {
            $position++;
            $relatedProductCategories[$productCategoryId] = [
                'position' => $position
            ];
        }


        $productCategory->relatedProductCategories()->sync($relatedProductCategories);

        return $productCategory;
    }

    public function rules(): array
    {
        return [
            'product_categories_id'   => ['sometimes', 'array'],
            'product_categories_id.*' => [
                'integer',
                Rule::exists('product_categories', 'id')->where('shop_id', $this->shop->id)
            ],
        ];
    }

    public function action(ProductCategory $productCategory, array $modelData): ProductCategory
    {
        $this->asAction = true;
        $this->initialisationFromShop($productCategory->shop, $modelData);

        return $this->handle($productCategory, $this->validatedData);
    }

    public function asController(ProductCategory $productCategory, ActionRequest $request): ProductCategory
    {
        $this->initialisationFromShop($productCategory->shop, $request);

        return $this->handle($productCategory, $this->validatedData);
    }
}
