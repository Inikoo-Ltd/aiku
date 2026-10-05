<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory;

use App\Enums\Catalogue\Collection\CollectionStateEnum;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

trait WithFamiliesFromParentCollections
{
    /**
     * Ids of the families held by the active collections attached to a department or sub department,
     * the families the department and sub department pages list besides their own.
     */
    protected function familyIdsFromParentCollections(int $parentId): Builder
    {
        return DB::table('collection_has_models as chm')
            ->select('chm.model_id')
            ->where('chm.model_type', class_basename(ProductCategory::class))
            ->whereIn('chm.collection_id', $this->activeCollectionIdsOfParent($parentId));
    }

    /**
     * Names of the active collections attached to the parent that bring the family in.
     */
    protected function parentCollectionNamesOfFamily(int $parentId, string $familyIdColumn = 'product_categories.id'): Builder
    {
        return DB::table('collection_has_models as named_chm')
            ->join('collections as named_collections', 'named_collections.id', '=', 'named_chm.collection_id')
            ->selectRaw("string_agg(named_collections.name, ', ' order by named_collections.name)")
            ->where('named_chm.model_type', class_basename(ProductCategory::class))
            ->whereColumn('named_chm.model_id', $familyIdColumn)
            ->whereIn('named_chm.collection_id', $this->activeCollectionIdsOfParent($parentId));
    }

    private function activeCollectionIdsOfParent(int $parentId): Builder
    {
        return DB::table('model_has_collections as mhc')
            ->join('collections as parent_collections', 'parent_collections.id', '=', 'mhc.collection_id')
            ->select('mhc.collection_id')
            ->where('mhc.model_type', class_basename(ProductCategory::class))
            ->where('mhc.model_id', $parentId)
            ->where('parent_collections.state', CollectionStateEnum::ACTIVE->value);
    }
}
