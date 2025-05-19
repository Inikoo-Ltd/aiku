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
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Http\Resources\CRM\PlatformsInCustomerResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
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

        $query = QueryBuilder::for(Platform::class);
        $query->join('customer_sales_channels', 'customer_sales_channels.platform_id', 'platforms.id');
        $query->where('customer_sales_channels.customer_id', $customer->id);

        return $query
            ->defaultSort('customer_sales_channels.id')
            ->select([
                'customer_sales_channels.id as customer_sales_channel_id',
                'customer_sales_channels.number_customer_clients as number_customer_clients',
                'customer_sales_channels.number_portfolios as number_portfolios',
                'customer_sales_channels.number_orders as number_orders',
                'platforms.id',
                'platforms.slug',
                'platforms.code',
                'platforms.name',
                'platforms.type'
            ])
            ->allowedSorts(['code', 'name', 'type'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
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

        if (!$manualCustomerSalesChannel && $manualPlatform) {
            $actions[] = [
                'type'        => 'button',
                'style'       => 'create',
                'label'       => __('add manual channel'),
                'fullLoading' => true,
                'route'       => [
                    'method'     => 'post',
                    'name'       => 'grp.models.customer.customer_sales_channel.store',
                    'parameters' => [
                        'customer' => $this->customer->id,
                        'platform' => $manualPlatform->id
                    ]
                ]
            ];
        }

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
                'data'        => PlatformsInCustomerResource::collection($platforms),
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
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_portfolios', label: __('Number Portfolios'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_clients', label: __('Number Clients'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_orders', label: __('Number Orders'), canBeHidden: false, sortable: true, searchable: true)
                ->defaultSort('code');
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
                            'name'       => 'grp.org.shops.show.crm.customers.show.platforms.index',
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
