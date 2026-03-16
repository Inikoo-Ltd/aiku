<?php

namespace App\Actions\Comms\Mailshot\Filters;

use Illuminate\Support\Arr;
use Illuminate\Database\Query\Builder;

class FilterByOrderValue
{
    /**
     * Apply the "By Order Value" filter.
     *
     */
    public function apply(Builder $query, array $filters): Builder
    {
        $orderValueFilter = Arr::get($filters, 'by_order_value');
        $isOrderValueActive = is_array($orderValueFilter) ? ($orderValueFilter['value'] ?? false) : $orderValueFilter;

        if ($isOrderValueActive) {
            $options = [];

            if (is_array($orderValueFilter) && isset($orderValueFilter['value'])) {
                $val = $orderValueFilter['value'];

                if (isset($val['amount_range']) && is_array($val['amount_range'])) {
                    $options['min'] = $val['amount_range']['min'] ?? null;
                    $options['max'] = $val['amount_range']['max'] ?? null;
                }
            }

            $min = Arr::get($options, 'min');
            $max = Arr::get($options, 'max');

            if (($min === null || $min === '') && ($max === null || $max === '')) {
                return $query;
            }

            $query->whereHas('orders', function ($q) use ($min, $max) {
                if ($min !== null && $min !== '') {
                    $q->where('org_net_amount', '>=', $min);
                }

                if ($max !== null && $max !== '') {
                    $q->where('org_net_amount', '<=', $max);
                }
            });
        }

        return $query;
    }
}
