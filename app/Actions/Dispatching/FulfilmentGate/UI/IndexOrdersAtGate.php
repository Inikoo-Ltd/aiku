<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 30 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\FulfilmentGate\UI;

use App\Actions\Dispatching\FulfilmentGate\GetGateCoverage;
use App\Actions\Dispatching\FulfilmentGate\GetMakeQueue;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithDispatchingAuthorisation;
use App\Actions\UI\Dispatch\ShowDispatchHub;
use App\InertiaTable\InertiaTable;
use App\Models\Inventory\Warehouse;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexOrdersAtGate extends OrgAction
{
    use WithDispatchingAuthorisation;

    public function handle(Organisation $organisation): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('orders.reference', $value)
                    ->orWhereStartWith('customers.name', $value);
            });
        });

        return QueryBuilder::for(Order::class)
            ->leftJoin('customers', 'customers.id', 'orders.customer_id')
            ->leftJoin('shops', 'shops.id', 'orders.shop_id')
            ->leftJoin('sales_channels', 'sales_channels.id', 'orders.sales_channel_id')
            ->leftJoin('currencies', 'currencies.id', 'orders.currency_id')
            ->where('orders.organisation_id', $organisation->id)
            ->whereNotNull('orders.at_gate_at')
            ->select([
                'orders.id',
                'orders.slug',
                'orders.reference',
                'shops.slug as shop_slug',
                'orders.state',
                'orders.pay_status',
                'orders.net_amount',
                'orders.at_gate_at',
                'customers.name as customer_name',
                'sales_channels.code as sales_channel_code',
                'sales_channels.type as sales_channel_type',
                'currencies.code as currency_code',
            ])
            ->defaultSort('at_gate_at')
            ->allowedFilters([$globalSearch])
            ->allowedSorts(['reference', 'customer_name', 'net_amount', 'at_gate_at', 'pay_status'])
            ->withPaginator(null, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function shortfall(Organisation $organisation): LengthAwarePaginator
    {
        $prefix = 'shortfall';
        InertiaTable::updateQueryBuilderParameters($prefix);

        // ponytail: aggregate shortfall vs current availability, same naive model as GetGateCoverage
        $base = DB::table('transactions')
            ->join('orders', 'orders.id', 'transactions.order_id')
            ->join('product_has_org_stocks', 'product_has_org_stocks.product_id', 'transactions.model_id')
            ->join('org_stocks', 'org_stocks.id', 'product_has_org_stocks.org_stock_id')
            ->where('transactions.model_type', 'Product')
            ->whereNull('transactions.deleted_at')
            ->where('orders.organisation_id', $organisation->id)
            ->whereNotNull('orders.at_gate_at')
            ->groupBy('org_stocks.id', 'org_stocks.code', 'org_stocks.name')
            ->havingRaw('sum(coalesce(product_has_org_stocks.quantity, 1) * (transactions.quantity_ordered + transactions.quantity_bonus)) > max(org_stocks.quantity_available)')
            ->select([
                'org_stocks.id as org_stock_id',
                'org_stocks.code as org_stock_code',
                'org_stocks.name as org_stock_name',
                DB::raw('sum(coalesce(product_has_org_stocks.quantity, 1) * (transactions.quantity_ordered + transactions.quantity_bonus)) as quantity_required'),
                DB::raw('max(org_stocks.quantity_available) as quantity_available'),
                DB::raw('greatest(sum(coalesce(product_has_org_stocks.quantity, 1) * (transactions.quantity_ordered + transactions.quantity_bonus)) - max(org_stocks.quantity_available), 0) as quantity_short'),
                DB::raw('count(distinct transactions.order_id) as number_orders'),
                DB::raw('sum(transactions.net_amount) as blocked_amount'),
            ]);

        return QueryBuilder::for(Order::query()->withoutGlobalScopes()->fromSub($base, $prefix))
            ->defaultSort('-blocked_amount')
            ->allowedSorts(['org_stock_code', 'quantity_required', 'quantity_short', 'number_orders', 'blocked_amount'])
            ->withPaginator($prefix, tableName: request()->route()?->getName())
            ->withQueryString();
    }

    public function shortfallTableStructure(): Closure
    {
        return function (InertiaTable $table) {
            $table
                ->name('shortfall')
                ->pageName('shortfallPage')
                ->withLabelRecord([__('Short stock'), __('Short stocks')])
                ->withEmptyState([
                    'title' => __('Nothing is short — all gated demand is coverable'),
                ])
                ->column(key: 'make', label: '', canBeHidden: false)
                ->column(key: 'org_stock_code', label: __('Stock'), canBeHidden: false, sortable: true)
                ->column(key: 'org_stock_name', label: __('Name'), canBeHidden: false)
                ->column(key: 'quantity_required', label: __('Required'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'quantity_available', label: __('Available'), canBeHidden: false, align: 'right')
                ->column(key: 'quantity_short', label: __('Short'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'number_orders', label: __('Orders'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'blocked_amount', label: __('Blocked value'), canBeHidden: false, sortable: true, align: 'right')
                ->defaultSort('-blocked_amount');
        };
    }

    public function makeQueueTableStructure(): Closure
    {
        return function (InertiaTable $table) {
            $table
                ->name('make_queue')
                ->pageName('makeQueuePage')
                ->withLabelRecord([__('Suggestion'), __('Suggestions')])
                ->withEmptyState([
                    'title' => __('Nothing to make — demand is covered and stock cover is healthy'),
                ])
                ->column(key: 'make', label: '', canBeHidden: false)
                ->column(key: 'org_stock_code', label: __('Stock'), canBeHidden: false, sortable: true)
                ->column(key: 'org_stock_name', label: __('Name'), canBeHidden: false)
                ->column(key: 'quantity_available', label: __('Available'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'days_cover', label: __('Cover (days)'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'suggested_quantity', label: __('Make'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'reasons', label: __('Why'), canBeHidden: false)
                ->column(key: 'score', label: __('Score'), canBeHidden: false, sortable: true, align: 'right')
                ->defaultSort('-score');
        };
    }

    public function tableStructure(): Closure
    {
        return function (InertiaTable $table) {
            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('Order at gate'), __('Orders at gate')])
                ->withEmptyState([
                    'title' => __('No orders waiting at the gate'),
                ])
                ->column(key: 'reference', label: __('Order'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'customer_name', label: __('Customer'), canBeHidden: false, sortable: true)
                ->column(key: 'coverage', label: __('Coverage'), canBeHidden: false)
                ->column(key: 'net_amount', label: __('Net'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'at_gate_at', label: __('At gate since'), canBeHidden: false, sortable: true)
                ->column(key: 'release', label: '', canBeHidden: false)
                ->defaultSort('at_gate_at');
        };
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->warehouse = $warehouse;
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($organisation);
    }

    public function htmlResponse(LengthAwarePaginator $orders, ActionRequest $request): Response
    {
        $coverage = GetGateCoverage::make()->handle($orders->getCollection()->pluck('id')->all());

        $makeQueue = GetMakeQueue::make()->handle($this->organisation);
        $currency  = $this->organisation->currency->code;
        $makeQueue->getCollection()->transform(function ($row) use ($currency) {
            $reasons = [];
            if ($row->blocked_paid_amount > 0) {
                $reasons[] = __('blocks :amount of paid orders', ['amount' => $currency.' '.number_format((float) $row->blocked_paid_amount, 2)]);
            }
            if ($row->shortfall_quantity > 0) {
                $reasons[] = __(':quantity short for orders at the gate', ['quantity' => 0 + $row->shortfall_quantity]);
            }
            if ($row->partner_quantity > 0) {
                $reasons[] = __('partners waiting for :quantity', ['quantity' => 0 + $row->partner_quantity]);
            }
            if ($row->days_cover !== null) {
                $reasons[] = __(':days days of cover left', ['days' => 0 + $row->days_cover]);
            }
            if ($row->shelf_life_days) {
                $reasons[] = __('shelf life :days days', ['days' => $row->shelf_life_days]);
            }
            $row->reasons = $reasons;

            return $row;
        });

        $orders->getCollection()->transform(function ($order) use ($coverage) {
            $orderCoverage         = $coverage[$order->id] ?? ['ready_lines' => 0, 'total_lines' => 0];
            $order->ready_lines    = $orderCoverage['ready_lines'];
            $order->total_lines    = $orderCoverage['total_lines'];

            return $order;
        });

        return Inertia::render(
            'Org/Dispatching/OrdersAtGate',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Gate'),
                'pageHead'    => [
                    'title' => __('The gate'),
                    'model' => __('Goods Out'),
                    'icon'  => [
                        'icon'  => ['fal', 'fa-door-closed'],
                        'title' => __('The gate'),
                    ],
                ],
                'data'      => $orders,
                'shortfall'  => $this->shortfall($this->organisation),
                'make_queue' => $makeQueue,
                'currency_code' => $this->organisation->currency->code,
            ]
        )->table($this->tableStructure())->table($this->shortfallTableStructure())->table($this->makeQueueTableStructure());
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowDispatchHub::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.dispatching.gate',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Gate'),
                        'icon'  => 'fal fa-door-closed',
                    ],
                ],
            ]
        );
    }
}
