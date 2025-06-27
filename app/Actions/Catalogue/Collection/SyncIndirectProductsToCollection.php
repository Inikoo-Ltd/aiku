<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Jun 2025 22:55:37 Malaysia Time, Sheffield, United Kingdom
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Collection;

use App\Models\Catalogue\Collection;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncIndirectProductsToCollection implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(Collection $collection): string
    {
        return $collection->id;
    }

    public function handle(Collection $collection): void
    {
        $directProductIDs   = $collection->products()->where('type', 'direct')->pluck('products.id')->all();
        $indirectProductIDs = $collection->products()->where('type', 'indirect')->pluck('products.id')->all();
        $familyIDs          = $collection->families->pluck('id');
        $productsInFamilies = DB::table('products')->whereIn('family_id', $familyIDs)->pluck('id')->all();

        $filtered = array_diff($productsInFamilies, $directProductIDs);
        $toAdd    = array_diff($filtered, $indirectProductIDs);
        $toRemove = array_diff($indirectProductIDs, $filtered);

        if ($toRemove) {
            DB::table('collection_has_models')
                ->where('collection_id', $collection->id)
                ->where('type', 'indirect')
                ->where('model_type', 'Product')
                ->whereIn('model_id', $toRemove)
                ->delete();
        }

        if ($toAdd) {
            $collection->products()->attach($toAdd, ['type' => 'indirect']);
        }
    }
}
