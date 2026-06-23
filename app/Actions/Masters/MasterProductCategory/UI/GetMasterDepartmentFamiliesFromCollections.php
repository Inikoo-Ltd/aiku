<?php

/*
 * Author: Andiferdiawan <andiferdiawan@gmail.com>
 * Created: 2026
 */

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Http\Resources\Masters\MasterFamiliesResource;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetMasterDepartmentFamiliesFromCollections
{
    use AsObject;

    public function handle(MasterProductCategory $masterDepartment): array
    {
        $collectionIds = $masterDepartment->masterCollections()->pluck('master_collections.id');

        $familyIds = DB::table('master_collection_has_models')
            ->whereIn('master_collection_id', $collectionIds)
            ->where('model_type', 'MasterProductCategory')
            ->pluck('model_id')
            ->unique()
            ->values();

        $existingOrderings = DB::table('master_department_family_orderings')
            ->where('master_department_id', $masterDepartment->id)
            ->whereIn('master_family_id', $familyIds)
            ->pluck('position', 'master_family_id');

        $this->syncNewFamiliesToPivot($masterDepartment->id, $familyIds, $existingOrderings);

        $orderings = DB::table('master_department_family_orderings')
            ->where('master_department_id', $masterDepartment->id)
            ->whereIn('master_family_id', $familyIds)
            ->orderBy('position')
            ->pluck('position', 'master_family_id');

        $families = $this->queryFamilies($familyIds);

        $sorted = $families->sortBy(fn ($f) => $orderings[$f->id] ?? PHP_INT_MAX)->values();

        return [
            'data'                        => MasterFamiliesResource::collection($sorted),
            'editable'                    => true,
            'sync_payload_key'            => 'family_position_map',
            'route_sync_related_products' => [
                'name'       => 'grp.models.master_product_category.department_family_ordering.sync',
                'parameters' => ['masterProductCategory' => $masterDepartment->id],
            ],
        ];
    }

    private function syncNewFamiliesToPivot(int $departmentId, Collection $familyIds, Collection $existingOrderings): void
    {
        $newFamilyIds = $familyIds->diff($existingOrderings->keys());

        if ($newFamilyIds->isEmpty()) {
            return;
        }

        $nextPosition = $existingOrderings->isEmpty()
            ? 1
            : DB::table('master_department_family_orderings')
                ->where('master_department_id', $departmentId)
                ->max('position') + 1;

        $now  = now();
        $rows = $newFamilyIds->map(function ($id) use ($departmentId, &$nextPosition, $now) {
            return [
                'master_department_id' => $departmentId,
                'master_family_id'     => $id,
                'position'             => $nextPosition++,
                'created_at'           => $now,
                'updated_at'           => $now,
            ];
        })->values()->all();

        DB::table('master_department_family_orderings')->insert($rows);
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
