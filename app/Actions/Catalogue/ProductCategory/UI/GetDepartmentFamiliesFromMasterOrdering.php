<?php

/*
 * Author: Andiferdiawan <andiferdiawan@gmail.com>
 * Created: 2026
 */

namespace App\Actions\Catalogue\ProductCategory\UI;

use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Http\Resources\Masters\MasterFamiliesResource;
use App\Models\Catalogue\ProductCategory;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetDepartmentFamiliesFromMasterOrdering
{
    use AsObject;

    public function handle(ProductCategory $department): array
    {
        $masterDepartment = $department->masterProductCategory;

        if (!$masterDepartment) {
            return ['data' => MasterFamiliesResource::collection(collect()), 'editable' => false];
        }

        $orderings = DB::table('master_department_family_orderings')
            ->where('master_department_id', $masterDepartment->id)
            ->orderBy('position')
            ->pluck('position', 'master_family_id');

        if ($orderings->isEmpty()) {
            return ['data' => MasterFamiliesResource::collection(collect()), 'editable' => false];
        }

        $families = $this->queryFamilies($orderings->keys());

        $sorted = $families->sortBy(fn ($f) => $orderings[$f->id] ?? PHP_INT_MAX)->values();

        return [
            'data'     => MasterFamiliesResource::collection($sorted),
            'editable' => false,
        ];
    }

    private function queryFamilies(Collection $familyIds): Collection
    {
        return MasterProductCategory::query()
            ->whereIn('master_product_categories.id', $familyIds)
            ->where('master_product_categories.type', MasterProductCategoryTypeEnum::FAMILY)
            ->leftJoin('master_product_category_stats', 'master_product_categories.id', '=', 'master_product_category_stats.master_product_category_id')
            ->leftJoin('master_product_categories as departments', 'departments.id', '=', 'master_product_categories.master_department_id')
            ->leftJoin('master_product_categories as sub_departments', 'sub_departments.id', '=', 'master_product_categories.master_sub_department_id')
            ->leftJoin('master_shops', 'master_shops.id', '=', 'master_product_categories.master_shop_id')
            ->leftJoin('groups', 'master_shops.group_id', '=', 'groups.id')
            ->leftJoin('currencies', 'groups.currency_id', '=', 'currencies.id')
            ->select([
                'master_product_categories.id',
                'master_product_categories.slug',
                'master_product_categories.code',
                'master_product_categories.name',
                'master_product_categories.status',
                'master_product_categories.web_images',
                'master_product_categories.mismatch_detected',
                'master_product_categories.health_rank',
                'master_product_category_stats.number_current_families as used_in',
                'master_product_category_stats.number_current_master_assets as products',
                'master_shops.slug as master_shop_slug',
                'master_shops.code as master_shop_code',
                'master_shops.name as master_shop_name',
                'departments.slug as master_department_slug',
                'departments.code as master_department_code',
                'departments.name as master_department_name',
                'sub_departments.slug as master_sub_department_slug',
                'sub_departments.code as master_sub_department_code',
                'sub_departments.name as master_sub_department_name',
                'currencies.code as currency_code',
            ])
            ->get();
    }
}
