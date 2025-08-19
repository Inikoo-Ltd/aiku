<?php

/*
 * author Arya Permana - Kirin
 * created on 04-11-2024-13h-41m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Ordering\Purge\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\Ordering\Order\WithOrdersSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Http\Resources\Ordering\PurgesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Purge;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexPurges extends OrgAction
{
    use WithOrdersSubNavigation;
    private Shop|Organisation|Group $parent;

    public function handle(Shop|Organisation|Group $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('purges.id', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(Purge::class);


        if ($parent instanceof Shop) {
            $query->where('shop_id', $parent->id);
        } elseif ($parent instanceof Group) {
            $query->where('purges.group_id', $parent->id);
        } else {
            abort(419);
        }
        $query->leftjoin('purge_stats', 'purge_stats.purge_id', '=', 'purges.id');
        $query->leftjoin('organisations', 'purges.organisation_id', '=', 'organisations.id');
        $query->leftjoin('shops', 'purges.shop_id', '=', 'shops.id');

        return $query->defaultSort('purges.id')
            ->select([
                'purges.id',
                'purges.state',
                'purges.type',
                'purges.scheduled_at',
                'purges.start_at',
                'purges.end_at',
                'purges.cancelled_at',
                'purges.inactive_days',
                'purge_stats.estimated_number_orders',
                'purge_stats.estimated_number_transactions',
                'purge_stats.estimated_net_amount',
                'shops.id as shop_id',
                'shops.name as shop_name',
                'shops.slug as shop_slug',
                'organisations.name as organisation_name',
                'organisations.slug as organisation_slug',
            ])
            ->allowedSorts(['id', 'scheduled_at', 'state', 'type', 'estimated_number_orders', 'estimated_number_transactions', 'estimated_net_amount'])
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


            $noResults = __("No purges found");

            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title' => $noResults,
                    ]
                );


            $table->column(key: 'state', label: __('state'), sortable: true, canBeHidden: false, searchable: true);
            $table->column(key: 'scheduled_at', label: __('date'), sortable: true, canBeHidden: false, searchable: true);
            $table->column(key: 'type', label: __('type'), sortable: true, canBeHidden: false, searchable: true);
            if ($parent instanceof Group) {
                $table->column(key: 'organisation_name', label: __('organisation'), canBeHidden: false, searchable: true);
                $table->column(key: 'shop_name', label: __('shop'), canBeHidden: false, searchable: true);
            }
            $table->column(key: 'estimated_number_orders', label: __('orders'), sortable: true, canBeHidden: false, searchable: true);
            $table->column(key: 'estimated_number_transactions', label: __('transactions'), sortable: true, canBeHidden: false, searchable: true);
            $table->column(key: 'estimated_net_amount', label: __('amount'), sortable: true, canBeHidden: false, searchable: true, align: 'right');
        };
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->parent instanceof Group) {
            return $request->user()->authTo("group-overview");
        }

        return $request->user()->authTo("orders.{$this->shop->id}.view");
    }


    public function jsonResponse(LengthAwarePaginator $purges): AnonymousResourceCollection
    {
        return PurgesResource::collection($purges);
    }


    public function htmlResponse(LengthAwarePaginator $purges, ActionRequest $request): Response
    {
        $subNavigation = null;
        $title      = __('Purges');
        $model      = '';
        $icon       = [
            'icon'  => ['fal', 'fa-trash-alt'],
            'title' => __('Purges')
        ];
        $afterTitle = null;
        $iconRight  = null;
        $actions    = [
            [
                'type'    =>    'button',
                                'style'   => 'create',
                                'tooltip' => __('new purge'),
                                'label'   => __('purge'),
                                'route'   => [
                                    'name'       => 'grp.org.shops.show.ordering.purges.create',
                                    'parameters' => $request->route()->originalParameters()
                                ]
            ]
        ];

        if ($this->parent instanceof Shop) {
            $subNavigation = $this->getOrdersNavigation($this->parent);
        }

        return Inertia::render(
            'Org/Ordering/Purges',
            [
                'breadcrumbs'                                => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters(),
                ),
                'title'                                      => __('purges'),
                'pageHead'                                   => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'subNavigation' => $subNavigation,
                    'actions'       => $actions
                ],
                'data'              => PurgesResource::collection($purges),
            ]
        )->table($this->tableStructure(parent: $this->parent));
    }


    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    public function inGroup(ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = group();
        $this->initialisationFromGroup(group(), $request);

        return $this->handle(group());
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Purges'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.shops.show.ordering.purges.index' =>
            array_merge(
                ShowShop::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.org.shops.show.ordering.purges.index',
                        'parameters' => $routeParameters
                    ]
                )
            ),
            'grp.overview.ordering.purges.index' =>
            array_merge(
                ShowGroupOverviewHub::make()->getBreadcrumbs(
                    $routeParameters
                ),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ]
                )
            ),
            default => []
        };
    }
}
