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
     * @param array $filters
     * @return Builder|SpatieQueryBuilder
     */
    public function apply($query, array $filters)
    {

        $basketFilter = Arr::get($filters, 'orders_in_basket');
        $isBasketActive = is_array($basketFilter) ? ($basketFilter['value'] ?? false) : $basketFilter;


        if ($isBasketActive) {
            $options = [];

            if (is_array($basketFilter) && isset($basketFilter['value'])) {
                $val = $basketFilter['value'];

                if (isset($val['date_range']) && is_array($val['date_range']) && count($val['date_range']) >= 2) {
                    $options['date_range'] = [
                        'start' => $val['date_range'][0],
                        'end'   => $val['date_range'][1]
                    ];
                }

                if (isset($val['amount_range']) && is_array($val['amount_range'])) {
                    $options['amount_range'] = [
                        'min' => $val['amount_range']['min'] ?? null,
                        'max' => $val['amount_range']['max'] ?? null,
                    ];
                }
            }

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
        }

        return $query;
    }
}
