<?php
/*
 * author Arya Permana - Kirin
 * created on 19-05-2025-15h-34m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Fulfilment\CustomerSalesChannel\UI;

use App\Actions\CRM\Customer\UI\ShowCustomer;
use App\Actions\CRM\Customer\UI\WithCustomerSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
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

class IndexFulfilmentCustomerSalesChannels extends RetinaAction
{
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
                'customer_sales_channels.slug as customer_sales_channel_slug',
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
        $icon          = ['fal', 'fa-user'];
        $title         = $this->customer->name;
        $iconRight     = [
            'icon'  => ['fal', 'fa-user-friends'],
            'title' => $title
        ];
        $afterTitle    = [

            'label' => __('Sales Channels')
        ];

        return Inertia::render(
            'Dropshipping/RetinaCustomerSalesChannels',
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
                    'actions'       => [
                        [
                            'type'  => 'button',
                            'style' => 'create',
                            'label' => 'Add Sales Channel',
                            'route' => [
                                'name'       => 'retina.fulfilment.dropshipping.customer_sales_channels.create',
                            ]
                        ]
                    ]
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

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        return $this->handle($this->customer);
    }

    public function getBreadcrumbs(): array
    {

        return
            array_merge(
                ShowRetinaDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'retina.dropshipping.platforms.dashboard',
                                'parameters'  => ['manual']  // TODO: change to correct one
                            ],
                            'label' => __('Channel Dashboard'),
                        ]
                    ]
                ]
            );

    }
}
