<?php

namespace App\Actions\Comms\Mailshot\Filters;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class FilterRegisteredNeverOrdered
{
    /**
     * Apply the "Registered Never Ordered" filter to the query.
     *
     * @param Builder|QueryBuilder $query
     * @param array $options
     * @return Builder|QueryBuilder
     */
    public function apply($query, array $filters): Builder
    {

        $regNeverOrdered = Arr::get($filters, 'registered_never_ordered');
        $isRegNeverOrderedActive = is_array($regNeverOrdered) ? ($regNeverOrdered['value'] ?? false) : $regNeverOrdered;

        if ($isRegNeverOrderedActive) {
            $options = [];

            if (is_array($regNeverOrdered) && isset($regNeverOrdered['value'])) {
                $val = $regNeverOrdered['value'];

                if (is_array($val) && isset($val['date_range'])) {
                    $rawDateRange = $val['date_range'];

                    if (is_array($rawDateRange) && count($rawDateRange) >= 2) {
                        $options['date_range'] = [
                            'start' => $rawDateRange[0],
                            'end'   => $rawDateRange[1]
                        ];
                    }
                }
            }

            // Use raw subquery to check for customers who have no orders
            $query->whereNotExists(function ($subQuery) {
                $subQuery->select(DB::raw(1))
                    ->from('orders')
                    ->whereRaw('orders.customer_id = customers.id')
                    ->whereNull('orders.deleted_at');
            });

            if ($dateRange = Arr::get($options, 'date_range')) {
                $startDate = Arr::get($dateRange, 'start');
                $endDate   = Arr::get($dateRange, 'end');

                if ($startDate) {
                    $query->whereDate('customers.created_at', '>=', $startDate);
                }

                if ($endDate) {
                    $query->whereDate('customers.created_at', '<=', $endDate);
                }
            }
        }

        return $query;
    }
}
