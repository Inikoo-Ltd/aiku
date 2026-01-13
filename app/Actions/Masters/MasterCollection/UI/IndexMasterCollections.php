<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 29 Dec 2024 03:11:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterCollection\UI;

use App\Actions\Goods\UI\WithMasterCatalogueSubNavigation;
use App\Actions\Masters\MasterShop\UI\ShowMasterShop;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersAuthorisation;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\UI\Catalogue\MasterCollectionsTabsEnum;
use App\Http\Resources\Masters\MasterCollectionsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Masters\MasterCollection;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexMasterCollections extends OrgAction
{
    use WithMasterCatalogueSubNavigation;
    use WithMastersAuthorisation;

    private Group|MasterShop $parent;

    public function handle(Group|MasterShop $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('master_collections.code', $value)
                    ->orWhereStartWith('master_collections.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(MasterCollection::class)
            ->leftJoin(
                'master_collection_stats',
                'master_collections.id',
                'master_collection_stats.master_collection_id'
            )
            ->leftJoin(
                'master_shops',
                'master_shops.id',
                'master_collections.master_shop_id'
            )
            ->leftJoin('groups', 'master_shops.group_id', '=', 'groups.id')
            ->leftJoin('currencies', 'groups.currency_id', '=', 'currencies.id');

        $selects = [
            'master_collections.id',
            'master_collections.code',
            'master_collections.slug',
            'master_collections.products_status',
            'master_collections.data',
            'master_collections.name',
            'master_collections.status',
            'master_collections.web_images',
            'currencies.code as currency_code',

            'master_collection_stats.number_current_master_families',
            'master_collection_stats.number_current_master_products',
            'master_collection_stats.number_current_master_collections',

            'master_shops.slug as master_shop_slug',
            'master_shops.code as master_shop_code',
            'master_shops.name as master_shop_name',
        ];

        if ($prefix === MasterCollectionsTabsEnum::SALES->value) {
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

            $selects[] = $timeSeriesData['selectRaw']['sales'];
            $selects[] = $timeSeriesData['selectRaw']['invoices'];
            $selects[] = $timeSeriesData['selectRaw']['sales_ly'];
            $selects[] = $timeSeriesData['selectRaw']['invoices_ly'];
        }

        $queryBuilder->select($selects);


        $queryBuilder->selectRaw("
            EXISTS (
                SELECT 1
                FROM collections
                JOIN webpages ON collections.id = webpages.model_id
                WHERE collections.master_collection_id = master_collections.id
                AND collections.webpage_id IS NOT NULL
                AND webpages.deleted_at IS NULL
                AND webpages.model_type = 'Collection'
            ) AS has_active_webpage
        ");


        $queryBuilder->addSelect(DB::raw("
            (
                SELECT json_agg(
                    json_build_object(
                        'id', mpc.id,
                        'code', mpc.code,
                        'slug', mpc.slug,
                        'name', mpc.name
                    )
                    ORDER BY mpc.code
                )
                FROM model_has_master_collections mhmc
                JOIN master_product_categories mpc
                    ON mhmc.model_id = mpc.id
                WHERE mhmc.master_collection_id = master_collections.id
                AND mhmc.model_type = 'MasterProductCategory'
                AND mhmc.type = 'master_department'
            ) AS departments_data
        "));

        $queryBuilder->addSelect(DB::raw("
            (
                SELECT json_agg(
                    json_build_object(
                        'id', mpc.id,
                        'code', mpc.code,
                        'slug', mpc.slug,
                        'name', mpc.name
                    )
                    ORDER BY mpc.code
                )
                FROM model_has_master_collections mhmc
                JOIN master_product_categories mpc
                    ON mhmc.model_id = mpc.id
                WHERE mhmc.master_collection_id = master_collections.id
                AND mhmc.model_type = 'MasterProductCategory'
                AND mhmc.type = 'master_sub_department'
            ) AS sub_departments_data
        "));


        /**
         * Parent scope
         */
        if ($parent instanceof MasterShop) {
            $queryBuilder->where('master_collections.master_shop_id', $parent->id);
        } else {
            $queryBuilder->where('master_collections.group_id', $parent->id);
        }

        return $queryBuilder
            ->defaultSort('master_collections.code')
            ->allowedSorts([
                'status',
                'code',
                'name',
                'number_current_master_families',
                'number_current_master_products',
                'number_current_master_collections',
                'sales',
                'invoices'
            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure($prefix = null, $sales = false): \Closure
    {
        return function (InertiaTable $table) use ($prefix, $sales) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix . 'Page');
            }

            if ($sales) {
                $table->betweenDates(['date']);
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title' => __("No master collections found"),
                    ],
                );

            if ($sales) {
                $table
                    ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                    ->column(key: 'sales', label: __('Sales'), canBeHidden: false, sortable: true, searchable: true, align: 'right')
                    ->column(key: 'sales_delta', label: __('Δ 1Y'), canBeHidden: false, sortable: false, searchable: false, align: 'right')
                    ->column(key: 'invoices', label: __('Invoices'), canBeHidden: false, sortable: true, searchable: true, align: 'right')
                    ->column(key: 'invoices_delta', label: __('Δ 1Y'), canBeHidden: false, sortable: false, searchable: false, align: 'right');
            } else {
                $table->column(key: 'status_icon', label: '', canBeHidden: false, type: 'icon');
                /*   $table->column(key: 'parents', label: __('Parents'), canBeHidden: false); */
                $table->column(key: 'image_thumbnail', label: '', type: 'avatar');
                $table->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true);
                $table->column(key: 'name', label: __(key: 'Name'), canBeHidden: false, sortable: true, searchable: true);
                $table->column(key: 'master_department', label: __('M. Departement'), canBeHidden: false, sortable: true, searchable: false);
                $table->column(key: 'master_sub_department', label: __('M. Sub-department'), canBeHidden: false, sortable: true, searchable: false);
                $table->column(key: 'number_current_master_families', label: __('Families'), canBeHidden: false, sortable: true);
                $table->column(key: 'number_current_master_products', label: __('Products'), canBeHidden: false, sortable: true);
                $table->column(key: 'number_current_master_collections', label: __('Collections'), canBeHidden: false, sortable: true);
                $table->column(key: 'actions', label: __('Action'));
            }
        };
    }

    public function jsonResponse(LengthAwarePaginator $masterCollections): AnonymousResourceCollection
    {
        return MasterCollectionsResource::collection($masterCollections);
    }

    public function htmlResponse(LengthAwarePaginator $masterCollections, ActionRequest $request): Response
    {
        $title = __('Master collections');

        $icon          = '';
        $model         = null;
        $afterTitle    = null;
        $iconRight     = null;
        $subNavigation = null;

        if ($this->parent instanceof Group) {
            $model      = '';
            $icon       = [
                'icon'  => ['fal', 'fa-album-collection'],
                'title' => $title
            ];
            $afterTitle = [
                'label' => __('In group')
            ];
            $iconRight  = [
                'icon' => 'fal fa-city',
            ];
        }


        if ($this->parent instanceof MasterShop) {
            $subNavigation = $this->getMasterShopNavigation($this->parent);
        }

        return Inertia::render(
            'Masters/MasterCollections',
            [
                'breadcrumbs' => $this->getBreadcrumbs($this->parent, $request->route()->getName(), $request->route()->originalParameters()),
                'title'       => $title,
                'pageHead'    => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'subNavigation' => $subNavigation,
                    'actions'       => [
                        [
                            'type'    => 'button',
                            'style'   => 'create',
                            'tooltip' => __('New master collection'),
                            'label'   => __('Master collection'),
                            'route'   => match ($this->parent::class) {
                                MasterProductCategory::class => [],
                                default => [
                                    'name'       => 'grp.masters.master_shops.show.master_collections.create',
                                    'parameters' => $request->route()->originalParameters()
                                ]
                            }
                        ],
                    ],
                ],
                'data'        => MasterCollectionsResource::collection($masterCollections),

                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => MasterCollectionsTabsEnum::navigation(),
                ],

                MasterCollectionsTabsEnum::INDEX->value => $this->tab == MasterCollectionsTabsEnum::INDEX->value ?
                    fn () => MasterCollectionsResource::collection($masterCollections)
                    : Inertia::lazy(fn () => MasterCollectionsResource::collection(IndexMasterCollections::run($this->parent, prefix: MasterCollectionsTabsEnum::INDEX->value))),

                MasterCollectionsTabsEnum::SALES->value => $this->tab == MasterCollectionsTabsEnum::SALES->value ?
                    fn () => MasterCollectionsResource::collection(IndexMasterCollections::run($this->parent, prefix: MasterCollectionsTabsEnum::SALES->value))
                    : Inertia::lazy(fn () => MasterCollectionsResource::collection(IndexMasterCollections::run($this->parent, prefix: MasterCollectionsTabsEnum::SALES->value))),


            ]
        )->table($this->tableStructure(prefix: MasterCollectionsTabsEnum::INDEX->value))
        ->table($this->tableStructure(prefix: MasterCollectionsTabsEnum::SALES->value, sales: true));
    }

    public function getBreadcrumbs(MasterShop|MasterProductCategory|Group $parent, string $routeName, array $routeParameters, string $suffix = null): array
    {
        $headCrumb = function (array $routeParameters, ?string $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Master collections'),
                        'icon'  => 'fal fa-album-collection'
                    ],
                    'suffix' => $suffix
                ],
            ];
        };

        return match ($routeName) {
            'grp.masters.master_shops.show.master_collections.index' =>
            array_merge(
                ShowMasterShop::make()->getBreadcrumbs($parent),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                    $suffix
                ),
            ),
            default => []
        };
    }

    public function asController(MasterShop $masterShop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterShop;
        $group        = group();
        $this->initialisationFromGroup($group, $request)->withTab(MasterCollectionsTabsEnum::values());

        return $this->handle(parent: $masterShop, prefix: MasterCollectionsTabsEnum::INDEX->value);
    }

    public function inGroup(ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = group();
        $group        = $this->parent;
        $this->initialisationFromGroup($group, $request)->withTab(MasterCollectionsTabsEnum::values());

        return $this->handle(parent: $group, prefix: MasterCollectionsTabsEnum::INDEX->value);
    }
}
