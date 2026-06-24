<?php

/*
 * Author Louis Perez
 * Co-Author: Andiferdiawan <andiferdiawan@gmail.com>
 * Created on 24-06-2026-15h-12m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Catalogue\ProductCategory\IndexFamiliesOrderingUnderDepartment;

use App\Actions\GrpAction;
use App\Actions\OrgAction;
use App\Actions\Web\Webpage\BreakWebpageCache;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class SyncDepartmentFamilyOrdering extends OrgAction
{
    public function handle(ProductCategory $department, array $modelData): ProductCategory
    {
        if ($department->type !== ProductCategoryTypeEnum::DEPARTMENT) {
            abort(404);
        }

        $familyIds = array_unique(Arr::get($modelData, 'families_id', []));

        $relatedFamilies = [];
        $position        = 0;
        foreach ($familyIds as $familyId) {
            $position++;
            $relatedFamilies[$familyId] = [
                'position' => $position
            ];
        }


        $department->indexFamiliesOrdering()->sync($relatedFamilies);

        if ($department->webpage) {
            BreakWebpageCache::run($department->webpage);
        }

        return $department;
    }

    public function rules(): array
    {
        return [
            'families_id'   => ['sometimes', 'array'],
            'families_id.*' => [
                'integer',
                Rule::exists('product_categories', 'id')->where('shop_id', $this->shop->id)
            ],
        ];
    }

    public function asController(ProductCategory $productCategory, ActionRequest $request): ProductCategory
    {
        $this->initialisationFromShop($productCategory->shop, $request);

        return $this->handle($productCategory, $this->validatedData);
    }
}
