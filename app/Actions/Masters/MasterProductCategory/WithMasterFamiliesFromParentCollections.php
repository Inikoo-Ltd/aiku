<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory;

use App\Enums\Catalogue\MasterCollection\MasterCollectionStateEnum;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

trait WithMasterFamiliesFromParentCollections
{
    /**
     * Ids of the master families held by the active master collections attached to a master department,
     * mirroring the families the shop department pages list from their collections.
     */
    protected function masterFamilyIdsFromParentCollections(int $masterParentId): Builder
    {
        return DB::table('master_collection_has_models as mchm')
            ->select('mchm.model_id')
            ->where('mchm.model_type', class_basename(MasterProductCategory::class))
            ->whereIn('mchm.master_collection_id', $this->activeMasterCollectionIdsOfParent($masterParentId));
    }

    /**
     * Names of the active master collections attached to the master parent that bring the master family in.
     */
    protected function parentMasterCollectionNamesOfFamily(int $masterParentId, string $masterFamilyIdColumn = 'master_product_categories.id'): Builder
    {
        return DB::table('master_collection_has_models as named_mchm')
            ->join('master_collections as named_master_collections', 'named_master_collections.id', '=', 'named_mchm.master_collection_id')
            ->selectRaw("string_agg(named_master_collections.name, ', ' order by named_master_collections.name)")
            ->where('named_mchm.model_type', class_basename(MasterProductCategory::class))
            ->whereColumn('named_mchm.model_id', $masterFamilyIdColumn)
            ->whereIn('named_mchm.master_collection_id', $this->activeMasterCollectionIdsOfParent($masterParentId));
    }

    private function activeMasterCollectionIdsOfParent(int $masterParentId): Builder
    {
        return DB::table('model_has_master_collections as mhmc')
            ->join('master_collections as parent_master_collections', 'parent_master_collections.id', '=', 'mhmc.master_collection_id')
            ->select('mhmc.master_collection_id')
            ->where('mhmc.model_type', class_basename(MasterProductCategory::class))
            ->where('mhmc.model_id', $masterParentId)
            ->where('parent_master_collections.state', MasterCollectionStateEnum::ACTIVE->value);
    }
}
