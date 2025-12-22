<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Mon, 13 Mar 2023 15:05:41 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Catalogue\Collection\UI;

use App\Actions\Catalogue\Shop\UI\ShowCatalogue;
use App\Actions\Catalogue\WithCollectionsSubNavigation;
use App\Actions\Catalogue\WithCollectionSubNavigation;
use App\Actions\Catalogue\WithDepartmentSubNavigation;
use App\Actions\Catalogue\WithFamilySubNavigation;
use App\Actions\Catalogue\WithSubDepartmentSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\Catalogue\Collection\CollectionProductsStatusEnum;
use App\Enums\Catalogue\Collection\CollectionStateEnum;
use App\Enums\UI\Catalogue\CollectionsTabsEnum;
use App\Http\Resources\Catalogue\CollectionsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\Sorts\Sort;

class IndexCollections extends OrgAction
{
    use WithCatalogueAuthorisation;
    use WithCollectionSubNavigation;
    use WithDepartmentSubNavigation;
    use WithSubDepartmentSubNavigation;
    use WithFamilySubNavigation;
    use WithCollectionsSubNavigation;

    private string $bucket;

    protected function getElementGroups(Shop $parent): array
    {
        return [
            'state' => [
                'label'    => __('State'),
                'elements' => array_merge_recursive(
                    CollectionProductsStatusEnum::labels(),
                    CollectionProductsStatusEnum::count($parent)
                ),

                'engine' => function ($query, $elements) {
                    $query->whereIn('collections.products_status', $elements);
                }
            ],


        ];
    }

    public function handle(Shop $shop, $prefix = null): LengthAwarePaginator
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

        $queryBuilder = QueryBuilder::for(Collection::class);
        $queryBuilder->where('collections.shop_id', $shop->id);
        $queryBuilder->leftjoin('collection_stats', 'collection_stats.collection_id', 'collections.id');
        $queryBuilder->leftJoin('collection_sales_intervals', 'collection_sales_intervals.collection_id', 'collections.id');


        $queryBuilder
            ->leftJoin('webpages', function ($join) {
                $join->on('collections.id', '=', 'webpages.model_id')
                    ->where('webpages.model_type', '=', 'Collection')
                    ->whereNull('webpages.deleted_at');
            });

        if ($this->bucket == 'active') {
            $queryBuilder->where('collections.state', CollectionStateEnum::ACTIVE);
        } elseif ($this->bucket == 'inactive') {
            $queryBuilder->where('collections.state', CollectionStateEnum::INACTIVE);
        } elseif ($this->bucket == 'in_process') {
            $queryBuilder->where('collections.state', CollectionStateEnum::IN_PROCESS);
        }

        foreach ($this->getElementGroups($shop) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }

        $queryBuilder
            ->leftJoin('organisations', 'collections.organisation_id', '=', 'organisations.id')
            ->leftJoin('shops', 'collections.shop_id', '=', 'shops.id')
            ->leftJoin('websites', 'websites.shop_id', '=', 'shops.id')
            ->leftJoin('currencies', 'shops.currency_id', 'currencies.id');

        $interval = request()->input('interval', 'all');

        $salesLyColumn = $interval === 'all'
            ? 'NULL'
            : "collection_sales_intervals.sales_grp_currency_{$interval}_ly";

        $queryBuilder
            ->defaultSort('collections.code')
            ->select([
                'collections.id',
                'collections.code',
                'collections.state',
                'collections.products_status',
                'collections.name',
                'collections.description',
                'collections.created_at',
                'collections.updated_at',
                'collections.slug',
                'collections.web_images',
                'collections.master_collection_id',
                'collection_stats.number_families',
                'collection_stats.number_products',
                'collection_stats.number_parents',
                'shops.slug as shop_slug',
                'shops.code as shop_code',
                'shops.name as shop_name',
                'organisations.name as organisation_name',
                'organisations.slug as organisation_slug',
                'webpages.id as webpage_id',
                'webpages.state as webpage_state',
                'webpages.url as webpage_url',
                'webpages.slug as webpage_slug',
                'websites.slug as website_slug',
                'currencies.code as currency_code',
                DB::raw("collection_sales_intervals.sales_grp_currency_{$interval} as sales"),
                DB::raw("{$salesLyColumn} as sales_ly"),
                DB::raw("'{$interval}' as current_interval"),
            ])
            ->selectRaw(
                '(
        SELECT concat(string_agg(product_categories.slug,\',\'),\'|\',string_agg(product_categories.type,\',\'),\'|\',string_agg(product_categories.code,\',\'),\'|\',string_agg(product_categories.name,\',\')) FROM model_has_collections
        left join product_categories on model_has_collections.model_id = product_categories.id
        WHERE model_has_collections.collection_id = collections.id

        AND model_has_collections.model_type = ?
    ) as parents_data',
                ['ProductCategory',]
            );


        return $queryBuilder
            ->allowedFilters([$globalSearch])
            ->allowedSorts([
                'code',
                'name',
                'number_parents',
                'number_families',
                'number_products',
                AllowedSort::custom(
                    'sales',
                    new class ($interval) implements Sort {
                        public function __construct(private string $interval)
                        {
                        }

                        public function __invoke(Builder $query, bool $descending, string $property)
                        {
                            $direction = $descending ? 'desc' : 'asc';
                            $query->orderBy("collection_sales_intervals.sales_grp_currency_{$this->interval}", $direction);
                        }
                    }
                ),
            ])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(
        Shop $shop,
        $prefix = null,
        $sales = true,
    ): Closure {
        return function (InertiaTable $table) use ($shop, $prefix, $sales) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            foreach ($this->getElementGroups($shop) as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements']
                );
            }

            if ($sales) {
                $table->withInterval();
            }

            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('collection'),__('collections')])
                ->withEmptyState(
                    [
                        'title'       => __("No collections found"),
                        'description' => __('Get started by creating a new collection. ✨'),
                        'count'       => $shop->stats->number_collections,
                    ]
                );

            $table->column(key: 'state_icon', label: '', canBeHidden: false, type: 'icon');

            if ($sales) {
                $table->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                    ->column(key: 'sales', label: __('Sales'), canBeHidden: false, sortable: true, searchable: true, align: 'right')
                    ->column(key: 'sales_delta', label: __('Δ 1Y'), canBeHidden: false, sortable: false, searchable: false, align: 'right');
            } else {
                $table->column(key: 'parents', label: __('Parents'), canBeHidden: false);
                $table->column(key: 'image_thumbnail', label: '', type: 'avatar');
                $table->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                    ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);
                $table->column(key: 'webpage', label: __('Webpage'), canBeHidden: false);
                $table->column(key: 'number_families', label: __('Families'), canBeHidden: false, sortable: true);
                $table->column(key: 'number_products', label: __('Products'), canBeHidden: false, sortable: true);
                $table->column(key: 'actions', label: '', searchable: true);
            }
        };
    }

    public function jsonResponse(LengthAwarePaginator $collections): AnonymousResourceCollection
    {
        return CollectionsResource::collection($collections);
    }

    public function htmlResponse(LengthAwarePaginator $collections, ActionRequest $request): Response
    {
        $container = null;

        $subNavigation = $this->getCollectionsSubNavigation($this->shop);
        $navigation = CollectionsTabsEnum::navigation();

        $title     = __('Collections');
        $icon      = [
            'icon'  => ['fal', 'fa-album-collection'],
            'title' => $title
        ];
        $iconRight = null;
        $routes    = [
            'indexWebpage' => [
                'name'       => 'grp.json.webpages.index',
                'parameters' => [
                    "shop" => Arr::get($request->route()->originalParameters(), 'shop')
                ]
            ],
        ];

        $websiteDomain = null;
        if ($this->shop->website) {
            $websiteDomain = 'https://'.$this->shop->website->domain;
        }

        return Inertia::render(
            'Org/Catalogue/Collections',
            [
                'breadcrumbs'    => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'          => __('Collections'),
                'pageHead'       => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'afterTitle'    => [
                        'label' => '@ '.__('Shop').' '.$this->shop->code,
                    ],
                    'iconRight'     => $iconRight,
                    'container'     => $container,
                    'subNavigation' => $subNavigation,
                ],
                'routes'         => $routes,
                'tabs'           => [
                    'current'    => $this->tab,
                    'navigation' => $navigation,
                ],
                'data'           => CollectionsResource::collection($collections),
                'formData'       => [
                    'fullLayout' => true,
                    'blueprint'  => [
                        [
                            'title'  => __('New Collection'),
                            'fields' => [
                                'code'        => [
                                    'type'     => 'input',
                                    'label'    => __('Code'),
                                    'required' => true
                                ],
                                'name'        => [
                                    'type'     => 'input',
                                    'label'    => __('Name'),
                                    'required' => true,
                                ],
                                'description' => [
                                    'type'     => 'textarea',
                                    'label'    => __('Description'),
                                    'required' => false,
                                ],
                                "image"       => [
                                    "type"     => "image_crop_square",
                                    "label"    => __("Image"),
                                    "required" => false,
                                ],

                            ]
                        ]
                    ],
                    'route'      => [
                        'name'       => 'grp.models.org.catalogue.collections.store',
                        'parameters' => [
                            'organisation' => $this->shop->organisation_id,
                            'shop'         => $this->shop->id,
                        ]
                    ]
                ],
                'website_domain' => $websiteDomain,

                CollectionsTabsEnum::INDEX->value => $this->tab == CollectionsTabsEnum::INDEX->value ?
                    fn () => CollectionsResource::collection($collections)
                    : Inertia::lazy(fn () => CollectionsResource::collection($collections)),

                CollectionsTabsEnum::SALES->value => $this->tab == CollectionsTabsEnum::SALES->value ?
                    fn () => CollectionsResource::collection($collections)
                    : Inertia::lazy(fn () => CollectionsResource::collection($collections)),
            ]
        )->table($this->tableStructure($this->shop, prefix: CollectionsTabsEnum::INDEX->value, sales: false))
            ->table($this->tableStructure($this->shop, prefix: CollectionsTabsEnum::SALES->value));
    }


    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'all';
        $this->initialisationFromShop($shop, $request)->withTab(CollectionsTabsEnum::values());

        return $this->handle(shop: $shop, prefix: CollectionsTabsEnum::INDEX->value);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function active(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'active';
        $this->initialisationFromShop($shop, $request);

        return $this->handle(shop: $shop);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inactive(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'inactive';
        $this->initialisationFromShop($shop, $request);

        return $this->handle(shop: $shop);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inProcess(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'in_process';
        $this->initialisationFromShop($shop, $request);

        return $this->handle(shop: $shop);
    }


    public function getBreadcrumbs(string $routeName, array $routeParameters, string $suffix = null): array
    {
        $headCrumb = function (array $routeParameters, ?string $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Collections'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ]
            ];
        };

        return match ($routeName) {
            'grp.org.shops.show.catalogue.collections.index',
            'grp.org.shops.show.catalogue.collections.create' =>
            array_merge(
                ShowCatalogue::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                    $suffix
                )
            ),
            'grp.org.shops.show.catalogue.collections.active.index' =>
            array_merge(
                ShowCatalogue::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                    __('(Active)')
                )
            ),
            'grp.org.shops.show.catalogue.collections.inactive.index' =>
            array_merge(
                ShowCatalogue::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                    __('(Inactive)')
                )
            ),
            'grp.org.shops.show.catalogue.collections.in_process.index' =>
            array_merge(
                ShowCatalogue::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                    __('(In Process)')
                )
            ),

            'grp.overview.catalogue.collections.index' =>
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


            default => []
        };
    }
}
