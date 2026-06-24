<?php

/*
 * Author Louis Perez
 * Co-Author: Andiferdiawan <andiferdiawan@gmail.com>
 * Created on 24-06-2026-15h-12m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Catalogue\ProductCategory\IndexFamiliesOrderingUnderDepartment;

use App\Actions\Catalogue\ProductCategory\Json\GetFamiliesUnderDepartmentPage;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Http\Resources\Masters\RelatedMasterProductsCategoriesResource;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetIndexFamiliesOrderingUnderDepartment
{
    use AsObject;

    public function getIndexFamiliesOrderingCombined(ProductCategory $department): Collection
    {
        if ($department->type !== ProductCategoryTypeEnum::DEPARTMENT) {
            abort(404);
        }

        $currentOrdering = DB::table('department_has_family')
            ->leftJoin('product_categories', 'department_has_family.family_id', 'product_categories.id')
            ->where('department_has_family.department_id', $department->id)
            ->whereNotNull('product_categories.id')
            ->orderBy('department_has_family.position')
            ->select([
                'product_categories.id',
                'product_categories.slug',
                'product_categories.code',
                'product_categories.name',
                'product_categories.web_images',
                DB::raw('department_has_family.position as position'),
            ])->get();

        $familiesDisplayedOnWebpage = GetFamiliesUnderDepartmentPage::make()->getBaseQuery($department, [
                'product_categories.id',
                'product_categories.slug',
                'product_categories.code',
                'product_categories.name',
                'product_categories.web_images',
            ])
            ->whereNotIn('product_categories.id', $currentOrdering->pluck('id'))
            ->get();

        $mergedIndexFamiliesOrdering = $currentOrdering->values();
        $existingFamiliesId = $currentOrdering->pluck('id')->all();
        $nextPosition = (int) ($currentOrdering->max('position') ?? 0);

        foreach ($familiesDisplayedOnWebpage as $familyDisplayedOnWebpage) {
            if (\in_array($familyDisplayedOnWebpage->id, $existingFamiliesId, true)) {
                continue;
            }

            $nextPosition++;
            $familyDisplayedOnWebpage->position = $nextPosition;
            $mergedIndexFamiliesOrdering->push($familyDisplayedOnWebpage);
        }
        
        return $mergedIndexFamiliesOrdering;
    }

    public function handle(ProductCategory $department): array
    {
        return [
            'data'     => RelatedMasterProductsCategoriesResource::collection($this->getIndexFamiliesOrderingCombined($department)),
            'editable' => true,
            'sync_payload_key' => 'families_id',
            'route_sync_index_families_ordering' => [
                'name'       => 'grp.models.product_category.index_families_ordering.sync',
                'parameters' => [
                    'productCategory' => $department->id
                ]
            ],
        ];
    }
}
