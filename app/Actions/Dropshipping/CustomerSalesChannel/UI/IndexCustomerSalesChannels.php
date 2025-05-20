<?php

/*
 * author Arya Permana - Kirin
 * created on 04-04-2025-11h-52m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\CustomerSalesChannel\UI;

use App\Actions\CRM\Customer\UI\ShowCustomer;
use App\Actions\CRM\Customer\UI\WithCustomerSubNavigation;
use App\Actions\OrgAction;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Http\Resources\CRM\CustomerSalesChannelsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexCustomerSalesChannels extends OrgAction
{
    use WithCustomerSubNavigation;

    private Customer $customer;

    public function handle(Customer $customer, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('platforms.code', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(CustomerSalesChannel::class)
                ->where('customer_sales_channels.customer_id', $customer->id)
                ->select([
                'customer_sales_channels.id',
                'customer_sales_channels.reference',
                'customer_sales_channels.slug',
                'customer_sales_channels.number_customer_clients as number_customer_clients',
                'customer_sales_channels.number_portfolios as number_portfolios',
                'customer_sales_channels.number_orders as number_orders',
                'customer_sales_channels.platform_id',
                ])
                ->selectSub(function ($subquery) {
                $subquery->from('orders')
                    ->selectRaw('COALESCE(SUM(total_amount), 0)')
                    ->whereColumn('orders.customer_sales_channel_id', 'customer_sales_channels.id')
                    ->whereNotIn('orders.state', [OrderStateEnum::CREATING, OrderStateEnum::SUBMITTED, OrderStateEnum::CANCELLED]);
                }, 'total_amount')
                ->defaultSort('customer_sales_channels.reference')
                ->allowedSorts(['reference', 'number_customer_clients', 'number_portfolios','number_orders'])
                ->allowedFilters([$globalSearch])
                ->withPaginator($prefix, tableName: request()->route()->getName())
                ->withQueryString();

        return $query;
    }

    public function htmlResponse(LengthAwarePaginator $platforms, ActionRequest $request): Response
    {

        $subNavigation = $this->getCustomerDropshippingSubNavigation($this->customer, $request);
        $icon          = ['fal', 'fa-user'];
        $title         = $this->customer->name;
        $iconRight     = [
            'icon'  => ['fal', 'fa-user-friends'],
            'title' => $title
        ];
        $afterTitle    = [

            'label' => __('Sales Channels')
        ];

        $manualPlatform = Platform::where('type', PlatformTypeEnum::MANUAL)->first();

        $manualCustomerSalesChannel = $this->customer->customerSalesChannels()
            ->where('platform_id', $manualPlatform->id)
            ->first();

        $actions = [];



        return Inertia::render(
            'Org/Dropshipping/CustomerSalesChannels',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Sales Channels'),
                'pageHead'    => [
                    'title'         => $title,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'icon'          => $icon,
                    'subNavigation' => $subNavigation,
                    'actions'       => $actions

                ],
                'data'        => CustomerSalesChannelsResource::collection($platforms),
            ]
        )->table($this->tableStructure());
    }


    public function tableStructure(array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }
            $table
                ->withModelOperations($modelOperations)
                ->withGlobalSearch()
                ->column(key: 'reference', label: __('Reference'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_portfolios', label: __('Portfolios'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_clients', label: __('Clients'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_orders', label: __('Orders'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'amount', label: __('Amount'), canBeHidden: false, sortable: true, searchable: true, align: 'right')
                ->defaultSort('reference');
        };
    }

    public function asController(Organisation $organisation, Shop $shop, Customer $customer, ActionRequest $request): LengthAwarePaginator
    {
        $this->customer = $customer;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($customer);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            ShowCustomer::make()->getBreadcrumbs($routeName, $routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.crm.customers.show.customer_sales_channels.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Channels'),
                        'icon'  => 'fal fa-bars',
                    ],

                ]
            ]
        );
    }
}
