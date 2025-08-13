<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Jun 2025 00:01:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterCollection\UI;

use App\Actions\Catalogue\ProductCategory\UI\ShowDepartment;
use App\Actions\Catalogue\ProductCategory\UI\ShowSubDepartment;
use App\Actions\Catalogue\WithCollectionSubNavigation;
use App\Actions\Catalogue\WithDepartmentSubNavigation;
use App\Actions\Catalogue\WithFamilySubNavigation;
use App\Actions\Catalogue\WithSubDepartmentSubNavigation;
use App\Actions\GrpAction;
use App\Actions\Masters\MasterProductCategory\UI\ShowMasterDepartment;
use App\Actions\Masters\MasterProductCategory\WithMasterDepartmentSubNavigation;
use App\Actions\Masters\MasterProductCategory\WithMasterSubDepartmentSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Http\Resources\Catalogue\CollectionsResource;
use App\Http\Resources\Masters\MasterCollectionsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Masters\MasterCollection;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use App\Models\SysAdmin\Organisation;
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
        $queryBuilder->join('model_has_master_collections', function ($join) {
            $join->on('model_has_master_collections.master_collection_id', '=', 'master_collections.id');
        });
        $queryBuilder->where('model_has_master_collections.model_id', $parent->id);
        $queryBuilder->where('model_has_master_collections.model_type', 'MasterProductCategory');

        $queryBuilder->leftjoin('master_collection_stats', 'master_collections.id', 'master_collection_stats.master_collection_id');

        $queryBuilder
            ->defaultSort('master_collections.code')
            ->select([
                'master_collections.id',
                'master_collections.code',
                'master_collections.state',
                'master_collections.name',
                'master_collections.description',
                'master_collections.created_at',
                'master_collections.updated_at',
                'master_collections.slug',
                // 'master_collection_stats.number_families',
                // 'master_collection_stats.number_products',
                // 'master_collection_stats.number_parents',
            ]);



        return $queryBuilder
            ->allowedFilters([$globalSearch])
            ->allowedSorts(['code', 'name'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(MasterProductCategory $masterProductCategory, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($masterProductCategory, $modelOperations, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
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

            $table
                ->column(key: 'state_icon', label: '', canBeHidden: false, type: 'icon')
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'actions', label: '', searchable: true);

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


        $icon       = [
            'icon'  => ['fal', 'fa-cube'],
            'title' => __('collections')
        ];


        $title      = $masterProductCategory->name;
        // $iconRight  = [
        //     'icon' => 'fal fa-album-collection',
        // ];
        $iconRight  = [];

        $afterTitle = [
            'label' => __('Master Collections')
        ];
        $model      = '';
        if ($masterProductCategory->type == MasterProductCategoryTypeEnum::DEPARTMENT) {
            $icon          = [
                'icon'  => ['fal', 'fa-folder-tree'],
                'title' => __('master department')
            ];
            $subNavigation = $this->getMasterDepartmentSubNavigation($masterProductCategory);
        } elseif ($masterProductCategory->type == MasterProductCategoryTypeEnum::SUB_DEPARTMENT) {
            $icon          = [
                'icon'  => ['fal', 'fa-dot-circle'],
                'title' => __('master sub department')
            ];
            $subNavigation = $this->getMasterSubDepartmentSubNavigation($masterProductCategory);
        }



        // $actions = [];
        $actions = array_values(array_filter([
            ... (function () use ($request) {
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
                        'tooltip' => __('new collection'),
                        'label'   => __('collection'),
                        'route'   => [
                            'name'       => $routes[$currentRoute],
                            'parameters' => $request->route()->originalParameters()
                        ]
                    ]
                ];
            })(),


        ]));


        return Inertia::render(
            'Org/Catalogue/Collections',
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
                'data'        => MasterCollectionsResource::collection($masterCollections),
                // 'formData'    => [
                //     'fullLayout' => true,
                //     'blueprint'  => [
                //         [
                //             'title'  => __('New Collection'),
                //             'fields' => [
                //                 'code'        => [
                //                     'type'     => 'input',
                //                     'label'    => __('code'),
                //                     'required' => true
                //                 ],
                //                 'name'        => [
                //                     'type'     => 'input',
                //                     'label'    => __('name'),
                //                     'required' => true,
                //                 ],
                //                 'description' => [
                //                     'type'     => 'textarea',
                //                     'label'    => __('description'),
                //                     'required' => false,
                //                 ],
                //                 "image"       => [
                //                     "type"     => "image_crop_square",
                //                     "label"    => __("Image"),
                //                     "required" => false,
                //                 ],

                //             ]
                //         ]
                //     ],
                //     'route'      => [
                //         'name'       => 'grp.models.product_category.collection.store',
                //         'parameters' => [
                //             'productCategory' => $productCategory->id,
                //         ]
                //     ]

                // ],
            ]
        )->table($this->tableStructure($this->parent));
    }


    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterDepartmentInMasterShop(MasterShop $masterShop, MasterProductCategory $masterDepartment, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterDepartment;
        $this->initialisation($masterShop->group, $request);    

        return $this->handle($masterDepartment);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterSubDepartmentInMasterShop(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterProductCategory $masterSubDepartment, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterSubDepartment;
        $this->initialisation($masterShop->group, $request);

        return $this->handle($masterSubDepartment);
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


            default => []
        };
    }
}
