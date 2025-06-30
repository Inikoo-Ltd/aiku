<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Jun 2025 18:38:03 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\WebUser;

use App\Actions\Fulfilment\Fulfilment\UI\ShowFulfilment;
use App\Actions\Fulfilment\FulfilmentCustomer\ShowFulfilmentCustomer;
use App\Actions\Fulfilment\WithFulfilmentCustomerSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithFulfilmentShopAuthorisation;
use App\Actions\Traits\WithFulfilmentCustomersSubNavigation;
use App\Http\Resources\CRM\WebUsersResource;
use App\InertiaTable\InertiaTable;
use App\Models\CRM\WebUser;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexWebUsersInFulfilmentCRM extends OrgAction
{
    use WithFulfilmentShopAuthorisation;
    use WithFulfilmentCustomersSubNavigation;
    use WithFulfilmentCustomerSubNavigation;


    private Fulfilment|FulfilmentCustomer $parent;

    public function handle(Fulfilment|FulfilmentCustomer $parent, $prefix = null): LengthAwarePaginator
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
        if ($parent instanceof FulfilmentCustomer) {
            $queryBuilder->where('customer_id', $parent->customer_id);
        } else {
            $queryBuilder->where('shop_id', $parent->shop->id);
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
            ->allowedSorts(['email', 'username', 'created_at'])
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
        } else {
            $subNavigation = $this->getSubNavigation($this->parent, $request);
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
                        ($this->canEdit && ($this->parent instanceof FulfilmentCustomer)) ? [
                            'type'  => 'button',
                            'style' => 'create',
                            'label' => __('website user'),
                            'route' => [
                                'name'       => 'grp.org.fulfilments.show.crm.customers.show.web_users.create',
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ] : false
                    ]
                ],
                'data'        => WebUsersResource::collection($webUsers),
            ]
        )->table($this->tableStructure());
    }

    public function tableStructure($prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }
            $table
                ->withGlobalSearch()
                ->withEmptyState()
                ->column(key: 'username', label: __('username'), canBeHidden: false, sortable: true, searchable: true);
            $table
                ->column(key: 'email', label: __('email'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'created_at', label: __('Created at'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'action', label: __('Action'), canBeHidden: false)
                ->defaultSort('username');
        };
    }


    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $fulfilment;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle(parent: $fulfilment);
    }

    public function asController(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $fulfilmentCustomer;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle(parent: $fulfilmentCustomer);
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
            'grp.org.fulfilments.show.crm.web_users.index' =>
            array_merge(
                ShowFulfilment::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $routeParameters
                )
            ),
            'grp.org.fulfilments.show.crm.customers.show.web_users.index' =>
            array_merge(
                ShowFulfilmentCustomer::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $routeParameters
                )
            ),


            default => []
        };
    }
}
