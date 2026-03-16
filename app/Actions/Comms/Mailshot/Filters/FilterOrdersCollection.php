<?php

namespace App\Actions\Comms\Mailshot\Filters;

use Illuminate\Support\Arr;
use Illuminate\Database\Query\Builder;

class FilterOrdersCollection
{
    /**
     * Apply the "Orders Collection" filter to the query.
     *
     */
    public function apply(Builder $query, array $filters): Builder
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
