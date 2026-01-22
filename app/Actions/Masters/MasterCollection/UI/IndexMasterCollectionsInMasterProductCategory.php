<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Jun 2025 00:01:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterCollection\UI;

use App\Actions\GrpAction;
use App\Actions\Masters\MasterProductCategory\UI\ShowMasterDepartment;
use App\Actions\Masters\MasterProductCategory\UI\ShowMasterSubDepartment;
use App\Actions\Masters\MasterProductCategory\WithMasterDepartmentSubNavigation;
use App\Actions\Masters\MasterProductCategory\WithMasterSubDepartmentSubNavigation;
use App\Actions\Traits\WithTab;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\UI\Catalogue\MasterCollectionsTabsEnum;
use App\Http\Resources\Masters\MasterCollectionsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Masters\MasterCollection;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexMasterCollectionsInMasterProductCategory extends GrpAction
{
    use WithMasterDepartmentSubNavigation;
    use WithMasterSubDepartmentSubNavigation;
    use WithTab;

    private MasterProductCategory $parent;

    public function handle(MasterProductCategory $parent, $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('collections.name', $value)
                    ->orWhereStartWith('collections.code', $value);
            });
        });

        $queryBuilder = QueryBuilder::for(MasterCollection::class);

        $selects = [
            'master_collections.id',
            'master_collections.code',
            'master_collections.state',
            'master_collections.name',
            'master_collections.description',
            'master_collections.created_at',
            'master_collections.updated_at',
            'master_collections.slug',
        ];

        if ($prefix === MasterCollectionsTabsEnum::SALES->value) {
            $queryBuilder->leftJoin('groups', 'master_collections.group_id', '=', 'groups.id')
                ->leftJoin('currencies', 'groups.currency_id', '=', 'currencies.id');


            $timeSeriesData = $queryBuilder->withTimeSeriesAggregation(
                timeSeriesTable: 'master_collection_time_series',
                timeSeriesRecordsTable: 'master_collection_time_series_records',
                foreignKey: 'master_collection_id',
                aggregateColumns: [
                    'sales_grp_currency' => 'sales',
                    'invoices'           => 'invoices'
                ],
                frequency: TimeSeriesFrequencyEnum::DAILY->value,
                prefix: $prefix,
                includeLY: true
            );
            $selects[] = 'currencies.code as currency_code';
            $selects[] = $timeSeriesData['selectRaw']['sales'];
            $selects[] = $timeSeriesData['selectRaw']['invoices'];
            $selects[] = $timeSeriesData['selectRaw']['sales_ly'];
            $selects[] = $timeSeriesData['selectRaw']['invoices_ly'];
        }


        $queryBuilder->join('model_has_master_collections', function ($join) {
            $join->on('model_has_master_collections.master_collection_id', '=', 'master_collections.id');
        });
        $queryBuilder->where('model_has_master_collections.model_id', $parent->id);
        $queryBuilder->where('model_has_master_collections.model_type', 'MasterProductCategory');

        $queryBuilder->leftjoin('master_collection_stats', 'master_collections.id', 'master_collection_stats.master_collection_id');

        $queryBuilder
            ->defaultSort('master_collections.code')
            ->select($selects);


        $allowedSorts = ['code', 'name'];
        if ($prefix === MasterCollectionsTabsEnum::SALES->value) {
            $allowedSorts = [
                'code',
                'name',
                'sales',
                'invoices'
            ];
        }

        return $queryBuilder
            ->allowedFilters([$globalSearch])
            ->allowedSorts($allowedSorts)
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(MasterProductCategory $masterProductCategory, ?array $modelOperations = null, $prefix = null): Closure
    {
        $sales = $prefix === MasterCollectionsTabsEnum::SALES->value;

        return function (InertiaTable $table) use ($masterProductCategory, $modelOperations, $prefix, $sales) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            if ($sales) {
                $table->betweenDates(['date']);
            }

            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState(
                    [
                        'title' => __("No collections in this product category"),
                        'count' => $masterProductCategory->stats->number_current_collections,
                    ]
                );

            if ($sales) {
                $table
                    ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                    ->column(key: 'sales', label: __('Sales'), canBeHidden: false, sortable: true, searchable: true, align: 'right')
                    ->column(key: 'sales_delta', label: __('Δ 1Y'), canBeHidden: false, sortable: false, searchable: false, align: 'right')
                    ->column(key: 'invoices', label: __('Invoices'), canBeHidden: false, sortable: true, searchable: true, align: 'right')
                    ->column(key: 'invoices_delta', label: __('Δ 1Y'), canBeHidden: false, sortable: false, searchable: false, align: 'right');
            } else {
                $table
                    ->column(key: 'status_icon', label: '', canBeHidden: false, type: 'icon')
                    ->column(key: 'image_thumbnail', label: '', type: 'avatar')
                    ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                    ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);
                $table->column(key: 'actions', label: '', searchable: true);
            }
        };
    }

    public function jsonResponse(LengthAwarePaginator $masterCollections): AnonymousResourceCollection
    {
        return MasterCollectionsResource::collection($masterCollections);
    }

    public function htmlResponse(LengthAwarePaginator $masterCollections, ActionRequest $request): Response
    {
        $masterProductCategory = $this->parent;
        $container = null;


        $subNavigation = null;


        $icon = [
            'icon'  => ['fal', 'fa-cube'],
            'title' => __('Collections')
        ];


        $title = $masterProductCategory->name;
        $iconRight = [];

        $afterTitle = [
            'label' => __('Master Collections')
        ];
        $model = '';
        if ($masterProductCategory->type == MasterProductCategoryTypeEnum::DEPARTMENT) {
            $icon = [
                'icon'  => ['fal', 'fa-folder-tree'],
                'title' => __('Master department')
            ];
            $subNavigation = $this->getMasterDepartmentSubNavigation($masterProductCategory);
        } elseif ($masterProductCategory->type == MasterProductCategoryTypeEnum::SUB_DEPARTMENT) {
            $icon = [
                'icon'  => ['fal', 'fa-dot-circle'],
                'title' => __('Master sub department')
            ];
            $subNavigation = $this->getMasterSubDepartmentSubNavigation($masterProductCategory);
        }


        $actions = array_values(array_filter([
            ...(function () use ($request) {
                $routes = [
                    'grp.masters.master_shops.show.master_departments.show.master_collections.index' => 'grp.masters.master_shops.show.master_departments.show.master_collections.create',
                ];

                $currentRoute = $request->route()->getName();

                if (!isset($routes[$currentRoute])) {
                    return [];
                }

                return [
                    [
                        'type'    => 'button',
                        'style'   => 'create',
                        'tooltip' => __('New collection'),
                        'label'   => __('Collection'),
                        'route'   => [
                            'name'       => $routes[$currentRoute],
                            'parameters' => $request->route()->originalParameters()
                        ]
                    ]
                ];
            })(),


        ]));


        return Inertia::render(
            'Masters/MasterCollections',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $this->parent,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Master Collections'),
                'pageHead'    => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'container'     => $container,
                    'actions'       => $actions,
                    'subNavigation' => $subNavigation,
                ],
                'routes'      => null,

                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => MasterCollectionsTabsEnum::navigation(),
                ],

                MasterCollectionsTabsEnum::INDEX->value => $this->tab == MasterCollectionsTabsEnum::INDEX->value ?
                    MasterCollectionsResource::collection($masterCollections)
                    : Inertia::lazy(fn () => MasterCollectionsResource::collection(IndexMasterCollectionsInMasterProductCategory::run($this->parent, prefix: MasterCollectionsTabsEnum::INDEX->value))),

                MasterCollectionsTabsEnum::SALES->value => $this->tab == MasterCollectionsTabsEnum::SALES->value ?
                    MasterCollectionsResource::collection($masterCollections)
                    : Inertia::lazy(fn () => MasterCollectionsResource::collection(IndexMasterCollectionsInMasterProductCategory::run($this->parent, prefix: MasterCollectionsTabsEnum::SALES->value))),


            ]
        )->table($this->tableStructure($this->parent, prefix: MasterCollectionsTabsEnum::INDEX->value))
            ->table($this->tableStructure($this->parent, prefix: MasterCollectionsTabsEnum::SALES->value));
    }


    public function inMasterDepartmentInMasterShop(MasterShop $masterShop, MasterProductCategory $masterDepartment, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterDepartment;
        $this->initialisation($masterShop->group, $request)
            ->withTab(MasterCollectionsTabsEnum::values());

        return $this->handle($masterDepartment, prefix: MasterCollectionsTabsEnum::INDEX->value);
    }

    public function inMasterSubDepartmentInMasterShop(MasterShop $masterShop, MasterProductCategory $masterSubDepartment, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterSubDepartment;
        $this->initialisation($masterShop->group, $request)
            ->withTab(MasterCollectionsTabsEnum::values());

        return $this->handle($masterSubDepartment, prefix: MasterCollectionsTabsEnum::INDEX->value);
    }

    public function getBreadcrumbs(MasterProductCategory $parent, string $routeName, array $routeParameters, string $suffix = null): array
    {
        $headCrumb = function (array $routeParameters, ?string $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Master collections'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ]
            ];
        };

        return match ($routeName) {

            'grp.masters.master_shops.show.master_departments.show.master_collections.index',
            'grp.masters.master_shops.show.master_departments.show.master_collections.show' =>
            array_merge(
                ShowMasterDepartment::make()->getBreadcrumbs(
                    $parent->masterShop,
                    $parent,
                    $routeName,
                    $routeParameters
                ),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                    $suffix
                )
            ),
            'grp.masters.master_shops.show.master_sub_departments.master_collections.index',
            'grp.masters.master_shops.show.master_sub_departments.master_collections.show' =>
            array_merge(
                ShowMasterSubDepartment::make()->getBreadcrumbs(
                    $parent,
                    $routeName,
                    $routeParameters
                ),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                    $suffix
                )
            ),


            default => []
        };
    }
}
