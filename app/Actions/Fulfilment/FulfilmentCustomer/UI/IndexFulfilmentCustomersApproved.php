<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Jan 2024 20:05:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\FulfilmentCustomer\UI;

use App\Actions\Fulfilment\Fulfilment\UI\ShowFulfilment;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithFulfilmentShopAuthorisation;
use App\Actions\Traits\WithFulfilmentCustomersSubNavigation;
use App\Enums\CRM\Customer\CustomerStatusEnum;
use App\Http\Resources\Fulfilment\FulfilmentCustomersResource;
use App\InertiaTable\InertiaTable;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexFulfilmentCustomersApproved extends OrgAction
{
    use WithFulfilmentShopAuthorisation;
    use WithFulfilmentCustomersSubNavigation;



    public function handle(Fulfilment $fulfilment, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('customers.name', $value)
                    ->orWhereStartWith('customers.email', $value)
                    ->orWhere('customers.reference', '=', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(FulfilmentCustomer::class);
        $queryBuilder->where('fulfilment_customers.fulfilment_id', $fulfilment->id);

        $queryBuilder->whereIn('customers.status', [CustomerStatusEnum::APPROVED]);



        return $queryBuilder
            ->defaultSort('-customers.created_at')
            ->select([
                'fulfilment_customers.pallets_storage',
                'fulfilment_customers.items_storage',
                'fulfilment_customers.dropshipping',
                'fulfilment_customers.space_rental',
                'customers.reference',
                'fulfilment_customers.status',
                'fulfilment_customers.number_spaces_state_renting',
                'fulfilment_customers.number_stored_items_state_active',
                'customers.id',
                'customers.name',
                'customers.registered_at',
                'fulfilment_customers.slug',
                'fulfilment_customers.number_pallets',
                'number_pallets_status_storing',
                'customer_stats.sales_all',
                'customer_stats.sales_org_currency_all',
                'customer_stats.sales_grp_currency_all',
                'customers.location',
                'currencies.code as currency_code',
                'platforms.name as platform_name',
            ])
            ->leftJoin('customers', 'customers.id', 'fulfilment_customers.customer_id')
            ->leftJoin('customer_sales_channels', function ($join) {
                $join->on('customers.id', '=', 'customer_sales_channels.customer_id');
            })
            ->leftJoin('platforms', 'customer_sales_channels.platform_id', '=', 'platforms.id')
            ->leftJoin('customer_stats', 'customers.id', 'customer_stats.customer_id')
            ->leftJoin('shops', 'customers.shop_id', 'shops.id')
            ->leftJoin('currencies', 'shops.currency_id', 'currencies.id')
            ->allowedSorts(
                ['reference', 'name', 'number_pallets', 'slug', 'number_spaces_state_renting', 'number_stored_items_state_active', 'number_pallets_status_storing', 'status', 'sales_all', 'sales_org_currency_all', 'sales_grp_currency_all', 'customers.created_at']
            )
            ->withBetweenDates(['registered_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator(prefix: $prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Fulfilment $fulfilment, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($fulfilment, $modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table->betweenDates(['registered_at']);


            $table
                ->withModelOperations($modelOperations)
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title'       => __("You don't have any customer yet").' 😭',
                        'description' => __("Dont worry soon you will be pretty busy"),
                        'count'       => $fulfilment->shop->crmStats->number_customers,
                        'action'      => [
                            'type'    => 'button',
                            'style'   => 'create',
                            'tooltip' => __('new customer'),
                            'label'   => __('customer'),
                            'route'   => [
                                'name'       => 'grp.org.fulfilments.show.crm.customers.create',
                                'parameters' => [$fulfilment->organisation->slug, $fulfilment->slug]
                            ]
                        ]
                    ]
                )
                ->column(key: 'status', label: '', icon: 'fal fa-yin-yang', canBeHidden: false, sortable: true, type: 'avatar')
                ->column(key: 'reference', label: __('reference'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_pallets_status_storing', label: ['type' => 'text', 'data' => __('Pallets'), 'tooltip' => __('Number of pallets in warehouse')], canBeHidden: false, sortable: true)
                ->column(key: 'number_stored_items_state_active', label: ['type' => 'text', 'data' => __('SKUs'), 'tooltip' => __('Number of SKUs in warehouse')], canBeHidden: false, sortable: true)
                ->column(key: 'number_spaces_state_renting', label: ['type' => 'text', 'data' => __('Spaces'), 'tooltip' => __('Number of renting spaces')], canBeHidden: false, sortable: true)
                ->column(key: 'sales_all', label: __('sales'), canBeHidden: false, sortable: true, searchable: true, type: 'number')
                ->column(key: 'platform_name', label: __('channels'), canBeHidden: false, sortable: false, searchable: false)
                ->column(key: 'interest', label: __('interest'), canBeHidden: false);
        };
    }

    public function asController(Organisation $organisation, Fulfilment $fulfilment, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($fulfilment);
    }


    public function jsonResponse(LengthAwarePaginator $customers): AnonymousResourceCollection
    {
        return FulfilmentCustomersResource::collection($customers);
    }

    public function htmlResponse(LengthAwarePaginator $customers, ActionRequest $request): Response
    {
        $actions = [];

        if ($this->canEdit) {
            $actions[] = [
                'type'    => 'button',
                'style'   => 'create',
                'tooltip' => __('New Customer'),
                'label'   => __('New Customer'),
                'route'   => [
                    'name'       => 'grp.org.fulfilments.show.crm.customers.create',
                    'parameters' => [
                        $this->fulfilment->organisation->slug,
                        $this->fulfilment->slug
                    ]
                ]
            ];
        }

        $navigation = $this->getSubNavigation($this->fulfilment, $request);

        return Inertia::render(
            'Org/Fulfilment/FulfilmentCustomers',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'title'       => __('customers'),
                'pageHead'    => [
                    'title'         => __('customers'),
                    'icon'          => [
                        'icon'    => ['fal', 'fa-user'],
                        'tooltip' => $this->fulfilment->shop->name.' '.__('Fulfilment customers')
                    ],
                    'actions'       => $actions,
                    'subNavigation' => $navigation
                ],
                'data'        => FulfilmentCustomersResource::collection($customers)
            ]
        )->table($this->tableStructure($this->fulfilment));
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Customers'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ];
        };


        return array_merge(
            ShowFulfilment::make()->getBreadcrumbs(
                $routeParameters
            ),
            $headCrumb(
                [
                    'name'       => 'grp.org.fulfilments.show.crm.customers.index',
                    'parameters' => $routeParameters
                ]
            )
        );
    }
}
