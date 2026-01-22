<?php

namespace App\Actions\Comms\Mailshot\Filters;

use Spatie\QueryBuilder\QueryBuilder;

class FilterByShowroomOrders
{
    public function apply(QueryBuilder $query): void
    {
        $query->whereHas('stats', function ($q) {
            $q->where('number_orders_sales_channel_type_showroom', '>', 0);
        });
    }
}
