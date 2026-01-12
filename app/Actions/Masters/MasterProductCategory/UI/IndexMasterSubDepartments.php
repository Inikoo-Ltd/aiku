<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 29 Dec 2024 03:11:24 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Actions\Goods\UI\WithMasterCatalogueSubNavigation;
use App\Actions\GrpAction;
use App\Actions\Masters\MasterProductCategory\WithMasterDepartmentSubNavigation;
use App\Actions\Masters\MasterShop\UI\ShowMasterShop;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\UI\Catalogue\MasterProductCategoryTabsEnum;
use App\Http\Resources\Masters\MasterSubDepartmentsResource;
use App\InertiaTable\InertiaTable;
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

class IndexMasterSubDepartments extends GrpAction
{
    use WithMasterCatalogueSubNavigation;
    use WithMasterDepartmentSubNavigation;

    private MasterShop|MasterProductCategory $parent;

    public function asController(MasterShop $masterShop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterShop;
        $group        = group();
        $this->initialisation($group, $request)->withTab(MasterProductCategoryTabsEnum::values());

        return $this->handle(parent: $masterShop, prefix: MasterProductCategoryTabsEnum::INDEX->value);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterDepartment(MasterShop $masterShop, MasterProductCategory $masterDepartment, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterDepartment;
        $group        = group();
        $this->initialisation($group, $request)->withTab(MasterProductCategoryTabsEnum::values());

        return $this->handle(parent: $masterDepartment, prefix: MasterProductCategoryTabsEnum::INDEX->value);
    }

    public function handle(MasterShop|MasterProductCategory $parent, $prefix = null): LengthAwarePaginator
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
        $queryBuilder->leftJoin('master_product_category_stats', 'master_product_categories.id', '=', 'master_product_category_stats.master_product_category_id');

        // Joins for currency code and sales aggregation
        $queryBuilder->leftJoin('master_shops', 'master_product_categories.master_shop_id', '=', 'master_shops.id');
        $queryBuilder->leftJoin('groups', 'master_shops.group_id', '=', 'groups.id');
        $queryBuilder->leftJoin('currencies', 'groups.currency_id', '=', 'currencies.id');

        if ($parent instanceof MasterShop) {
            $queryBuilder->where('master_product_categories.master_shop_id', $parent->id);
        } else {
            $queryBuilder->where('master_product_categories.master_parent_id', $parent->id);
        }

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
            'master_product_category_stats.number_current_master_product_categories_type_family as number_families',
            'master_product_category_stats.number_current_master_assets_type_product as number_products',
            'currencies.code as currency_code',
        ];

        if ($prefix === MasterProductCategoryTabsEnum::SALES->value) {
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
        }

        $queryBuilder->select($selects);

        return $queryBuilder
            ->where('master_product_categories.type', ProductCategoryTypeEnum::SUB_DEPARTMENT)
            ->defaultSort('master_product_categories.code')
            ->allowedSorts([
                'code',
                'name',
                'number_families',
                'number_products',
                'sales',
                'invoices'
            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(MasterShop|MasterProductCategory $parent, $prefix = null, $sales = false): Closure
    {
        return function (InertiaTable $table) use ($prefix, $parent, $sales) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix . 'Page');
            }
            if ($sales) {
                $table->betweenDates(['date']);
            }
            class_basename($parent);
            $table
                ->defaultSort('code')
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title' => __("No sub departments found"),
                        'count' => $parent->stats->number_master_product_categories_type_sub_department ?? 0,
                    ]
                );

            if ($sales) {
                $table->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                    ->column(key: 'sales', label: __('Sales'), canBeHidden: false, sortable: true, searchable: true, align: 'right')
                    ->column(key: 'sales_delta', label: __('Δ 1Y'), canBeHidden: false, sortable: false, searchable: false, align: 'right')
                    ->column(key: 'invoices', label: __('Invoices'), canBeHidden: false, sortable: true, searchable: true, align: 'right')
                    ->column(key: 'invoices_delta', label: __('Δ 1Y'), canBeHidden: false, sortable: false, searchable: false, align: 'right');
            } else {
                $table
                    ->column(key: 'status_icon', label: '', canBeHidden: false, searchable: true, type: 'icon')
                    ->column(key: 'image_thumbnail', label: '', type: 'avatar')
                    ->column(key: 'code', label: __('Code'), sortable: true, searchable: true)
                    ->column(key: 'name', label: __('Name'), sortable: true, searchable: true)
                    ->column(key: 'number_families', label: __('M. Families'), sortable: true)
                    ->column(key: 'number_products', label: __('M. Products'), sortable: true);
            }
        };
    }

    public function jsonResponse(LengthAwarePaginator $masterSubDepartments): AnonymousResourceCollection
    {
        return MasterSubDepartmentsResource::collection($masterSubDepartments);
    }

    public function htmlResponse(LengthAwarePaginator $masterSubDepartments, ActionRequest $request): Response
    {
        $navigation = MasterProductCategoryTabsEnum::navigation();

        $subNavigation = null;
        $modelNavigation = [];
        if ($this->parent instanceof MasterShop) {
            $subNavigation = $this->getMasterShopNavigation($this->parent);
        } elseif ($this->parent instanceof MasterProductCategory) {
            $subNavigation = $this->getMasterDepartmentSubNavigation($this->parent);
            $modelNavigation = GetMasterDepartmentNavigation::run($this->parent, $request);

        }
        $title      = $this->parent->name;
        $model      = '';
        $icon       = [
            'icon'  => ['fal', 'fa-store-alt'],
            'title' => __('Master shop')
        ];
        $afterTitle = [
            'label' => __('Master Sub Departments')
        ];
        $iconRight  = [
            'icon' => 'fal fa-folder-download',
        ];

        return Inertia::render(
            'Masters/MasterSubDepartments',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $this->parent,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => $modelNavigation,
                'title'       => __('Master Sub Departments'),
                'pageHead'    => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'actions'       => [
                        [
                            'type'    => 'button',
                            'style'   => 'create',
                            'tooltip' => __('New master sub-department'),
                            'label'   => __('Sub-department'),
                            'route'   => match ($this->parent::class) {
                                MasterProductCategory::class => [
                                    'name'       => 'grp.masters.master_shops.show.master_departments.show.master_sub_departments.create',
                                    'parameters' => $request->route()->originalParameters()
                                ],
                                default => [
                                    'name'       => 'grp.masters.master_shops.show.master_sub_departments.create',
                                    'parameters' => $request->route()->originalParameters()
                                ]
                            }
                        ],
                    ],
                    'subNavigation' => $subNavigation,
                ],
                'tabs'                                => [
                    'current'    => $this->tab,
                    'navigation' => $navigation,
                ],
                MasterProductCategoryTabsEnum::INDEX->value => $this->tab == MasterProductCategoryTabsEnum::INDEX->value ?
                    fn () => MasterSubDepartmentsResource::collection($masterSubDepartments)
                    : Inertia::lazy(fn () => MasterSubDepartmentsResource::collection(IndexMasterSubDepartments::run($this->parent, prefix: MasterProductCategoryTabsEnum::INDEX->value))),

                MasterProductCategoryTabsEnum::SALES->value => $this->tab == MasterProductCategoryTabsEnum::SALES->value ?
                    fn () => MasterSubDepartmentsResource::collection(IndexMasterSubDepartments::run($this->parent, prefix: MasterProductCategoryTabsEnum::SALES->value))
                    : Inertia::lazy(fn () => MasterSubDepartmentsResource::collection(IndexMasterSubDepartments::run($this->parent, prefix: MasterProductCategoryTabsEnum::SALES->value))),
            ]
        )->table($this->tableStructure($this->parent, prefix: MasterProductCategoryTabsEnum::INDEX->value))
            ->table($this->tableStructure($this->parent, prefix: MasterProductCategoryTabsEnum::SALES->value, sales: true));
    }

    public function getBreadcrumbs(MasterShop|MasterProductCategory $parent, string $routeName, array $routeParameters, string $suffix = null): array
    {
        $headCrumb = function (array $routeParameters, ?string $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Master sub departments'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ]
            ];
        };

        return match ($routeName) {
            'grp.masters.master_shops.show.master_sub_departments.index',
            'grp.masters.master_shops.show.master_sub_departments.show',
            'grp.masters.master_shops.show.master_sub_departments.edit' =>
            array_merge(
                ShowMasterShop::make()->getBreadcrumbs($parent),
                $headCrumb(
                    [
                        'name'       => 'grp.masters.master_shops.show.master_sub_departments.index',
                        'parameters' => $routeParameters
                    ],
                    $suffix
                )
            ),

            'grp.masters.master_departments.show.master_sub_departments.index',
            'grp.masters.master_departments.show.master_sub_departments.show',
            'grp.masters.master_shops.show.master_departments.show.master_sub_departments.index',
            'grp.masters.master_shops.show.master_departments.show.master_sub_departments.show' =>
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


            default => []
        };
    }
}
