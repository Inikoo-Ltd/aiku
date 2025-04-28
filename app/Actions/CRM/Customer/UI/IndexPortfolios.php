<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:32:25 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer\UI;

use App\Actions\OrgAction;
use App\Http\Resources\CRM\PortfolioResource;
use App\InertiaTable\InertiaTable;
use App\Models\CRM\Customer;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\Portfolio;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexPortfolios extends OrgAction
{
    use WithCustomerSubNavigation;

    private Customer $parent;

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->authTo("crm.{$this->shop->id}.edit");

        return $request->user()->authTo("crm.{$this->shop->id}.view");
    }

    public function asController(Organisation $organisation, Shop $shop, Customer $customer, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($shop, $request);
        $this->parent = $customer;

        return $this->handle($customer);
    }


    public function handle(Customer $parent, $prefix = null): LengthAwarePaginator
    {

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('portfolios.reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Portfolio::class);


        if (class_basename($parent) == 'Customer') {
            $queryBuilder->where('portfolios.customer_id', $parent->id);
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
            ->defaultSort('portfolios.reference')
            ->with('item')
            ->allowedSorts(['reference', 'created_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure($parent, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withModelOperations($modelOperations)
                ->withGlobalSearch()
                ->withEmptyState(
                    match (class_basename($parent)) {
                        'Customer' => [
                            'title'       => __("No portfolios found"),
                            'description' => __("You can add your portfolio 🤷🏽‍♂️"),
                            'count'       => $parent->stats->number_customer_clients,
                        ],
                        default => null
                    }
                )
                ->column(key: 'product_code', label: __('product'), canBeHidden: false, searchable: true)
                ->column(key: 'product_name', label: __('product name'), canBeHidden: false, searchable: true)
                ->column(key: 'reference', label: __('customer reference'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'type', label: __('type'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'created_at', label: __('created at'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'action', label: __('action'), canBeHidden: false);
        };
    }

    public function jsonResponse(LengthAwarePaginator $portfolio): AnonymousResourceCollection
    {
        return PortfolioResource::collection($portfolio);
    }

    public function htmlResponse(LengthAwarePaginator $portfolio, ActionRequest $request): Response
    {
        $scope = $this->parent;

        $subNavigation = $this->getCustomerDropshippingSubNavigation($this->parent, $request);

        $icon       = ['fal', 'fa-user'];
        $title      = $this->parent->name;
        $iconRight  = [
            'icon'  => ['fal', 'fa-chess-board'],
            'title' => __('portfolio')
        ];
        $afterTitle = [

            'label' => __('Portfolio')
        ];


        return Inertia::render(
            'Org/Shop/CRM/Portfolios',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('portfolios'),
                'pageHead'    => [
                    'title'         => $title,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'icon'          => $icon,
                    'subNavigation' => $subNavigation,
                    'actions'       => [
                        [
                            'type'    => 'button',
                            'style'   => 'create',
                            'tooltip' => __('Add Product'),
                            'label'   => __('New Item'),
                            'route'   => [
                                'method'     => 'post',
                                'name'       => 'grp.models.org.shop.customer.portfolio.store',
                                'parameters' => [
                                    'organisation' => $scope->organisation->id,
                                    'shop'         => $scope->shop->id,
                                    'customer'     => $scope->id
                                ]
                            ]
                        ],
                    ],
                ],
                'data'        => PortfolioResource::collection($portfolio),

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
                        'label' => __('Portfolios'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.shops.show.crm.customers.show.portfolios.index' =>
            array_merge(
                ShowCustomer::make()->getBreadcrumbs(
                    'grp.org.shops.show.crm.customers.show',
                    $routeParameters
                ),
                $headCrumb(
                    [
                        'name'       => 'grp.org.shops.show.crm.customers.show.portfolios.index',
                        'parameters' => $routeParameters
                    ]
                )
            ),
            default => []
        };
    }
}
