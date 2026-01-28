<?php

namespace App\Actions\Comms\Mailshot\Filters;

use Illuminate\Support\Arr;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\QueryBuilder as SpatieQueryBuilder;

class FilterByShowroomOrders
{
    /**
     * Apply the "By Showroom Orders" filter to the query.
     *
     * @param Builder|SpatieQueryBuilder $query
     * @param array $filters
     * @return Builder|SpatieQueryBuilder
     */
    public function apply($query, array $filters)
    {
        $showroomFilter = Arr::get($filters, 'by_showroom_orders');
        $isShowroomActive = is_array($showroomFilter) ? ($showroomFilter['value'] ?? false) : $showroomFilter;

        if ($isShowroomActive) {

            $query->whereHas('stats', function (Builder $q) {
                $q->where('number_orders_sales_channel_type_showroom', '>', 0);
            });
        }

        return $query;
    }
}
