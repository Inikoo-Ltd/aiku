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
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Http\Resources\Platform\ShopPlatformStatsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\Platform;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexPlatforms extends OrgAction
{
    use WithCRMAuthorisation;
    use WithCustomersSubNavigation;

    private Group|Shop $parent;

    public function handle(Group|Shop $parent, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Platform::class);

        $selects = [
            'id',
            'code',
            'slug',
            'name',
            'type',
        ];

        $timeSeriesData = $queryBuilder->withTimeSeriesAggregation(
            timeSeriesTable: 'platform_time_series',
            timeSeriesRecordsTable: 'platform_time_series_records',
            foreignKey: 'platform_id',
            aggregateColumns: [
                'channels'           => 'channels',
                'customers'          => 'customers',
                'portfolios'         => 'portfolios',
                'customer_clients'   => 'customer_clients',
                'sales_grp_currency' => 'sales',
            ],
            frequency: TimeSeriesFrequencyEnum::DAILY->value,
            includeLY: false,
            additionalFilters: $parent instanceof Shop
                ? ['shop_id' => $parent->id]
                : []
        );

        $selects[] = $timeSeriesData['selectRaw']['channels'];
        $selects[] = $timeSeriesData['selectRaw']['customers'];
        $selects[] = $timeSeriesData['selectRaw']['portfolios'];
        $selects[] = $timeSeriesData['selectRaw']['customer_clients'];
        $selects[] = $timeSeriesData['selectRaw']['sales'];

        return $queryBuilder
            ->allowedSorts(['id', 'code', 'slug', 'name', 'type', 'channels', 'customers', 'portfolios', 'customer_clients', 'sales'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?array $modelOperations = null, ?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix . 'Page');
            }

            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'channels', label: __('Channels'), canBeHidden: false, sortable: true)
                ->column(key: 'customers', label: __('Customers'), canBeHidden: false, sortable: true)
                ->column(key: 'portfolios', label: __('Portfolios'), canBeHidden: false, sortable: true)
                ->column(key: 'customer_clients', label: __('Clients'), canBeHidden: false, sortable: true)
                ->column(key: 'sales', label: __('Sales'), canBeHidden: false, sortable: true, align: 'right');
        };
    }

    public function htmlResponse(LengthAwarePaginator $platforms, ActionRequest $request): Response
    {
        $pageHead = [
            'title'         => __('Platforms'),
            'icon'          => [
                'title' => __('Platforms'),
                'icon'  => ['fal', 'fa-route'],
            ],
        ];

        if ($this->parent instanceof Shop) {
            $pageHead = [
                'title'         => __('Platforms'),
                'icon'          => [
                    'title' => __('Platforms'),
                    'icon'  => ['fal', 'fa-route'],
                ],
                'subNavigation' => $this->getSubNavigation($request),
            ];
        }

        return Inertia::render(
            'Org/Shop/CRM/Platforms',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'title'       => __('Platforms'),
                'pageHead'    => $pageHead,
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

    public function inGroup(ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = app('group');
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($this->group);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Platforms'),
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
            'grp.platforms.index' =>
                array_merge(
                    ShowGroupDashboard::make()->getBreadcrumbs(),
                    $headCrumb(
                        [
                            'name'       => 'grp.platforms.index',
                            'parameters' => $routeParameters
                        ]
                    )
                ),
            default => []
        };
    }
}
