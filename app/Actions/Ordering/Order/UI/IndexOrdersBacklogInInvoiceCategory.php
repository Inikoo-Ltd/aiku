<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 01 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Ordering\Order\UI;

use App\Actions\Accounting\InvoiceCategory\UI\ShowInvoiceCategory;
use App\Actions\Accounting\InvoiceCategory\WithInvoiceCategorySubNavigation;
use App\Actions\Ordering\Order\GetOrderBacklog;
use App\Actions\OrgAction;
use App\Actions\Traits\Dashboards\Settings\WithDashboardPartnersTypeSettings;
use App\Enums\UI\Ordering\OrdersTabsEnum;
use App\Http\Resources\Ordering\OrdersResource;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\InvoiceCategory;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Orders behind the dashboard Backlog column of an invoice category: submitted but not invoiced yet, as of now.
 */
class IndexOrdersBacklogInInvoiceCategory extends OrgAction
{
    use WithInvoiceCategorySubNavigation;
    use WithDashboardPartnersTypeSettings;

    private InvoiceCategory $invoiceCategory;
    private bool $includePartners = false;
    private int $numberBacklogOrders = 0;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo([
            "accounting.{$this->organisation->id}.view",
            "orders.{$this->organisation->id}.view",
            'group-overview',
        ]);
    }

    public function handle(InvoiceCategory $invoiceCategory, bool $includePartners = false, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereWith('orders.reference', $value)
                    ->orWhereWith('customers.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $backlogOrderIds           = GetOrderBacklog::make()->orderIdsInInvoiceCategory($invoiceCategory, $includePartners);
        $this->numberBacklogOrders = count($backlogOrderIds);

        $query = QueryBuilder::for(Order::class);
        $query->whereIn('orders.id', $backlogOrderIds);
        $query->leftJoin('customers', 'orders.customer_id', '=', 'customers.id');
        $query->leftJoin('currencies', 'orders.currency_id', '=', 'currencies.id');
        $query->leftJoin('organisations', 'orders.organisation_id', '=', 'organisations.id');
        $query->leftJoin('shops', 'orders.shop_id', '=', 'shops.id');
        $query->leftJoin('platforms', 'orders.platform_id', '=', 'platforms.id');
        $query->leftJoin('sales_channels', 'orders.sales_channel_id', '=', 'sales_channels.id');

        return $query->defaultSort('-orders.submitted_at')
            ->select([
                'orders.id',
                'orders.slug',
                'orders.reference',
                'orders.date',
                'orders.submitted_at',
                'orders.state',
                'orders.created_at',
                'orders.is_premium_dispatch',
                'orders.has_extra_packing',
                'orders.has_insurance',
                'orders.is_export',
                'orders.customer_sales_channel_id',
                'orders.net_amount',
                'orders.total_amount',
                'orders.payment_amount',
                'orders.pay_detailed_status',
                'orders.to_be_paid_by',
                'customers.name as customer_name',
                'customers.slug as customer_slug',
                'customers.is_vip as is_customer_vip',
                'currencies.code as currency_code',
                'currencies.id as currency_id',
                'shops.name as shop_name',
                'shops.code as shop_code',
                'shops.slug as shop_slug',
                'organisations.name as organisation_name',
                'organisations.slug as organisation_slug',
                'platforms.type as platform',
                'sales_channels.type as sales_channel_type',
                'sales_channels.name as sales_channel_name',
                'sales_channels.code as sales_channel_code',
            ])
            ->allowedSorts(['id', 'reference', 'submitted_at', 'net_amount', 'customer_name', 'shop_name', 'pay_detailed_status'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(int $numberBacklogOrders, ?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($numberBacklogOrders, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');

                InertiaTable::updateQueryBuilderParameters($prefix);
            }

            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('order'), __('orders')])
                ->withEmptyState(
                    [
                        'title' => __('No orders waiting to be invoiced'),
                        'count' => $numberBacklogOrders,
                    ]
                );

            $table->column(key: 'state', label: '', type: 'icon');
            $table->column(key: 'reference', label: __('Reference'), sortable: true);
            $table->column(key: 'submitted_at', label: __('Submitted'), sortable: true, type: 'date_hm');
            $table->column(key: 'customer_name', label: __('Customer'), sortable: true);
            $table->column(key: 'shop_name', label: __('Shop'), sortable: true);
            $table->column(key: 'pay_detailed_status', label: __('Payment'), sortable: true);
            $table->column(key: 'net_amount', label: __('Net'), sortable: true, type: 'currency');
        };
    }

    public function jsonResponse(LengthAwarePaginator $orders): AnonymousResourceCollection
    {
        return OrdersResource::collection($orders);
    }

    public function htmlResponse(LengthAwarePaginator $orders, ActionRequest $request): Response
    {
        return Inertia::render(
            'Ordering/Orders',
            [
                'breadcrumbs'                 => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'title'                       => __('Backlog').': '.$this->invoiceCategory->name,
                'sales_channels'              => [],
                'can_add_order'               => false,
                'pageHead'                    => [
                    'title'         => $this->invoiceCategory->name,
                    'model'         => __('Backlog'),
                    'icon'          => [
                        'icon'  => ['fal', 'fa-shopping-cart'],
                        'title' => __('Orders submitted but not invoiced yet'),
                    ],
                    'afterTitle'    => [
                        'label'   => $this->includePartners ? __('With partners') : __('Without partners'),
                        'tooltip' => __('Follows the partners setting on the dashboard'),
                    ],
                    'subNavigation' => $this->getInvoiceCategoryNavigation($this->invoiceCategory),
                ],
                'tabs'                        => [
                    'current'    => $this->tab,
                    'navigation' => Arr::only(OrdersTabsEnum::navigation(), [OrdersTabsEnum::ORDERS->value]),
                ],
                OrdersTabsEnum::ORDERS->value => OrdersResource::collection($orders),
            ]
        )->table($this->tableStructure($this->numberBacklogOrders, OrdersTabsEnum::ORDERS->value));
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(Organisation $organisation, InvoiceCategory $invoiceCategory, ActionRequest $request): LengthAwarePaginator
    {
        $this->invoiceCategory = $invoiceCategory;
        $this->includePartners = $this->dashboardIncludesPartners($request->user()->settings ?? []);
        $this->initialisation($invoiceCategory->organisation, $request)->withTab([OrdersTabsEnum::ORDERS->value]);

        return $this->handle($invoiceCategory, $this->includePartners, OrdersTabsEnum::ORDERS->value);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            ShowInvoiceCategory::make()->getBreadcrumbs($this->invoiceCategory, $routeName, $routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => $routeName,
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Backlog'),
                    ],
                ],
            ]
        );
    }
}
