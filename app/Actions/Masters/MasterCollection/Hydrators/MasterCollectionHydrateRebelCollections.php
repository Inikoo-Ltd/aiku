<?php

/*
 * Author Louis Perez
 * Created on 24-09-2026-14h-18m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Masters\MasterCollection\Hydrators;

use App\Models\Masters\MasterCollection;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/*
 * Counts the shop collections of a master that do not follow its items
 * (families and products) or its content (name and descriptions).
 */
class MasterCollectionHydrateRebelCollections implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(MasterCollection $masterCollection): string
    {
        return $masterCollection->id;
    }

    public function handle(MasterCollection $masterCollection): void
    {
        $counts = DB::table('collections')
            ->where('master_collection_id', $masterCollection->id)
            ->whereNull('deleted_at')
            ->selectRaw('count(*) filter (where not_follow_master_items) as rebel_items')
            ->selectRaw('count(*) filter (where not_follow_master_content) as rebel_content')
            ->first();

        $masterCollection->stats()->update([
            'total_collections_rebel_items'   => (int) $counts->rebel_items,
            'total_collections_rebel_content' => (int) $counts->rebel_content,
        ]);
    }
}
