<?php

namespace App\Actions\Comms\Mailshot\Filters;

use Illuminate\Support\Arr;
use Spatie\QueryBuilder\QueryBuilder as SpatieQueryBuilder;
use Illuminate\Database\Eloquent\Builder;

class FilterByOrderValue
{
    /**
     * Apply the "By Order Value" filter.
     *
     * @param SpatieQueryBuilder|Builder $query
     * @param array $options
     * @return mixed
     */
    public function apply($query, array $options = [])
    {
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

        return $query;
    }
}
