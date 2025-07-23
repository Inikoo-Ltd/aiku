<?php

namespace App\Actions\CRM\TrafficSource\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Actions\Traits\WithCustomersSubNavigation;
use App\Http\Resources\CRM\TrafficSourcesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use App\Models\CRM\TrafficSource;

class IndexTrafficSources extends OrgAction
{
    use WithCustomersSubNavigation;
    use WithCRMAuthorisation;

    private Shop|Organisation $parent;

    public function handle(Shop|Organisation $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('traffic_sources.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(TrafficSource::class);

        if ($parent instanceof Organisation) {
            $queryBuilder->where('traffic_sources.organisation_id', $parent->id);
            $queryBuilder->leftJoin('organisations', 'organisations.id', '=', 'traffic_sources.organisation_id');
            $queryBuilder->leftJoin('currencies', 'currencies.id', '=', 'organisations.currency_id');
        } else {
            $queryBuilder->where('traffic_sources.shop_id', $parent->id);
            $queryBuilder->leftJoin('shops', 'shops.id', '=', 'traffic_sources.shop_id');
            $queryBuilder->leftJoin('currencies', 'currencies.id', '=', 'shops.currency_id');
        }

        $queryBuilder->leftJoin('traffic_source_stats', function ($join) {
            $join->on('traffic_sources.id', '=', 'traffic_source_stats.traffic_source_id');
        });



        $selectFields = [
            'traffic_sources.id',
            'traffic_sources.slug',
            'traffic_sources.name',
            'traffic_source_stats.number_customers',
            'traffic_source_stats.number_customer_purchases',
            'traffic_source_stats.total_customer_revenue',
            'currencies.code as currency_code',
        ];

        $groupByFields = [
            'traffic_sources.id',
            'traffic_source_stats.id',
            'currencies.id'
        ];

        $queryBuilder
            ->defaultSort('traffic_sources.id')
            ->select($selectFields)
            ->groupBy($groupByFields);

        $allowedSorts = [
            'name',
            'number_customers',
            'number_customer_purchases',
            'total_customer_revenue',
        ];

        return $queryBuilder
            ->allowedSorts($allowedSorts)
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(
        Shop|Organisation $parent,
        ?array $modelOperations = null,
        $prefix = null,
    ): Closure {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix . 'Page');
            }

            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations);

            $table
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_customers', label: __('Customers'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_customer_purchases', label: __('Purchases'), canBeHidden: false, sortable: true)
                ->column(key: 'total_customer_revenue', label: __('Total Revenue'), canBeHidden: false, sortable: true, type: 'currency');
        };
    }

    public function htmlResponse(LengthAwarePaginator $trafficSources, ActionRequest $request): Response
    {
        $subNavigation = null;
        if ($this->parent instanceof Shop) {
            $subNavigation = $this->getSubNavigation($request);
        }
        $title      = __('Traffic Sources');
        $model      = __('Traffic Source');
        $icon       = [
            'icon'  => ['fal', 'fa-route'],
            'title' => __('traffic sources')
        ];
        $afterTitle = null;
        $iconRight  = null;

        if ($this->parent instanceof Shop) {
            $title      = $this->parent->name;
            $model      = __('traffic source');
            $icon       = [
                'icon'  => ['fal', 'fa-route'],
                'title' => __('traffic source')
            ];
            $iconRight  = [
                'icon' => 'fal fa-route',
            ];
            $afterTitle = [
                'label' => __('Traffic Sources')
            ];
        }

        $action = [];


        return Inertia::render(
            'Org/Shop/CRM/TrafficSources',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Traffic Sources'),
                'pageHead'    => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'subNavigation' => $subNavigation,
                    'actions'       => $action,
                ],
                'data'        => TrafficSourcesResource::collection($trafficSources), // You may want to use a resource if needed
            ]
        )->table($this->tableStructure($this->parent));
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Traffic Sources'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.shops.show.crm.traffic_sources.index' =>
            array_merge(
                ShowShop::make()->getBreadcrumbs(
                    $routeParameters
                ),
                $headCrumb(
                    [
                        'name'       => 'grp.org.shops.show.crm.traffic_sources.index',
                        'parameters' => $routeParameters
                    ]
                )
            ),
            default => []
        };
    }
}
