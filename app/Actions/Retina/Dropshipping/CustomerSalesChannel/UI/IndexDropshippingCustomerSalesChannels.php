<?php

/*
 * author Arya Permana - Kirin
 * created on 19-05-2025-15h-34m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\CustomerSalesChannel\UI;

use App\Actions\RetinaAction;
use App\Http\Resources\CRM\CustomerSalesChannelsResource;
use App\InertiaTable\InertiaTable;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexDropshippingCustomerSalesChannels extends RetinaAction
{
    public function handle(Customer $customer, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('customer_sales_channels.reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(CustomerSalesChannel::class);
        $query->leftjoin('platforms', 'customer_sales_channels.platform_id', 'platforms.id');
        $query->where('customer_sales_channels.customer_id', $customer->id);

        return $query
            ->defaultSort('customer_sales_channels.reference')
            ->select([
                'customer_sales_channels.id',
                'customer_sales_channels.status',
                'customer_sales_channels.reference',
                'customer_sales_channels.slug',
                'customer_sales_channels.number_customer_clients as number_customer_clients',
                'customer_sales_channels.number_portfolios as number_portfolios',
                'customer_sales_channels.number_orders as number_orders',
                'customer_sales_channels.platform_id',
                'customer_sales_channels.name',
            ])
            ->allowedSorts(['reference', 'number_customer_clients', 'number_portfolios', 'number_orders'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function htmlResponse(LengthAwarePaginator $platforms, ActionRequest $request): Response
    {
        $icon = ['fal', 'fa-user'];
        $title = $this->customer->name;
        $iconRight = [
            'icon' => ['fal', 'fa-user-friends'],
            'title' => $title
        ];
        $afterTitle = [

            'label' => __('Sales Channels')
        ];

        return Inertia::render(
            'Dropshipping/RetinaCustomerSalesChannels',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title' => __('Sales Channels'),
                'pageHead' => [
                    'title' => $title,
                    'afterTitle' => $afterTitle,
                    'iconRight' => $iconRight,
                    'icon' => $icon,
                    'actions' => [
                        [
                            'type' => 'button',
                            'style' => 'create',
                            'label' => 'Add Sales Channel',
                            'route' => [
                                'name' => 'retina.dropshipping.customer_sales_channels.create',
                            ]
                        ]
                    ]
                ],
                'data' => CustomerSalesChannelsResource::collection($platforms),
            ]
        )->table($this->tableStructure());
    }


    public function tableStructure(array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix . 'Page');
            }
            $table
                ->withModelOperations($modelOperations)
                ->withGlobalSearch()
                ->column(key: 'platform_name', label: __('Platform'), canBeHidden: false, sortable: false, searchable: false)
                ->column(key: 'reference', label: __('Reference'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Store Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_portfolios', label: __('Number Portfolios'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_clients', label: __('Number Clients'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_orders', label: __('Number Orders'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'status', label: __('Status'), canBeHidden: false)
                ->column(key: 'action', label: __('Action'), canBeHidden: false)
                ->defaultSort('reference');
        };
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        return $this->handle($this->customer);
    }

    public function getBreadcrumbs(): array
    {

        return
            [
                [
                    'type' => 'simple',
                    'simple' => [
                        'route' => [
                            'name' => 'retina.dropshipping.customer_sales_channels.index',
                            'parameters' => []
                        ],
                        'label' => __('Channels'),
                    ]
                ]
            ];
    }
}
