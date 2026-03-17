<?php

namespace App\Actions\Comms\Mailshot\Filters;

use Illuminate\Support\Arr;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

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

            $query->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('customer_stats')
                    ->whereColumn('customer_stats.customer_id', 'customers.id');

                $q->where('customer_stats.number_orders_handing_type_collection', '>', 0);
            });
        }

        return $query;
    }
}
