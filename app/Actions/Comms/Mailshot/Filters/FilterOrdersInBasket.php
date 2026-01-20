<?php

namespace App\Actions\Comms\Mailshot\Filters;

use Illuminate\Support\Arr;
use Illuminate\Database\Eloquent\Builder;
use App\Enums\Ordering\Order\OrderStateEnum;
use Spatie\QueryBuilder\QueryBuilder as SpatieQueryBuilder;

class FilterOrdersInBasket
{
    /**
     * Apply the "Orders In Basket" filter.
     *
     * @param Builder|SpatieQueryBuilder $query
     * @param array $options
     * @return Builder|SpatieQueryBuilder
     */
    public function apply($query, array $options = [])
    {

        $query->whereHas('orders', function ($q) use ($options) {
            $q->where('state', OrderStateEnum::CREATING);
            if ($dateRange = Arr::get($options, 'date_range')) {
                $startDate = Arr::get($dateRange, 'start');
                $endDate   = Arr::get($dateRange, 'end');

                if ($startDate) {
                    $q->whereDate('updated_at', '>=', $startDate);
                }
                if ($endDate) {
                    $q->whereDate('updated_at', '<=', $endDate);
                }
            }

            if ($amountRange = Arr::get($options, 'amount_range')) {
                $min = Arr::get($amountRange, 'min');
                $max = Arr::get($amountRange, 'max');

                if ($min !== null && $min !== '') {
                    $q->where('org_net_amount', '>=', $min);
                }
                if ($max !== null && $max !== '') {
                    $q->where('org_net_amount', '<=', $max);
                }
            }
        });

        return $query;
    }
}
