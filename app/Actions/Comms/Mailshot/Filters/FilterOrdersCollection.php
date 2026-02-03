<?php

namespace App\Actions\Comms\Mailshot\Filters;

use Illuminate\Support\Arr;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\QueryBuilder as SpatieQueryBuilder;

class FilterOrdersCollection
{
    /**
     * Apply the "Orders Collection" filter to the query.
     *
     * @param Builder|SpatieQueryBuilder $query
     * @param array $filters
     * @return Builder|SpatieQueryBuilder
     */
    public function apply($query, array $filters)
    {
        $collectionFilter = Arr::get($filters, 'orders_collection');
        $isCollectionActive = is_array($collectionFilter) ? ($collectionFilter['value'] ?? false) : $collectionFilter;

        if ($isCollectionActive) {

            $query->whereHas('stats', function (Builder $q) {
                $q->where('number_orders_handing_type_collection', '>', 0);
            });
        }

        return $query;
    }
}
