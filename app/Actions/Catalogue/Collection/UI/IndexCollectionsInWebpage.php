<?php

/*
 * author Arya Permana - Kirin
 * created on 10-06-2025-08h-15m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\Collection\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Collection;
use App\Models\Web\Webpage;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class IndexCollectionsInWebpage extends OrgAction
{
    public function handle(Webpage $webpage, $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Collection::class);
        $queryBuilder->leftjoin('collection_stats', 'collections.id', 'collection_stats.collection_id');
        $queryBuilder->leftjoin('webpage_has_collections', 'collections.id', 'webpage_has_collections.collection_id');
        $queryBuilder->where('webpage_has_collections.webpage_id', $webpage->id);
        $queryBuilder
            ->defaultSort('collections.code')
            ->select([
                'collections.id',
                'collections.code',
                'collections.name',
                'collections.description',
                'collections.created_at',
                'collections.updated_at',
                'collections.slug',
                'collection_stats.number_departments',
                'collection_stats.number_families',
                'collection_stats.number_products',
                'collection_stats.number_collections'
            ]);

        return $queryBuilder
            ->allowedSorts(['code', 'name'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }
}
