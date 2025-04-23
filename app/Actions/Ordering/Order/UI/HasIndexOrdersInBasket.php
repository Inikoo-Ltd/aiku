<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Apr 2025 20:19:46 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order\UI;

use App\InertiaTable\InertiaTable;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;

trait HasIndexOrdersInBasket
{
    public function tableStructure(Group|Organisation $model, $prefix = null): \Closure
    {
        return function (InertiaTable $table) use ($model, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            if ($prefix) {
                InertiaTable::updateQueryBuilderParameters($prefix);
            }

            $noResults = __("No orders found");
            $table->betweenDates(['date']);

            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title' => $noResults,
                        'count' => $model->orderingStats->number_orders ?? 0
                    ]
                );


            if ($model instanceof Group) {
                $table->column(key: 'organisation_code', label: __('Org'), canBeHidden: false, searchable: true);
            }

            $table->column(key: 'shop_code', label: __('shop'), canBeHidden: false, searchable: true);
            $table->column(key: 'reference', label: __('reference'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'created_at', label: __('Created'), canBeHidden: false, sortable: true, searchable: true, type: 'date');
            $table->column(key: 'updated_by_customer_at', label: __('Updated'), tooltip: __('Last updated by customer at'), canBeHidden: false, sortable: true, searchable: true, type: 'date');
            $table->column(key: 'customer_name', label: __('customer'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'net_amount', label: __('net'), canBeHidden: false, searchable: true, type: 'currency');
        };
    }

}
