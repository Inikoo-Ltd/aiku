<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:12 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order\UI;

use App\Actions\OrgAction;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Http\Resources\Sales\OrderResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Ordering\Order;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;

class IndexOrdersExcessPayment extends OrgAction
{
    protected function getElementGroups(Shop|Customer|CustomerClient $parent): array
    {
        return [
            'state' => [
                'label'    => __('State'),
                'elements' => array_merge_recursive(
                    OrderStateEnum::labels(),
                    OrderStateEnum::count($parent)
                ),

                'engine' => function ($query, $elements) {
                    $query->whereIn('orders.state', $elements);
                }
            ],


        ];
    }

    public function handle(Shop|Customer|CustomerClient $parent, $prefix = null, ): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereWith('orders.reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }


        $query = QueryBuilder::for(Order::class);

        if (class_basename($parent) == 'Shop') {
            $query->where('orders.shop_id', $parent->id);
        } elseif (class_basename($parent) == 'Customer') {
            $query->where('orders.customer_id', $parent->id);
        } else {
            $query->where('orders.customer_client_id', $parent->id);
        }

        $query->leftJoin('customers', 'orders.customer_id', '=', 'customers.id');
        $query->leftJoin('customer_clients', 'orders.customer_client_id', '=', 'customer_clients.id');
        $query->leftJoin('currencies', 'orders.currency_id', '=', 'currencies.id');
        $query->leftJoin('organisations', 'orders.organisation_id', '=', 'organisations.id');
        $query->leftJoin('shops', 'orders.shop_id', '=', 'shops.id');

        $query->whereColumn('orders.payment_amount', '>', 'orders.total_amount');
        // foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
        //     $query->whereElementGroup(
        //         key: $key,
        //         allowedElements: array_keys($elementGroup['elements']),
        //         engine: $elementGroup['engine'],
        //         prefix: $prefix
        //     );
        // }
        


        return $query->defaultSort('-orders.date')
        ->select([
            'orders.id',
            'orders.slug',
            'orders.reference',
            'orders.date',
            'orders.state',
            'orders.created_at',
            'orders.updated_at',
            'orders.slug',
            'orders.net_amount',
            'orders.total_amount',
            'orders.payment_amount',
            'customers.name as customer_name',
            'customers.slug as customer_slug',
            'customer_clients.name as client_name',
            'customer_clients.ulid as client_ulid',
            //'payments.state as payment_state',
            //'payments.status as payment_status',
            'currencies.code as currency_code',
            'currencies.id as currency_id',
            'shops.name as shop_name',
            'shops.slug as shop_slug',
            'organisations.name as organisation_name',
            'organisations.slug as organisation_slug',
            'customers.slug as customer_slug',
            'customers.name as customer_name',
        ])
            ->leftJoin('order_stats', 'orders.id', 'order_stats.order_id')
            ->allowedSorts(['id', 'reference', 'date', 'payment_amount', 'total_amount']) // Ensure `id` is the first sort column
            ->withBetweenDates(['date'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Shop|Customer|CustomerClient $parent, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            if ($prefix) {
                InertiaTable::updateQueryBuilderParameters($prefix);
            }


            $noResults = __("No orders found");
            if ($parent instanceof Customer) {
                $stats     = $parent->stats;
                $noResults = __("Customer has no orders");
            } elseif ($parent instanceof CustomerClient) {
                $stats     = $parent->stats;
                $noResults = __("This customer client hasn't place any orders");
            } else {
                $stats = $parent->orderingStats;
            }

            $table->betweenDates(['date']);

            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title' => $noResults,
                        'count' => $stats->number_orders ?? 0
                    ]
                );

                // foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
                //     $table->elementGroup(
                //         key: $key,
                //         label: $elementGroup['label'],
                //         elements: $elementGroup['elements']
                //     );
                // }
            

            $table->column(key: 'state', label: '', canBeHidden: false, searchable: true, type: 'icon');
            $table->column(key: 'reference', label: __('reference'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'date', label: __('Created date'), canBeHidden: false, sortable: true, searchable: true, type: 'date');
            if ($parent instanceof Shop) {
                $table->column(key: 'customer_name', label: __('customer'), canBeHidden: false, searchable: true);
            }
            $table->column(key: 'payment_status', label: __('payment'), canBeHidden: false, searchable: true);
            $table->column(key: 'payment_amount', label: __('paid amount'), canBeHidden: false, searchable: true, type: 'currency');
            $table->column(key: 'total_amount', label: __('total amount'), canBeHidden: false, searchable: true, type: 'currency');
        };
    }

    public function jsonResponse(LengthAwarePaginator $orders): AnonymousResourceCollection
    {
        return OrderResource::collection($orders);
    }
}
