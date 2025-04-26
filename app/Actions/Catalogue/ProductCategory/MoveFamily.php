<?php

/*
 * author Arya Permana - Kirin
 * created on 11-10-2024-13h-22m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Catalogue\ProductCategory;

use App\Actions\OrgAction;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class MoveFamily extends OrgAction
{
    public function handle(ProductCategory $family, $modelData): ProductCategory
    {
        if (Arr::exists($modelData, 'department_id')) {
            data_set($modelData, 'sub_department_id', null);
            UpdateProductCategory::make()->action($family, $modelData);
        } elseif (Arr::exists($modelData, 'sub_department_id')) {
            data_set($modelData, 'department_id', null);
            UpdateProductCategory::make()->action($family, $modelData);
        }

        return $family;
    }

    public function rules()
    {
        return [
            'department_id' => ['sometimes', 'exists:product_categories,id'],
            'sub_department_id' => ['sometimes', 'exists:product_categories,id']
        ];
    }

    public function asController(ProductCategory $family, ActionRequest $request)
    {
        $this->initialisationFromShop($family->shop, $request);

        return $this->handle($family, $this->validatedData);
    }
}
