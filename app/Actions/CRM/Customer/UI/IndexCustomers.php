<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:32:25 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Http\Resources\CRM\CustomersResource;
use App\InertiaTable\InertiaTable;
use App\Models\CRM\Customer;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexCustomers extends OrgAction
{
    private Shop|Organisation $parent;
    private bool $canCreateShop = false;

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit       = $request->user()->hasPermissionTo("crm.{$this->shop->id}.edit");
        $this->canCreateShop = $request->user()->hasPermissionTo("org-admin.{$this->organisation->id}");

        return $request->user()->hasPermissionTo("crm.{$this->shop->id}.view");
    }

    public function inOrganisation(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);
        $this->parent = $organisation;

        return $this->handle($this->parent);
    }


    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($shop, $request);
        $this->parent = $shop;

        return $this->handle($shop);
    }


    public function handle(Organisation|Shop $parent, $prefix = null): LengthAwarePaginator
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

        $queryBuilder = QueryBuilder::for(Customer::class);


        if (class_basename($parent) == 'Shop') {
            $queryBuilder->where('customers.shop_id', $parent->id);
        } else {
            $queryBuilder->where('customers.organisation_id', $parent->id)
                ->addSelect([
                    'shops.code as shop_code',
                    'shops.slug as shop_slug',
                ])
                ->leftJoin('shops', 'shops.id', 'shop_id');
        }


        /*
        foreach ($this->elementGroups as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                prefix: $prefix,
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine']
            );
        }
        */


        return $queryBuilder
            ->defaultSort('-created_at')
            ->select([
                'customers.location',
                'customers.reference',
                'customers.id',
                'customers.name',
                'customers.slug',
                'customers.created_at',
                'customer_dropshipping_stats.number_current_portfolios',
                'customer_stats.number_current_clients',
                'customer_stats.last_invoiced_at',
                'customer_stats.number_invoices_type_invoice',
                'customer_stats.sales_all',
                'customer_stats.sales_org_currency_all',
                'customer_stats.sales_grp_currency_all',
                'shops.currency_id',
                'platforms.name as platform_name',
                'currencies.code as currency_code',
            ])
            ->leftJoin('model_has_platforms', function ($join) {
                $join->on('customers.id', '=', 'model_has_platforms.model_id')
                    ->where('model_has_platforms.model_type', '=', class_basename(Customer::class));
            })
            ->leftJoin('platforms', 'model_has_platforms.platform_id', '=', 'platforms.id')
            ->leftJoin('customer_dropshipping_stats', 'customers.id', 'customer_dropshipping_stats.customer_id')
            ->leftJoin('customer_stats', 'customers.id', 'customer_stats.customer_id')
            ->leftJoin('shops', 'customers.shop_id', 'shops.id')
            ->leftJoin('currencies', 'shops.currency_id', 'currencies.id')
            ->allowedSorts([
                'reference',
                'name',
                'number_current_clients',
                'number_current_portfolios',
                'slug',
                'created_at',
                'number_invoices_type_invoice',
                'last_invoiced_at',
                'invoiced_net_amount',
                'invoiced_org_net_amount',
                'invoiced_grp_net_amount',

            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix)
            ->withQueryString();
    }

    public function tableStructure(Organisation|Shop $parent, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $isDropshipping = false;
            if ($parent instanceof Shop and $parent->type == ShopTypeEnum::DROPSHIPPING) {
                $isDropshipping = true;
            }

            $table
                ->withModelOperations($modelOperations)
                ->withGlobalSearch()
                ->withEmptyState(
                    match (class_basename($parent)) {
                        'Organisation' => [
                            'title'       => __("No customers found"),
                            'description' => $this->canCreateShop && $parent->catalogueStats->number_shops == 0 ? __('Get started by creating a shop. ✨')
                                : __("In fact, is no even a shop yet 🤷🏽‍♂️"),
                            'count'       => $parent->crmStats->number_customers,
                            'action'      => $this->canCreateShop && $parent->catalogueStats->number_shops == 0
                                ? [
                                    'type'    => 'button',
                                    'style'   => 'create',
                                    'tooltip' => __('new shop'),
                                    'label'   => __('shop'),
                                    'route'   => [
                                        'name' => 'shops.create',
                                    ]
                                ]
                                :
                                [
                                    'type'    => 'button',
                                    'style'   => 'create',
                                    'tooltip' => __('new customer'),
                                    'label'   => __('customer'),
                                    'route'   => [
                                        'name' => 'shops.create',
                                    ]
                                ]


                        ],
                        'Shop' => [
                            'title'       => __("No customers found"),
                            'description' => ($parent->type == ShopTypeEnum::FULFILMENT || $parent->type == ShopTypeEnum::DROPSHIPPING) ? __("You can add your customer 🤷🏽‍♂️") : null,
                            'count'       => $parent->crmStats->number_customers,
                            'action'      => ($parent->type == ShopTypeEnum::FULFILMENT || $parent->type == ShopTypeEnum::DROPSHIPPING) ? [
                                'type'    => 'button',
                                'style'   => 'create',
                                'tooltip' => __('new customer'),
                                'label'   => __('customer'),
                                'route'   => [
                                    'name'       => 'grp.org.shops.show.crm.customers.create',
                                    'parameters' => [
                                        'organisation' => $parent->organisation->slug,
                                        'shop'         => $parent->slug
                                    ]
                                ]
                            ] : null
                        ],
                        default => null
                    }
                )
                ->column(key: 'reference', label: __('reference'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'location', label: __('location'), canBeHidden: false, searchable: true)
                ->column(key: 'created_at', label: __('since'), canBeHidden: false, sortable: true, searchable: true);


            if ($isDropshipping) {
                $table->column(key: 'number_current_clients', label: __('Clients'), canBeHidden: false, sortable: true, searchable: true)
                    ->column(key: 'number_current_portfolios', label: __('Portfolios'), canBeHidden: false, sortable: true, searchable: true)
                    ->column(key: 'platforms', label: __('Platforms'), canBeHidden: false, sortable: true, searchable: true);
            }

            $table->column(key: 'last_invoiced_at', label: __('last invoice'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_invoices_type_invoice', label: __('invoices'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'invoiced_net_amount', label: __('sales'), canBeHidden: false, sortable: true, searchable: true);

            $table->defaultSort('-created_at');
        };
    }

    public function jsonResponse(LengthAwarePaginator $customers): AnonymousResourceCollection
    {
        return CustomersResource::collection($customers);
    }

    public function htmlResponse(LengthAwarePaginator $customers, ActionRequest $request): Response
    {
        $scope = $this->parent;


        return Inertia::render(
            'Org/Shop/CRM/Customers',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('customers'),
                'pageHead'    => [
                    'title'   => __('customers'),
                    'icon'    => [
                        'icon'  => ['fal', 'fa-user'],
                        'title' => __('customer')
                    ],
                    'actions' => [
                        [
                            'type'    => 'button',
                            'style'   => 'create',
                            'tooltip' => __('New Customer'),
                            'label'   => __('New Customer'),
                            'route'   => [
                                'name'       => 'grp.org.shops.show.crm.customers.create',
                                'parameters' => [
                                    'organisation' => $scope->organisation->slug,
                                    'shop'         => $scope->slug
                                ]
                            ]
                        ],
                    ],
                ],
                'data'        => CustomersResource::collection($customers),

            ]
        )->table($this->tableStructure($this->parent));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
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

        return match ($routeName) {
            'grp.org.shops.show.crm.customers.index' =>
            array_merge(
                ShowShop::make()->getBreadcrumbs(
                    $routeParameters
                ),
                $headCrumb(
                    [
                        'name'       => 'grp.org.shops.show.crm.customers.index',
                        'parameters' => $routeParameters
                    ]
                )
            ),
            default => []
        };
    }
}
