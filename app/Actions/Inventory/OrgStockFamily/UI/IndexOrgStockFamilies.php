<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 Aug 2024 14:56:11 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStockFamily\UI;

use App\Actions\Inventory\UI\ShowInventoryDashboard;
use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\Traits\Authorisations\Inventory\WithInventoryAuthorisation;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\Inventory\OrgStockFamily\OrgStockFamilyStateEnum;
use App\Enums\UI\Inventory\OrgStockFamiliesTabsEnum;
use App\Http\Resources\Inventory\OrgStockFamiliesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Inventory\OrgStockFamily;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexOrgStockFamilies extends OrgAction
{
    use WithInventoryAuthorisation;

    private string $bucket;


    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'all';
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(OrgStockFamiliesTabsEnum::values());

        return $this->handle($organisation, prefix: OrgStockFamiliesTabsEnum::INDEX->value);
    }

    public function active(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(OrgStockFamiliesTabsEnum::values());
        $this->bucket = 'active';

        return $this->handle($organisation, prefix: OrgStockFamiliesTabsEnum::INDEX->value);
    }

    public function inProcess(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(OrgStockFamiliesTabsEnum::values());
        $this->bucket = 'in_process';

        return $this->handle($organisation, prefix: OrgStockFamiliesTabsEnum::INDEX->value);
    }

    public function discontinuing(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(OrgStockFamiliesTabsEnum::values());
        $this->bucket = 'discontinuing';

        return $this->handle($organisation, prefix: OrgStockFamiliesTabsEnum::INDEX->value);
    }

    public function discontinued(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(OrgStockFamiliesTabsEnum::values());
        $this->bucket = 'discontinued';

        return $this->handle($organisation, prefix: OrgStockFamiliesTabsEnum::INDEX->value);
    }

    public function maya(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->maya = true;
        $this->initialisation($organisation, $request)->withTab(OrgStockFamiliesTabsEnum::values());

        return $this->handle($organisation, prefix: OrgStockFamiliesTabsEnum::INDEX->value);
    }


    protected function getElementGroups(Organisation $organisation): array
    {
        return
            [
                'state' => [
                    'label'    => __('State'),
                    'elements' => array_merge_recursive(
                        OrgStockFamilyStateEnum::labels(),
                        OrgStockFamilyStateEnum::count($organisation)
                    ),
                    'engine'   => function ($query, $elements) {
                        $query->whereIn('org_stock_families.state', $elements);
                    }
                ]
            ];
    }

    public function handle(Organisation $organisation, $prefix = null, $bucket = null): LengthAwarePaginator
    {
        if ($bucket) {
            $this->bucket = $bucket;
        }

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('org_stock_families.code', $value)
                    ->orWhereAnyWordStartWith('org_stock_families.name', $value);
            });
        });
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(OrgStockFamily::class);
        $queryBuilder->where('org_stock_families.organisation_id', $organisation->id);

        if ($this->bucket == 'active') {
            $queryBuilder->where('org_stock_families.state', OrgStockFamilyStateEnum::ACTIVE);
        } elseif ($this->bucket == 'discontinuing') {
            $queryBuilder->where('org_stock_families.state', OrgStockFamilyStateEnum::DISCONTINUING);
        } elseif ($this->bucket == 'discontinued') {
            $queryBuilder->where('org_stock_families.state', OrgStockFamilyStateEnum::DISCONTINUED);
        } elseif ($this->bucket == 'in_process') {
            $queryBuilder->where('org_stock_families.state', OrgStockFamilyStateEnum::IN_PROCESS);
        }

        $selects = [
            'org_stock_families.slug',
            'org_stock_families.code',
            'org_stock_families.id as id',
            'org_stock_families.name',
            'number_current_org_stocks',
            'organisations.name as organisation_name',
            'organisations.slug as organisation_slug',
            'warehouses.slug as warehouse_slug',
        ];

        if ($prefix === OrgStockFamiliesTabsEnum::SALES->value) {
            $timeSeriesData = $queryBuilder->withTimeSeriesAggregation(
                timeSeriesTable: 'org_stock_family_time_series',
                timeSeriesRecordsTable: 'org_stock_family_time_series_records',
                foreignKey: 'org_stock_family_id',
                aggregateColumns: [
                    'sales_grp_currency_external' => 'sales_grp_currency_external',
                    'invoices'                    => 'invoices',
                ],
                frequency: TimeSeriesFrequencyEnum::DAILY->value,
                prefix: $prefix,
                includeLY: true
            );

            $selects[] = $timeSeriesData['selectRaw']['sales_grp_currency_external'];
            $selects[] = $timeSeriesData['selectRaw']['sales_grp_currency_external_ly'];
            $selects[] = $timeSeriesData['selectRaw']['invoices'];
            $selects[] = $timeSeriesData['selectRaw']['invoices_ly'];
        }

        $allowedSorts = ['code', 'name', 'number_current_org_stocks'];

        if ($prefix === OrgStockFamiliesTabsEnum::SALES->value) {
            $allowedSorts[] = 'sales_grp_currency_external';
            $allowedSorts[] = 'invoices';
        }

        return $queryBuilder
            ->defaultSort('code')
            ->select($selects)
            ->leftJoin('organisations', 'org_stock_families.organisation_id', 'organisations.id')
            ->leftJoin('warehouses', 'warehouses.organisation_id', 'organisations.id')
            ->leftJoin('org_stock_family_stats', 'org_stock_family_stats.org_stock_family_id', 'org_stock_families.id')
            ->allowedSorts($allowedSorts)
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Organisation $organisation, $prefix = null, bool $sales = false): Closure
    {
        return function (InertiaTable $table) use ($organisation, $prefix, $sales) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            foreach ($this->getElementGroups($organisation) as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements']
                );
            }

            $table
                ->withGlobalSearch()
                ->column(key: 'code', label: 'Code', canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);

            if ($sales) {
                $table->betweenDates(['date'])
                    ->column(key: 'sales_grp_currency_external', label: __('Sales'), canBeHidden: false, sortable: true, align: 'right')
                    ->column(key: 'sales_grp_currency_external_delta', label: __('Δ 1Y'), canBeHidden: false, sortable: false, align: 'right')
                    ->column(key: 'invoices', label: __('Invoices'), canBeHidden: false, sortable: true, align: 'right')
                    ->column(key: 'invoices_delta', label: __('Δ 1Y'), canBeHidden: false, sortable: false, align: 'right');
            } else {
                $table->column(key: 'number_current_org_stocks', label: 'SKUs', canBeHidden: false, sortable: true)
                    ->defaultSort('code');
            }
        };
    }

    public function jsonResponse(LengthAwarePaginator $stocks): AnonymousResourceCollection
    {
        return OrgStockFamiliesResource::collection($stocks);
    }

    public function getOrgStockFamiliesSubNavigation(): array
    {
        return [

            [
                'label'  => __('Active'),
                'root'   => 'grp.org.warehouses.show.inventory.org_stock_families.active.',
                'route'  => [
                    'name'       => 'grp.org.warehouses.show.inventory.org_stock_families.active.index',
                    'parameters' => [
                        'organisation' => $this->organisation->slug,
                        'warehouse'    => $this->warehouse->slug
                    ]
                ],
                'number' => $this->organisation->inventoryStats->number_org_stock_families_state_active ?? 0
            ],
            [
                'label'  => __('In process'),
                'root'   => 'grp.org.warehouses.show.inventory.org_stock_families.in-process.',
                'route'  => [
                    'name'       => 'grp.org.warehouses.show.inventory.org_stock_families.in-process.index',
                    'parameters' => [
                        'organisation' => $this->organisation->slug,
                        'warehouse'    => $this->warehouse->slug
                    ]
                ],
                'number' => $this->organisation->inventoryStats->number_org_stock_families_state_in_process ?? 0
            ],
            [
                'label'  => __('Discontinuing'),
                'root'   => 'grp.org.warehouses.show.inventory.org_stock_families.discontinuing.',
                'route'  => [
                    'name'       => 'grp.org.warehouses.show.inventory.org_stock_families.discontinuing.index',
                    'parameters' => [
                        'organisation' => $this->organisation->slug,
                        'warehouse'    => $this->warehouse->slug
                    ]
                ],
                'number' => $this->organisation->inventoryStats->number_org_stock_families_state_discontinuing ?? 0
            ],
            [
                'label'  => __('Discontinued'),
                'root'   => 'grp.org.warehouses.show.inventory.org_stock_families.discontinued.',
                'align'  => 'right',
                'route'  => [
                    'name'       => 'grp.org.warehouses.show.inventory.org_stock_families.discontinued.index',
                    'parameters' => [
                        'organisation' => $this->organisation->slug,
                        'warehouse'    => $this->warehouse->slug
                    ]
                ],
                'number' => $this->organisation->inventoryStats->number_org_stock_families_state_discontinued ?? 0
            ],
            [
                'label'  => __('All'),
                'icon'   => 'fal fa-bars',
                'root'   => 'grp.org.warehouses.show.inventory.org_stock_families.index',
                'align'  => 'right',
                'route'  => [
                    'name'       => 'grp.org.warehouses.show.inventory.org_stock_families.index',
                    'parameters' => [
                        'organisation' => $this->organisation->slug,
                        'warehouse'    => $this->warehouse->slug
                    ]
                ],
                'number' => $this->organisation->inventoryStats->number_org_stock_families ?? 0

            ],

        ];
    }

    public function htmlResponse(LengthAwarePaginator $orgStockFamilies, ActionRequest $request): Response
    {
        $subNavigation = $this->getOrgStockFamiliesSubNavigation();

        $title = match ($this->bucket) {
            'active' => __('Active SKU Families'),
            'in_process' => __('In process SKU Families'),
            'discontinuing' => __('Discontinuing SKU Families'),
            'discontinued' => __('Discontinued SKU Families'),
            default => __('SKU Families')
        };

        $titlePage = __("SKUs families");


        return Inertia::render(
            'Org/Inventory/OrgStockFamilies',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => $title,
                'pageHead'    => [
                    'title'         => $title,
                    'icon'          => [
                        'title' => $titlePage,
                        'icon'  => 'fal fa-boxes-alt'
                    ],
                    'subNavigation' => $subNavigation
                ],
                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => OrgStockFamiliesTabsEnum::navigation(),
                ],

                OrgStockFamiliesTabsEnum::INDEX->value => $this->tab == OrgStockFamiliesTabsEnum::INDEX->value
                    ? fn () => OrgStockFamiliesResource::collection($orgStockFamilies)
                    : Inertia::lazy(fn () => OrgStockFamiliesResource::collection($orgStockFamilies)),

                OrgStockFamiliesTabsEnum::SALES->value => $this->tab == OrgStockFamiliesTabsEnum::SALES->value
                    ? fn () => OrgStockFamiliesResource::collection($this->handle($this->organisation, prefix: OrgStockFamiliesTabsEnum::SALES->value))
                    : Inertia::lazy(fn () => OrgStockFamiliesResource::collection($this->handle($this->organisation, prefix: OrgStockFamiliesTabsEnum::SALES->value))),
            ]
        )->table($this->tableStructure($this->organisation, prefix: OrgStockFamiliesTabsEnum::INDEX->value))
         ->table($this->tableStructure($this->organisation, prefix: OrgStockFamiliesTabsEnum::SALES->value, sales: true));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, ?string $suffix = null): array
    {
        $headCrumb = function (array $routeParameters, ?string $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Org Stock Families'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ]
            ];
        };

        return match ($routeName) {
            'grp.overview.inventory.org-stock-families.index' =>
            array_merge(
                ShowGroupOverviewHub::make()->getBreadcrumbs(),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                    $suffix
                )
            ),
            default => array_merge(
                ShowInventoryDashboard::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                    $suffix
                )
            ),
        };
    }
}
