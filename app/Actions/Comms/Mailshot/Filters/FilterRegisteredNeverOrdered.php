<?php

namespace App\Actions\Comms\Mailshot\Filters;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\QueryBuilder;
use Illuminate\Support\Arr;

class FilterRegisteredNeverOrdered
{
    /**
     * Apply the "Registered Never Ordered" filter to the query.
     *
     * @param Builder|QueryBuilder $query
     * @param array $options
     * @return Builder|QueryBuilder
     */
    public function apply($query, array $options): Builder|QueryBuilder
    {
        $query->whereDoesntHave('orders');

        if ($dateRange = Arr::get($options, 'date_range')) {
            $startDate = Arr::get($dateRange, 'start');
            $endDate   = Arr::get($dateRange, 'end');

            if ($startDate) {
                $query->whereDate('created_at', '>=', $startDate);
            }

            if ($endDate) {
                $query->whereDate('created_at', '<=', $endDate);
            }
        }

        return $query;
    }
}
