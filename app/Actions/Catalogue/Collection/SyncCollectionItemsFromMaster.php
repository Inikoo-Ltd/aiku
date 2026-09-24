<?php

/*
 * Author Louis Perez
 * Created on 24-09-2026-13h-23m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Catalogue\Collection;

use App\Actions\Catalogue\Collection\Hydrators\CollectionHydrateFamilies;
use App\Actions\Catalogue\Collection\Hydrators\CollectionHydrateProducts;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/*
 * Makes the families and direct products of a shop collection mirror its master
 * collection: whatever the shop attached on its own is removed, and every shop
 * family / product whose master is in the master collection is attached.
 * Nested collections are left alone.
 */
class SyncCollectionItemsFromMaster
{
    use AsAction;

    public function handle(Collection $collection): Collection
    {
        $masterCollection = $collection->masterCollection;
        if (!$masterCollection) {
            return $collection;
        }

        $familyIDs = ProductCategory::where('shop_id', $collection->shop_id)
            ->whereIn('master_product_category_id', $masterCollection->masterFamilies()->pluck('master_product_categories.id'))
            ->pluck('id')
            ->all();

        $collection->families()->sync($familyIDs);

        $productIDs = Product::where('shop_id', $collection->shop_id)
            ->whereIn('master_product_id', $masterCollection->masterProducts()->pluck('master_assets.id'))
            ->pluck('id')
            ->all();

        $directProductIDs   = $collection->products()->wherePivot('type', 'direct')->pluck('products.id')->all();
        $indirectProductIDs = $collection->products()->wherePivot('type', 'indirect')->pluck('products.id')->all();

        $toDetach = array_diff($directProductIDs, $productIDs);
        if ($toDetach) {
            $collection->products()->detach($toDetach);
        }

        // Already there through a family, but the master has it directly
        $toPromote = array_intersect($productIDs, $indirectProductIDs);
        if ($toPromote) {
            DB::table('collection_has_models')
                ->where('collection_id', $collection->id)
                ->where('model_type', 'Product')
                ->whereIn('model_id', $toPromote)
                ->update(['type' => 'direct']);
        }

        $toAttach = array_diff($productIDs, $directProductIDs, $indirectProductIDs);
        if ($toAttach) {
            $collection->products()->attach($toAttach, ['type' => 'direct']);
        }

        SyncIndirectProductsToCollection::run($collection);

        CollectionHydrateFamilies::dispatch($collection);
        CollectionHydrateProducts::dispatch($collection);

        return $collection;
    }
}
