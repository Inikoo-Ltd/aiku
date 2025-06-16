<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Thu, 18 May 2023 14:27:33 Central European Summer, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Catalogue\Shop\UI;

use App\Actions\Catalogue\Product\UI\IndexProductsInOrganisation;
use App\Actions\Catalogue\ProductCategory\UI\IndexDepartmentsInOrganisation;
use App\Actions\Catalogue\ProductCategory\UI\IndexFamilies;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\UI\Catalogue\ShopsTabsEnum;
use App\Http\Resources\Catalogue\DepartmentsResource;
use App\Http\Resources\Catalogue\FamiliesResource;
use App\Http\Resources\Catalogue\ProductsResource;
use App\Http\Resources\Catalogue\ShopResource;
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

class IndexShops extends OrgAction
{
    use WithCatalogueAuthorisation;


    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request)->withTab(ShopsTabsEnum::values());

        return $this->handle($organisation, ShopsTabsEnum::SHOPS->value);
    }

    protected function getElementGroups(Organisation $organisation): array
    {
        return [
            'state' => [
                'label'    => __('State'),
                'elements' => array_merge_recursive(
                    ShopStateEnum::labels(),
                    ShopStateEnum::count($organisation)
                ),

                'engine' => function ($query, $elements) {
                    $query->whereIn('shops.state', $elements);
                }
            ],
        ];
    }

    public function handle(Organisation $organisation, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('shops.name', $value)
                    ->orWhereStartWith('shops.code', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Shop::class);
        $queryBuilder->where('type', '!=', ShopTypeEnum::FULFILMENT);


        $queryBuilder->where('organisation_id', $organisation->id);


        foreach ($this->getElementGroups($organisation) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }

        return $queryBuilder
            ->defaultSort('shops.code')
            ->select(['code', 'id', 'name', 'slug', 'type', 'state'])
            ->allowedSorts(['code', 'name', 'type', 'state'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Organisation $organisation, $prefix): Closure
    {
        return function (InertiaTable $table) use ($prefix, $organisation) {
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


            if ($this->canEdit) {
                $emptyState = [
                    'title'       => __('No shops found'),
                    'description' => __('Get started by creating a shop. ✨'),
                    'count'       => $organisation->catalogueStats->number_shops,
                    'action'      => [
                        'type'    => 'button',
                        'style'   => 'create',
                        'tooltip' => __('new shop'),
                        'label'   => __('shop'),
                        'route'   => [
                            'name'       => 'grp.org.shops.create',
                            'parameters' => $organisation->slug
                        ]
                    ]
                ];
            } else {
                $emptyState = [
                    'title'       => __('No shops found'),
                    'description' => '',
                    'count'       => $organisation->catalogueStats->number_shops,
                    'action'      => null

                ];
            }



            $table
                ->withGlobalSearch()
                ->withModelOperations()
                ->withEmptyState($emptyState)
                ->column(key: 'state', label: '', canBeHidden: false, type: 'avatar')
                ->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'type', label: __('type'), canBeHidden: false, sortable: true, searchable: true)
                ->defaultSort('code');
        };
    }


    public function htmlResponse(LengthAwarePaginator $shops, ActionRequest $request): Response
    {
        $productIndex = IndexProductsInOrganisation::class;


        return Inertia::render(
            'Org/Catalogue/Shops',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'title'       => __('shops'),
                'pageHead'    => [
                    'title'   => __('shops'),
                    'icon'    => [
                        'icon'  => ['fal', 'fa-store-alt'],
                        'title' => __('shop')
                    ],
                    'actions' => [
                        $this->canEdit ? [
                            'type'    => 'button',
                            'style'   => 'create',
                            'tooltip' => __('new shop'),
                            'label'   => __('shop'),
                            'route'   => [
                                'name'       => 'grp.org.shops.create',
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ] : false,
                    ]
                ],

                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => ShopsTabsEnum::navigation(),
                ],


                ShopsTabsEnum::SHOPS->value => $this->tab == ShopsTabsEnum::SHOPS->value ?
                    fn () => ShopResource::collection($shops)
                    : Inertia::lazy(fn () => ShopResource::collection($shops)),


                ShopsTabsEnum::DEPARTMENTS->value => $this->tab == ShopsTabsEnum::DEPARTMENTS->value ?
                    fn () => DepartmentsResource::collection(IndexDepartmentsInOrganisation::run($this->organisation, ShopsTabsEnum::DEPARTMENTS->value))
                    : Inertia::lazy(fn () => DepartmentsResource::collection(IndexDepartmentsInOrganisation::run($this->organisation, ShopsTabsEnum::DEPARTMENTS->value))),

                ShopsTabsEnum::FAMILIES->value => $this->tab == ShopsTabsEnum::FAMILIES->value ?
                    fn () => FamiliesResource::collection(IndexFamilies::run($this->organisation, ShopsTabsEnum::FAMILIES->value))
                    : Inertia::lazy(fn () => FamiliesResource::collection(IndexFamilies::run($this->organisation, ShopsTabsEnum::FAMILIES->value))),

                ShopsTabsEnum::PRODUCTS->value => $this->tab == ShopsTabsEnum::PRODUCTS->value ?
                    fn () => ProductsResource::collection($productIndex::run($this->organisation, ShopsTabsEnum::PRODUCTS->value))
                    : Inertia::lazy(fn () => ProductsResource::collection($productIndex::run($this->organisation, ShopsTabsEnum::PRODUCTS->value))),

            ]
        )->table($this->tableStructure(organisation: $this->organisation, prefix: 'shops'))
            ->table(
                IndexDepartmentsInOrganisation::make()->tableStructure(
                    parent: $this->organisation,
                    prefix: ShopsTabsEnum::DEPARTMENTS->value,
                )
            )
            ->table(
                IndexFamilies::make()->tableStructure(
                    parent: $this->organisation,
                    prefix: ShopsTabsEnum::FAMILIES->value,
                    canEdit: $this->canEdit
                ),
            )
            ->table(
                $productIndex::make()->tableStructure(
                    $this->organisation,
                    ShopsTabsEnum::PRODUCTS->value,
                )
            );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, $suffix = null): array
    {
        return
            array_merge(
                ShowGroupDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.shops.index',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Shops'),
                            'icon'  => 'fal fa-bars'
                        ],
                        'suffix' => $suffix

                    ]
                ]
            );

    }
}
