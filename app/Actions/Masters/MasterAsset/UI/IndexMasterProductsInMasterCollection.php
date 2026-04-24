<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 25 May 2025 19:56:14 Central Indonesia Time, Sanur, Plane KL-Shanghai
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset\UI;

use App\Actions\GrpAction;
use App\Actions\Masters\MasterCollection\UI\ShowMasterCollection;
use App\Actions\Masters\MasterCollection\UI\WithMasterCollectionNavigation;
use App\Actions\Masters\MasterCollection\UI\WithMasterCollectionSubNavigation;
use App\Enums\UI\Catalogue\MasterProductsTabsEnum;
use App\Http\Resources\Masters\MasterProductsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterCollection;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexMasterProductsInMasterCollection extends GrpAction
{
    use WithMasterCollectionNavigation;
    use WithMasterCollectionSubNavigation;

    private MasterCollection $parent;

    public function handle(MasterCollection $masterCollection, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('master_assets.name', $value)
                    ->orWhereStartWith('master_assets.code', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(MasterAsset::class);

        $queryBuilder->join('master_collection_has_models', function ($join) {
            $join->on('master_assets.id', '=', 'master_collection_has_models.model_id')
                ->where('master_collection_has_models.model_type', '=', 'MasterAsset');

        });
        $queryBuilder->where('master_collection_has_models.master_collection_id', '=', $masterCollection->id);


        $queryBuilder
            ->defaultSort('master_assets.code')
            ->select([
                'master_assets.id',
                'master_assets.code',
                'master_assets.name',
                'master_assets.slug',
                'master_assets.status',
                'master_assets.price',
                'master_assets.unit',
                'master_assets.units',
                'master_assets.rrp',
                'master_assets.web_images',
            ])
            ->leftJoin('master_asset_stats', 'master_assets.id', 'master_asset_stats.master_asset_id');

        return $queryBuilder->allowedSorts(['code', 'name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(MasterCollection $masterCollection, $prefix = null, $action = true): Closure
    {
        return function (InertiaTable $table) use ($masterCollection, $prefix, $action) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }


            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title' => __("Collection doesn't have any families"),
                        'count' => 0,
                    ]
                );

            $table->column(key: 'image_thumbnail', label: '', type: 'avatar')
                ->column(key: 'status_icon', label: '', type: 'icon');
            $table->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);
            if ($action) {
                $table->column(key: 'actions', label: __('Action'), canBeHidden: false, sortable: true, searchable: true);
            }
        };
    }

    public function htmlResponse(LengthAwarePaginator $masterAssets, ActionRequest $request): Response
    {
        $title = $this->parent->name;

        $model      = '';
        $icon            = [
            'icon'  => ['fal', 'fa-album-collection'],
            'title' => __('Master Collections')
        ];
        $afterTitle      = [
            'label' => __('Master Products')
        ];
        $iconRight       = [
            'icon' => 'fal fa-cube',
        ];

        $subNavigation   = $this->getMasterCollectionSubNavigation($this->parent);
        $modelNavigation = [];

        return Inertia::render(
            'Masters/MasterProducts',
            [
                'breadcrumbs'             => $this->getBreadcrumbs(
                    $this->parent,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'              => $modelNavigation,
                'title'                   => $title,
                'currency'                => $this->parent->group->currency->code,
                'pageHead'                => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'subNavigation' => $subNavigation,
                ],
                'variantSlugs'            => collect($masterAssets->items())->pluck('variant_slug')->filter()->unique()->mapWithKeys(fn ($slug) => [$slug => productCodeToHexCode($slug)]),
                'editable_table'          => false,
                'hide_bulk_edit'          => true,
                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => MasterProductsTabsEnum::navigationExcept([MasterProductsTabsEnum::SALES, MasterProductsTabsEnum::INDEX_ORDERING]),
                ],
                'routes'    => [
                    'dataList'     => [
                        'name'       => 'grp.json.master_shop.master_products_not_attached_to_master_collection',
                        'parameters' => [
                            'masterShop'          => $this->parent->masterShop->slug,
                            'masterCollection'    => $this->parent->slug
                        ]
                    ],
                    'submitAttach' => [
                        'name'       => 'grp.models.master_collection.attach-models',
                        'parameters' => [
                            'masterCollection' => $this->parent->id
                        ]
                    ],
                    'detach'       => [
                        'method'     => 'delete',
                        'name'       => 'grp.models.master_collection.detach-models',
                        'parameters' => [
                            'masterCollection' => $this->parent->id
                        ]
                    ]
                ],
                MasterProductsTabsEnum::INDEX->value => $this->tab == MasterProductsTabsEnum::INDEX->value ?
                    fn () => MasterProductsResource::collection($masterAssets)
                    : Inertia::lazy(fn () => MasterProductsResource::collection(IndexMasterProducts::run($this->parent, prefix: MasterProductsTabsEnum::INDEX->value))),

            ]
        )->table($this->tableStructure($this->parent, prefix: MasterProductsTabsEnum::INDEX->value));
    }

    public function getBreadcrumbs(MasterCollection $parent, string $routeName, array $routeParameters, ?string $suffix = null): array
    {
        $headCrumb = function (array $routeParameters, ?string $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Master Products'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ]
            ];
        };

        return match ($routeName) {
            'grp.masters.master_shops.show.master_sub_departments.master_collections.products',
            'grp.masters.master_shops.show.master_departments.show.master_collections.products',
            'grp.masters.master_departments.show.master_collections.products',
            'grp.masters.master_shops.show.master_collections.products' =>
            array_merge(
                ShowMasterCollection::make()->getBreadcrumbs($this->parent, preg_replace('/products$/', 'show', $routeName), $routeParameters),
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

    public function asController(MasterShop $masterShop, MasterCollection $masterCollection, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterCollection;
        $group = group();

        $this->initialisation($group, $request)->withTab(MasterProductsTabsEnum::valuesExcept([MasterProductsTabsEnum::SALES]));

        return $this->handle($masterCollection, MasterProductsTabsEnum::INDEX->value);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterDepartmentInMasterShop(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterCollection $masterCollection, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterCollection;
        $group = group();

        $this->initialisation($group, $request)->withTab(MasterProductsTabsEnum::valuesExcept([MasterProductsTabsEnum::SALES]));

        return $this->handle($masterCollection, MasterProductsTabsEnum::INDEX->value);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterSubDepartmentInMasterShop(MasterShop $masterShop, MasterProductCategory $masterSubDepartment, MasterCollection $masterCollection, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterCollection;
        $group = group();

        $this->initialisation($group, $request)->withTab(MasterProductsTabsEnum::valuesExcept([MasterProductsTabsEnum::SALES]));

        return $this->handle($masterCollection, MasterProductsTabsEnum::INDEX->value);
    }


    public function inGroup(MasterCollection $masterCollection, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterCollection;
        $group        = group();

        $this->initialisation($group, $request)->withTab(MasterProductsTabsEnum::valuesExcept([MasterProductsTabsEnum::SALES]));

        return $this->handle($masterCollection, MasterProductsTabsEnum::INDEX->value);
    }

}
