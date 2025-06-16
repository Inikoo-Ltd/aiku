<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:25:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\WebUser;

use App\Actions\CRM\Customer\UI\ShowCustomer;
use App\Actions\CRM\Customer\UI\WithCustomerSubNavigation;
use App\Actions\Fulfilment\FulfilmentCustomer\ShowFulfilmentCustomer;
use App\Actions\Fulfilment\WithFulfilmentCustomerSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\Web\Website\UI\ShowWebsite;
use App\Enums\UI\CRM\WebUserTabsEnum;
use App\Http\Resources\CRM\WebUserRequestsResource;
use App\Http\Resources\CRM\WebUsersResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\CRM\WebUser;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexWebUsers extends OrgAction
{
    use WithAuthorizeWebUserScope;
    use WithFulfilmentCustomerSubNavigation;
    use WithCustomerSubNavigation;

    private Group|Shop|Organisation|Customer|FulfilmentCustomer|Website $parent;


    public function handle(Group|Shop|Organisation|Customer|FulfilmentCustomer|Website $parent, $prefix = null): LengthAwarePaginator
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
        $queryBuilder->leftJoin('organisations', 'web_users.organisation_id', '=', 'organisations.id')
                ->leftJoin('shops', 'web_users.shop_id', '=', 'shops.id');
        if ($parent instanceof Customer) {
            $queryBuilder->where('customer_id', $parent->id);
        } elseif ($parent instanceof FulfilmentCustomer) {
            $queryBuilder->where('customer_id', $parent->customer_id);
        } elseif ($parent instanceof Group) {
            $queryBuilder->where('web_users.group_id', $parent->id);
        } elseif ($parent instanceof Website) {
            $queryBuilder->where('website_id', $parent->id);
        } elseif ($parent instanceof Organisation) {
            $queryBuilder->where('organisation_id', $parent->id);
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
                'shops.slug as shop_slug',
                'shops.code as shop_code',
                'shops.name as shop_name',
                'organisations.name as organisation_name',
                'organisations.slug as organisation_slug',
                ])
            ->allowedSorts(['email', 'username', 'created_at', 'organisation_name', 'shop_name', 'shop_code'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }


    public function authorize(ActionRequest $request): bool
    {
        return $this->authorizeWebUserScope($request);
    }

    public function jsonResponse(): AnonymousResourceCollection
    {
        return WebUsersResource::collection($this->handle($this->parent));
    }

    public function htmlResponse(LengthAwarePaginator $webUsers, ActionRequest $request): Response
    {

        $subNavigation = [];

        $icon       = ['fal', 'fa-terminal'];
        $title      = __('web users');
        $afterTitle = null;
        $iconRight  = null;


        if ($this->parent instanceof FulfilmentCustomer) {
            $subNavigation = $this->getFulfilmentCustomerSubNavigation($this->parent, $request);
            $icon          = ['fal', 'fa-user'];
            $title         = $this->parent->customer->name;
            $iconRight     = [
                'icon' => 'fal fa-terminal',
            ];
            $afterTitle    = [

                'label' => __('Web users')
            ];
        } elseif ($this->parent instanceof Customer) {
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
        }
        return Inertia::render(
            'Org/Shop/CRM/WebUsers',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters(),
                ),
                'title'       => __('web users'),
                'pageHead'    => [
                    'title'         => $title,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'icon'          => $icon,
                    'subNavigation' => $subNavigation,
                    'actions'       => [
                        ($this->canEdit && ($this->parent instanceof Customer || $this->parent instanceof FulfilmentCustomer)) ? [
                            'type'  => 'button',
                            'style' => 'create',
                            'label' => __('website user'),
                            'route' => [
                                'name'       => $this->parent instanceof Customer ? 'grp.org.shops.show.crm.customers.show.web-users.create' : 'grp.org.fulfilments.show.crm.customers.show.web-users.create',
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ] : false
                    ]
                ],
                'tabs'                                              => [
                    'current'    => $this->tab,
                    'navigation' => WebUserTabsEnum::navigation(),
                ],
                WebUserTabsEnum::WEB_USERS->value => $this->tab == WebUserTabsEnum::WEB_USERS->value ?
                fn () => WebUsersResource::collection($webUsers)
                : Inertia::lazy(fn () => WebUsersResource::collection($webUsers)),

                WebUserTabsEnum::REQUESTS->value => $this->tab == WebUserTabsEnum::REQUESTS->value ?
                fn () => WebUserRequestsResource::collection(IndexWebUserRequests::run($this->parent, WebUserTabsEnum::REQUESTS->value))
                : Inertia::lazy(fn () => WebUserRequestsResource::collection(IndexWebUserRequests::run($this->parent, WebUserTabsEnum::REQUESTS->value))),

                'data'        => WebUsersResource::collection($webUsers),

            ]
        )->table(
            $this->tableStructure(parent: $this->parent, prefix: WebUserTabsEnum::WEB_USERS->value)
        )->table(
            IndexWebUserRequests::make()->tableStructure(parent: $this->parent, prefix: WebUserTabsEnum::REQUESTS->value)
        );
    }

    public function tableStructure(Group|Shop|Organisation|Customer|FulfilmentCustomer|Website $parent, ?array $modelOperations = null, $prefix = null, $canEdit = false): Closure
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
                                        'name'       => 'grp.org.shops.show.crm.customers.show.web-users.create',
                                        'parameters' => [$parent->organisation->slug, $parent->shop->slug, $parent->slug]
                                    ]
                                ] : null

                        ],
                        'FulfilmentCustomer' => [
                            'title'  => __("Customer don't have any login credentials"),
                            'count'  => $parent->customer->stats->number_web_users,
                            'action' => $canEdit && $parent->customer->stats->number_web_users == 0
                                ?
                                [
                                    'type'    => 'button',
                                    'style'   => 'create',
                                    'tooltip' => __('new website user'),
                                    'label'   => __('website user'),
                                    'route'   => [
                                        'name'       => 'grp.org.fulfilments.show.crm.customers.show.web-users.create',
                                        'parameters' => [$parent->organisation->slug, $parent->fulfilment->slug, $parent->slug]
                                    ]
                                ] : null

                        ],
                        default => null
                    }
                )
                ->column(key: 'username', label: __('username'), canBeHidden: false, sortable: true, searchable: true);
            if ($parent instanceof Group) {
                $table->column(key: 'organisation_name', label: __('organisation'), canBeHidden: false, sortable: true, searchable: true)
                    ->column(key: 'shop_name', label: __('shop'), canBeHidden: false, sortable: true, searchable: true);
            }
            $table
                ->column(key: 'email', label: __('email'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'created_at', label: __('Created at'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'action', label: __('Action'), canBeHidden: false, sortable: false, searchable: false)
                ->defaultSort('username');
        };
    }

    public function inGroup(ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = group();
        $this->initialisationFromGroup($this->parent, $request)->withTab(WebUserTabsEnum::values());

        return $this->handle(parent: $this->parent, prefix: WebUserTabsEnum::WEB_USERS->value);
    }

    public function inOrganisation(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request);

        return $this->handle(parent: $this->parent, prefix: WebUserTabsEnum::WEB_USERS->value);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inWebsite(Organisation $organisation, Shop $shop, Website $website, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $website;
        $this->initialisationFromShop($shop, $request)->withTab(WebUserTabsEnum::values());

        return $this->handle(parent: $website, prefix: WebUserTabsEnum::WEB_USERS->value);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilmentCustomer(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $fulfilmentCustomer;
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(WebUserTabsEnum::values());

        return $this->handle(parent: $fulfilmentCustomer, prefix: WebUserTabsEnum::WEB_USERS->value);
    }

    public function asController(Organisation $organisation, Shop $shop, Customer $customer, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $customer;
        $this->initialisationFromShop($shop, $request)->withTab(WebUserTabsEnum::values());

        return $this->handle(parent: $customer, prefix: WebUserTabsEnum::WEB_USERS->value);
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
            'grp.org.fulfilments.show.crm.customers.show.web-users.index' =>
            array_merge(
                ShowFulfilmentCustomer::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $routeParameters
                )
            ),
            'grp.org.shops.show.crm.customers.show.web-users.index' =>
            array_merge(
                ShowCustomer::make()->getBreadcrumbs('grp.org.shops.show.crm.customers.show', $routeParameters),
                $headCrumb(
                    $routeParameters
                )
            ),


            'grp.org.shops.show.web.websites.show.web-users.index' =>
            array_merge(
                (new ShowWebsite())->getBreadcrumbs(
                    scope: $routeName,
                    routeParameters: $routeParameters
                ),
                $headCrumb(
                    $routeParameters
                )
            ),
            'grp.overview.crm.web-users.index' =>
            array_merge(
                ShowGroupOverviewHub::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                )
            ),
            default => []
        };
    }
}
