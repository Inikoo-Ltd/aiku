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
    public function apply($query, array $filters): Builder|QueryBuilder
    {
        $regNeverOrdered = Arr::get($filters, 'registered_never_ordered', []);

        if (!empty($regNeverOrdered)) {
            $options = [];

            if (is_array($regNeverOrdered) && isset($regNeverOrdered['date_range'])) {
                $rawDateRange = $regNeverOrdered['date_range'];
                if (is_array($rawDateRange) && count($rawDateRange) == 2) {
                    $options['date_range'] = [
                        'start' => $rawDateRange[0],
                        'end'   => $rawDateRange[1]
                    ];
                }
            }

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
        }

        return $query;
    }
}
