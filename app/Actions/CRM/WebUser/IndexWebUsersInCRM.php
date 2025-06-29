<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:25:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\WebUser;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\CRM\Customer\UI\ShowCustomer;
use App\Actions\CRM\Customer\UI\WithCustomerSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Actions\Traits\WithCustomersSubNavigation;
use App\Http\Resources\CRM\WebUsersResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\CRM\WebUser;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexWebUsersInCRM extends OrgAction
{
    use WithCRMAuthorisation;
    use WithCustomerSubNavigation;
    use WithCustomersSubNavigation;


    private Shop|Customer $parent;


    public function handle(Shop|Customer $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('username', $value);
            });
        });
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(WebUser::class);

        if ($parent instanceof Customer) {
            $queryBuilder->where('customer_id', $parent->id);
        } else {
            $queryBuilder->where('shop_id', $parent->id);
        }


        return $queryBuilder
            ->defaultSort('username')
            ->select([
                'web_users.username',
                'web_users.id',
                'web_users.email',
                'web_users.slug',
                'web_users.created_at',
            ])
            ->allowedSorts(['email', 'username', 'created_at', 'organisation_name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }


    public function htmlResponse(LengthAwarePaginator $webUsers, ActionRequest $request): Response
    {


        $icon       = ['fal', 'fa-terminal'];
        $title      = __('web users');
        $afterTitle = null;
        $iconRight  = null;


        if ($this->parent instanceof Customer) {
            if ($this->parent->is_dropshipping) {
                $subNavigation = $this->getCustomerDropshippingSubNavigation($this->parent, $request);
            } else {
                $subNavigation = $this->getCustomerSubNavigation($this->parent, $request);
            }
            $icon       = ['fal', 'fa-user'];
            $title      = $this->parent->name;
            $iconRight  = [
                'icon' => 'fal fa-terminal',
            ];
            $afterTitle = [

                'label' => __('Web users')
            ];
        } else {
            $subNavigation = $this->getSubNavigation($request);
        }

        return Inertia::render(
            'Org/Shop/CRM/WebUsers',
            [
                'breadcrumbs'                     => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters(),
                ),
                'title'                           => $title,
                'pageHead'                        => [
                    'title'         => $title,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'icon'          => $icon,
                    'subNavigation' => $subNavigation,
                    'actions'       => [
                        ($this->canEdit && $this->parent instanceof Customer) ? [
                            'type'  => 'button',
                            'style' => 'create',
                            'label' => $title,
                            'route' => [
                                'name'       => 'grp.org.shops.show.crm.customers.show.web_users.create',
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ] : false
                    ]
                ],



                'data' => WebUsersResource::collection($webUsers),

            ]
        )->table($this->tableStructure(parent: $this->parent));
    }

    public function tableStructure(Shop|Customer $parent, ?array $modelOperations = null, $prefix = null, $canEdit = false): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix, $parent, $canEdit) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }
            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState(
                    match (class_basename($parent)) {
                        'Customer' => [
                            'title'  => __("Customer don't have any login credentials"),
                            'count'  => $parent->stats->number_web_users,
                            'action' => $canEdit && $parent->stats->number_web_users == 0
                                ?
                                [
                                    'type'    => 'button',
                                    'style'   => 'create',
                                    'tooltip' => __('new website user'),
                                    'label'   => __('website user'),
                                    'route'   => [
                                        'name'       => 'grp.org.shops.show.crm.customers.show.web_users.create',
                                        'parameters' => [$parent->organisation->slug, $parent->shop->slug, $parent->slug]
                                    ]
                                ] : null

                        ],
                        default => null
                    }
                )
                ->column(key: 'username', label: __('username'), canBeHidden: false, sortable: true, searchable: true);
            $table
                ->column(key: 'created_at', label: __('since'), canBeHidden: false, sortable: true, searchable: true);
            if ($parent instanceof Customer) {
                $table->column(key: 'action', label: __('Action'), canBeHidden: false);
            }
            $table->defaultSort('username');
        };
    }


    /** @noinspection PhpUnusedParameterInspection */
    public function inShop(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);

        return $this->handle(parent: $shop);
    }


    public function asController(Organisation $organisation, Shop $shop, Customer $customer, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $customer;
        $this->initialisationFromShop($shop, $request);

        return $this->handle(parent: $customer);
    }


    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Web users'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ];
        };


        return match ($routeName) {
            'grp.org.shops.show.crm.web_users.index' =>
            array_merge(
                ShowShop::make()->getBreadcrumbs(
                    $routeParameters
                ),
                $headCrumb(
                    $routeParameters
                )
            ),
            'grp.org.shops.show.crm.customers.show.web_users.index' =>
            array_merge(
                ShowCustomer::make()->getBreadcrumbs('grp.org.shops.show.crm.customers.show', $routeParameters),
                $headCrumb(
                    $routeParameters
                )
            ),


            default => []
        };
    }
}
