<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 24 Jul 2025 15:58:43 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Platform\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Actions\Traits\WithCustomersSubNavigation;
use App\Http\Resources\Platform\ShopPlatformStatsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Catalogue\ShopPlatformStats;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\Sorts\Sort;
use Illuminate\Database\Eloquent\Builder;

class IndexPlatforms extends OrgAction
{
    use WithCustomersSubNavigation;
    use WithCRMAuthorisation;

    private Shop|Organisation $parent;

    public function handle(Shop $shop, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('platforms.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(ShopPlatformStats::class)
            ->join('platforms', 'shop_platform_stats.platform_id', '=', 'platforms.id')
            ->select('shop_platform_stats.*', 'platforms.name as platform_name')
            ->where('shop_platform_stats.shop_id', $shop->id);

        return $queryBuilder
            ->allowedSorts([
                'id',
                'number_customer_sales_channels',
                'number_products',
                'number_orders',
                'sales',
                AllowedSort::custom('name', new class implements Sort {
                    public function __invoke(Builder $query, bool $descending, string $property)
                    {
                        $direction = $descending ? 'desc' : 'asc';
                        $query->orderBy('platforms.name', $direction);
                    }
                })
            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix . 'Page');
            }

            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations);

            $table
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_customer_sales_channels', label: __('Channels'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_products', label: __('Portfolios'), canBeHidden: false, sortable: true)
                ->column(key: 'number_orders', label: __('Orders'), canBeHidden: false, sortable: true)
                ->column(key: 'sales', label: __('Sales'), canBeHidden: false, sortable: true, align: 'right');
        };
    }

    public function htmlResponse(LengthAwarePaginator $platforms, ActionRequest $request): Response
    {
        $subNavigation = null;
        if ($this->parent instanceof Shop) {
            $subNavigation = $this->getSubNavigation($request);
        }
        $title      = __('Platforms');
        $model      = __('Platforms');
        $icon       = [
            'icon'  => ['fal', 'fa-route'],
            'title' => __('platforms')
        ];
        $afterTitle = null;
        $iconRight  = null;

        if ($this->parent instanceof Shop) {
            $title      = $this->parent->name;
            $model      = __('platforms');
            $icon       = [
                'icon'  => ['fal', 'fa-route'],
                'title' => __('platforms')
            ];
            $iconRight  = [
                'icon' => 'fal fa-route',
            ];
            $afterTitle = [
                'label' => __('platforms')
            ];
        }

        $action = [];


        return Inertia::render(
            'Org/Shop/CRM/Platforms',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('platforms'),
                'pageHead'    => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'subNavigation' => $subNavigation,
                    'actions'       => $action,
                ],
                'data'        => ShopPlatformStatsResource::collection($platforms),
            ]
        )->table($this->tableStructure());
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
                        'label' => __('platforms'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.shops.show.crm.platforms.index' =>
            array_merge(
                ShowShop::make()->getBreadcrumbs(
                    $routeParameters
                ),
                $headCrumb(
                    [
                        'name'       => 'grp.org.shops.show.crm.platforms.index',
                        'parameters' => $routeParameters
                    ]
                )
            ),
            default => []
        };
    }
}
