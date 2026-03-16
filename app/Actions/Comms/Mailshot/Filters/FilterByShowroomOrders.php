<?php

namespace App\Actions\Comms\Mailshot\Filters;

use Illuminate\Support\Arr;
use Illuminate\Database\Query\Builder;

class FilterByShowroomOrders
{
    /**
     * Apply the "By Showroom Orders" filter to the query.
     *
     */
    public function apply(Builder $query, array $filters): Builder
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
