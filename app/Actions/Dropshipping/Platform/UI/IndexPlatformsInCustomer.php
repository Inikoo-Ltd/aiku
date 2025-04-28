<?php

/*
 * author Arya Permana - Kirin
 * created on 04-04-2025-11h-52m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\Platform\UI;

use App\Actions\CRM\Customer\UI\ShowCustomer;
use App\Actions\CRM\Customer\UI\WithCustomerSubNavigation;
use App\Actions\OrgAction;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Http\Resources\CRM\PlatformsInCustomerResource;
use App\Http\Resources\Platform\PlatformsResource;
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

class IndexPlatformsInCustomer extends OrgAction
{
    use WithCustomerSubNavigation;

    private Customer $parent;

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
        $query->join('customer_has_platforms', 'customer_has_platforms.platform_id', 'platforms.id');
        $query->where('customer_has_platforms.customer_id', $customer->id);

        return $query
            ->defaultSort('customer_has_platforms.id')
            ->select([
                'customer_has_platforms.id as customer_has_platform_id',
                'customer_has_platforms.number_customer_clients as number_customer_clients',
                'customer_has_platforms.number_portfolios as number_portfolios',
                'customer_has_platforms.number_orders as number_orders',
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
        $subNavigation = $this->getCustomerDropshippingSubNavigation($this->parent, $request);
        $icon          = ['fal', 'fa-user'];
        $title         = $this->parent->name;
        $iconRight     = [
            'icon'  => ['fal', 'fa-user-friends'],
            'title' => __('channels')
        ];
        $afterTitle    = [

            'label' => __('Channels')
        ];

        $enableAiku  = !$this->parent->platforms()->where('type', PlatformTypeEnum::MANUAL)->first();
        $aikuChannel = Platform::where('type', PlatformTypeEnum::MANUAL)->first();

        $actions = [];

        if (!$this->parent->platforms()->where('type', PlatformTypeEnum::MANUAL)->first() && $aikuChannel) {
            $actions[] = [
                'type'        => 'button',
                'style'       => 'create',
                'label'       => __('add manual channel'),
                'fullLoading' => true,
                'route'       => [
                    'method'     => 'post',
                    'name'       => 'grp.models.customer.platform.attach',
                    'parameters' => [
                        'customer' => $this->parent->id,
                        'platform' => $aikuChannel->id
                    ]
                ]
            ];
        }

        return Inertia::render(
            'Org/Shop/CRM/PlatformsInCustomer',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Channels'),
                'pageHead'    => [
                    'title'         => $title,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'icon'          => $icon,
                    'subNavigation' => $subNavigation,
                    'actions'       => $actions

                ],
                'data'        => PlatformsInCustomerResource::collection($platforms),
                'platforms'   => PlatformsResource::collection($this->parent->group->platforms),
                'enableAiku'  => $enableAiku,
                'attachRoute' => [
                    'name'       => 'grp.models.customer.platform.attach',
                    'parameters' => [
                        'customer' => $this->parent->id,
                    ]
                ]
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
        $this->parent = $customer;
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
