<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 29 Dec 2024 03:09:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Actions\Goods\UI\WithMasterCatalogueSubNavigation;
use App\Actions\Masters\MasterShop\UI\ShowMasterShop;
use App\Actions\Masters\UI\ShowMastersDashboard;
use App\Actions\OrgAction;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\UI\Catalogue\MasterDepartmentsTabsEnum;
use App\Http\Resources\Masters\MasterDepartmentsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexMasterDepartments extends OrgAction
{
    use WithMasterCatalogueSubNavigation;

    private MasterShop|Group $parent;

    public function asController(MasterShop $masterShop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterShop;
        $group        = group();
        $this->initialisationFromGroup($group, $request)->withTab(MasterDepartmentsTabsEnum::values());

        return $this->handle(parent: $masterShop, prefix: MasterDepartmentsTabsEnum::INDEX->value);
    }

    public function inGroup(ActionRequest $request): LengthAwarePaginator
    {
        $group        = group();
        $this->parent = $group;
        $this->initialisationFromGroup($group, $request)->withTab(MasterDepartmentsTabsEnum::values());

        return $this->handle(parent: $group, prefix: MasterDepartmentsTabsEnum::INDEX->value);
    }

    public function handle(Group|MasterShop $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('master_product_categories.name', $value)
                    ->orWhereStartWith('master_product_categories.slug', $value);
            });
        });
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(MasterProductCategory::class);
        $queryBuilder->where('master_product_categories.type', ProductCategoryTypeEnum::DEPARTMENT);

        $queryBuilder->leftJoin('master_product_category_stats', 'master_product_categories.id', '=', 'master_product_category_stats.master_product_category_id');

        $selects = [
            'master_product_categories.id',
            'master_product_categories.slug',
            'master_product_categories.code',
            'master_product_categories.name',
            'master_product_categories.status',
            'master_product_categories.description',
            'master_product_categories.created_at',
            'master_product_categories.updated_at',
            'master_product_categories.web_images',
            'master_product_category_stats.number_current_departments as used_in',
            'master_product_category_stats.number_current_master_product_categories_type_family as families',
            'master_product_category_stats.number_current_master_assets_type_product as products',
            'master_product_category_stats.number_current_master_product_categories_type_sub_department as sub_departments',
            'master_product_category_stats.number_collections_state_active as collections',
            'currencies.code as currency_code',
        ];

        if ($prefix === MasterDepartmentsTabsEnum::SALES->value) {
            // Use reusable time series aggregation method
            $timeSeriesData = $queryBuilder->withTimeSeriesAggregation(
                timeSeriesTable: 'master_product_category_time_series',
                timeSeriesRecordsTable: 'master_product_category_time_series_records',
                foreignKey: 'master_product_category_id',
                aggregateColumns: [
                    'sales_grp_currency' => 'sales',
                    'invoices'           => 'invoices'
                ],
                frequency: TimeSeriesFrequencyEnum::DAILY->value,
                prefix: $prefix,
                includeLY: true
            );

            $selects[] = $timeSeriesData['selectRaw']['sales'];
            $selects[] = $timeSeriesData['selectRaw']['invoices'];
            $selects[] = $timeSeriesData['selectRaw']['sales_ly'];
            $selects[] = $timeSeriesData['selectRaw']['invoices_ly'];

            $queryBuilder->groupBy([
                'master_product_categories.id',
                'master_product_categories.slug',
                'master_product_categories.code',
                'master_product_categories.name',
                'master_product_categories.status',
                'master_product_categories.description',
                'master_product_categories.created_at',
                'master_product_categories.updated_at',
                'master_product_categories.web_images',
                'master_product_category_stats.number_current_departments',
                'master_product_category_stats.number_current_master_product_categories_type_family',
                'master_product_category_stats.number_current_master_assets_type_product',
                'master_product_category_stats.number_current_master_product_categories_type_sub_department',
                'master_product_category_stats.number_collections_state_active',
                'currencies.code',
            ]);
        }

        $queryBuilder->select($selects);

        if ($parent instanceof MasterShop) {
            $queryBuilder->where('master_product_categories.master_shop_id', $parent->id);
            $queryBuilder->leftJoin('master_shops', 'master_product_categories.master_shop_id', '=', 'master_shops.id');
            $queryBuilder->leftJoin('groups', 'master_shops.group_id', '=', 'groups.id');
            $queryBuilder->leftJoin('currencies', 'groups.currency_id', '=', 'currencies.id');
        } else {
            $queryBuilder->where('master_product_categories.group_id', $parent->id);
            $queryBuilder->leftJoin('master_shops', 'master_shops.id', 'master_product_categories.master_shop_id');
            $queryBuilder->leftJoin('groups', 'master_shops.group_id', '=', 'groups.id');
            $queryBuilder->leftJoin('currencies', 'groups.currency_id', '=', 'currencies.id');
            $queryBuilder->addSelect([
                'master_shops.slug as master_shop_slug',
                'master_shops.code as master_shop_code',
                'master_shops.name as master_shop_name',
            ]);
            if ($prefix === MasterDepartmentsTabsEnum::SALES->value) {
                $queryBuilder->groupBy([
                    'master_shops.slug',
                    'master_shops.code',
                    'master_shops.name',
                ]);
            }
        }

        return $queryBuilder
            ->defaultSort('master_product_categories.code')
            ->allowedSorts([
                'code', 'name', 'used_in', 'sub_departments', 'collections', 'families', 'products',
                'sales',
                'invoices',
            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Group|MasterShop $parent, ?array $modelOperations = null, $prefix = null, $sales = false): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix, $parent, $sales) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            if ($sales) {
                $table->betweenDates(['date']);
            }

            $table
                ->defaultSort('code')
                ->withLabelRecord([__('master department'), __('master departments')])
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState(
                    [
                        'title' => __("No master departments found"),
                    ],
                );


            if ($parent instanceof Group) {
                $table->column('master_shop_code', __('M. Shop'), sortable: true);
            }

            if ($sales) {
                $table->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                    ->column(key: 'sales', label: __('Sales'), canBeHidden: false, sortable: true, searchable: true, align: 'right')
                    ->column(key: 'sales_delta', label: __('Δ 1Y'), canBeHidden: false, sortable: false, searchable: false, align: 'right')
                    ->column(key: 'invoices', label: __('Invoices'), canBeHidden: false, sortable: true, searchable: true, align: 'right')
                    ->column(key: 'invoices_delta', label: __('Δ 1Y'), canBeHidden: false, sortable: false, searchable: false, align: 'right');
            } else {
                $table
                    ->column(key: 'image_thumbnail', label: '', type: 'avatar')
                    ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                    ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                    ->column(key: 'used_in', label: __('Used in'), tooltip: __('Current shops with this master'), canBeHidden: false, sortable: true, searchable: true)
                    ->column(key: 'sub_departments', label: __('M. Sub-departments'), tooltip: __('current sub departments'), canBeHidden: false, sortable: true, searchable: true)
                    ->column(key: 'collections', label: __('M. Collections'), tooltip: __('current collections'), canBeHidden: false, sortable: true, searchable: true)
                    ->column(key: 'families', label: __('M. Families'), tooltip: __('current master families'), canBeHidden: false, sortable: true, searchable: true)
                    ->column(key: 'products', label: __('M. Products'), tooltip: __('current master products'), canBeHidden: false, sortable: true, searchable: true);
            }
        };
    }

    public function jsonResponse(LengthAwarePaginator $masterDepartments): AnonymousResourceCollection
    {
        return MasterDepartmentsResource::collection($masterDepartments);
    }

    public function htmlResponse(LengthAwarePaginator $masterDepartments, ActionRequest $request): Response
    {
        $navigation = MasterDepartmentsTabsEnum::navigation();

        $model = '';
        if ($this->parent instanceof MasterShop) {
            $subNavigation = $this->getMasterShopNavigation($this->parent);
            $title         = $this->parent->name;

            $icon       = [
                'icon'  => ['fal', 'fa-store-alt'],
                'title' => __('Master shop')
            ];
            $afterTitle = [
                'label' => __('Master Departments')
            ];
            $iconRight  = [
                'icon' => 'fal fa-folder-tree',
            ];
        } else {
            $title         = __('Master departments');
            $icon          = [
                'icon'  => ['fal', 'fa-folder-tree'],
                'title' => $title
            ];
            $afterTitle    = [
                'label' => __('In group')
            ];
            $iconRight     = [
                'icon' => 'fal fa-city',
            ];
            $subNavigation = null;
        }

        $actions = [];
        if ($request->route()->getName() == 'grp.masters.master_shops.show.master_departments.index') {
            $actions = [
                [
                    'type'    => 'button',
                    'style'   => 'create',
                    'tooltip' => __('New Master Department'),
                    'label'   => __('Master Department'),
                    'route'   => match ($this->parent::class) {
                        MasterProductCategory::class => [
                            'name'       => 'grp.masters.master_departments.create',
                            'parameters' => $request->route()->originalParameters()
                        ],
                        default => [
                            'name'       => 'grp.masters.master_shops.show.master_departments.create',
                            'parameters' => $request->route()->originalParameters()
                        ]
                    }
                ],
            ];
        }


        return Inertia::render(
            'Masters/MasterDepartments',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $this->parent,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => $title,
                'pageHead'    => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'actions'       => $actions,
                    'subNavigation' => $subNavigation,
                ],
                'tabs'                                => [
                    'current'    => $this->tab,
                    'navigation' => $navigation,
                ],
                MasterDepartmentsTabsEnum::INDEX->value => $this->tab == MasterDepartmentsTabsEnum::INDEX->value ?
                    fn () => MasterDepartmentsResource::collection($masterDepartments)
                    : Inertia::lazy(fn () => MasterDepartmentsResource::collection(IndexMasterDepartments::run($this->parent, prefix: MasterDepartmentsTabsEnum::INDEX->value))),

                MasterDepartmentsTabsEnum::SALES->value => $this->tab == MasterDepartmentsTabsEnum::SALES->value ?
                    fn () => MasterDepartmentsResource::collection(IndexMasterDepartments::run($this->parent, prefix: MasterDepartmentsTabsEnum::SALES->value))
                    : Inertia::lazy(fn () => MasterDepartmentsResource::collection(IndexMasterDepartments::run($this->parent, prefix: MasterDepartmentsTabsEnum::SALES->value))),
            ]
        )->table($this->tableStructure($this->parent, prefix: MasterDepartmentsTabsEnum::INDEX->value))
            ->table($this->tableStructure($this->parent, prefix: MasterDepartmentsTabsEnum::SALES->value, sales: true));
    }

    public function getBreadcrumbs(MasterShop|Group $parent, string $routeName, array $routeParameters, string $suffix = null): array
    {
        $headCrumb = function (array $routeParameters, ?string $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Master departments'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ]
            ];
        };

        return match ($routeName) {
            'grp.masters.master_departments.index' =>
            array_merge(
                ShowMastersDashboard::make()->getBreadcrumbs(),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                    $suffix
                )
            ),
            'grp.masters.master_shops.show.master_departments.index' =>
            array_merge(
                ShowMasterShop::make()->getBreadcrumbs($parent),
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
