<?php

namespace App\Actions\Comms\Mailshot\Filters;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\QueryBuilder as SpatieQueryBuilder;

class FilterOrdersCollection
{
    /**
     * Apply the "Orders Collection" filter to the query.
     *
     * @param Builder|SpatieQueryBuilder $query
     * @param bool $active
     * @return Builder|SpatieQueryBuilder
     */
    public function apply($query, $active = true)
    {
        if (! $active) {
            return $query;
        }

        $query->whereHas('stats', function (Builder $q) {
            $q->where('number_orders_handing_type_collection', '>', 0);
        });

        return $query;
    }
}
