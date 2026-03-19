<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Jun 2025 23:12:39 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Actions\GrpAction;
use App\Actions\Masters\MasterCollection\UI\ShowMasterCollection;
use App\Actions\Masters\MasterCollection\UI\WithMasterCollectionNavigation;
use App\Actions\Masters\MasterCollection\UI\WithMasterCollectionSubNavigation;
use App\Enums\UI\Catalogue\MasterCollectionsTabsEnum;
use App\Enums\UI\Catalogue\MasterProductCategoryTabsEnum;
use App\Http\Resources\Masters\MasterFamiliesResource;
use App\InertiaTable\InertiaTable;
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

class IndexMasterFamiliesInMasterCollection extends GrpAction
{
    use WithMasterCollectionNavigation;
    use WithMasterCollectionSubNavigation;

    private MasterCollection $parent;

    public function handle(MasterCollection $masterCollection, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('master_product_categories.name', $value)
                    ->orWhereStartWith('master_product_categories.code', $value);
            });
        });
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(MasterProductCategory::class);

        $queryBuilder->join('master_collection_has_models', function ($join) {
            $join->on('master_product_categories.id', '=', 'master_collection_has_models.model_id')
                ->where('master_collection_has_models.model_type', '=', 'MasterProductCategory');
        });
        $queryBuilder->where('master_collection_has_models.master_collection_id', '=', $masterCollection->id);
        $queryBuilder->leftJoin('master_product_category_stats', 'master_product_categories.id', 'master_product_category_stats.master_product_category_id');

        return $queryBuilder
            ->defaultSort('master_product_categories.code')
            ->select([
                'master_product_categories.id',
                'master_product_categories.slug',
                'master_product_categories.code',
                'master_product_categories.name',
                'master_product_categories.status',
                'master_product_categories.description',
                'master_product_categories.created_at',
                'master_product_categories.updated_at',
                'master_product_categories.web_images',

            ])
            ->allowedSorts(['code', 'name'])
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
                ->defaultSort('code')
                ->withEmptyState(
                    [
                        'title' => __("Collection doesn't have any families"),
                        'count' => 0,
                    ]
                )
                ->withGlobalSearch();

            $table
                ->column(key: 'status_icon', label: '', canBeHidden: false, searchable: true, type: 'icon')
                ->column(key: 'image_thumbnail', label: '', type: 'avatar')
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'actions', label: __('Action'), canBeHidden: false, sortable: true, searchable: true);
        };
    }

    public function htmlResponse(LengthAwarePaginator $masterFamilies, ActionRequest $request): Response
    {
        $navigation      = MasterProductCategoryTabsEnum::navigationExcept([MasterProductCategoryTabsEnum::SALES]);
        $subNavigation   = $this->getMasterCollectionSubNavigation($this->parent);
        $modelNavigation = [];
        $title           = $this->parent->name;
        $model           = '';
        $icon            = [
            'icon'  => ['fal', 'fa-album-collection'],
            'title' => __('Master Collections')
        ];
        $afterTitle      = [
            'label' => __('Master Families')
        ];
        $iconRight       = [
            'icon' => 'fal fa-folder-tree',
        ];

        $baseData = [
            'breadcrumbs'            => $this->getBreadcrumbs(
                $this->parent,
                $request->route()->getName(),
                $request->route()->originalParameters()
            ),
            'navigation'             => $modelNavigation,
            'title'                  => __('Master Families'),
            'pageHead'               => [
                'title'         => $title,
                'icon'          => $icon,
                'model'         => $model,
                'afterTitle'    => $afterTitle,
                'iconRight'     => $iconRight,
                'subNavigation' => $subNavigation,
            ],
            'hideCheckbox'           => true,
            'accessedFromCollection' => true,
            'routes'                 => [
                'dataList'     => [
                    'name'       => 'grp.json.master_shop.master_families_not_attached_to_master_collection',
                    'parameters' => [
                        'masterShop' => $this->parent->masterShop->slug,
                        'scope'      => $this->parent->slug
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
        ];

        $baseData['tabs'] = [
            'current'    => $this->tab,
            'navigation' => $navigation,
        ];

        $baseData[MasterProductCategoryTabsEnum::INDEX->value] = $this->tab == MasterProductCategoryTabsEnum::INDEX->value ?
            fn () => MasterFamiliesResource::collection($masterFamilies)
            : Inertia::lazy(fn () => MasterFamiliesResource::collection($masterFamilies));

        return Inertia::render(
            'Masters/MasterFamilies',
            $baseData
        )
            ->table($this->tableStructure($this->parent, prefix: MasterProductCategoryTabsEnum::INDEX->value));
    }

    public function getBreadcrumbs(MasterCollection $parent, string $routeName, array $routeParameters, ?string $suffix = null): array
    {
        $headCrumb = function (array $routeParameters, ?string $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Master families'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ]
            ];
        };

        return match ($routeName) {
            'grp.masters.master_shops.show.master_sub_departments.master_collections.families',
            'grp.masters.master_shops.show.master_departments.show.master_collections.families',
            'grp.masters.master_departments.show.master_collections.families',
            'grp.masters.master_shops.show.master_collections.families' =>
            array_merge(
                ShowMasterCollection::make()->getBreadcrumbs($this->parent, preg_replace('/families$/', 'show', $routeName), $routeParameters),
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
        $group        = group();

        $this->initialisation($group, $request)->withTab(MasterCollectionsTabsEnum::valuesExcept([MasterCollectionsTabsEnum::SALES]));

        return $this->handle($masterCollection, MasterCollectionsTabsEnum::INDEX->value);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterDepartmentInMasterShop(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterCollection $masterCollection, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterCollection;
        $group        = group();

        $this->initialisation($group, $request)->withTab(MasterCollectionsTabsEnum::valuesExcept([MasterCollectionsTabsEnum::SALES]));

        return $this->handle($masterCollection, MasterCollectionsTabsEnum::INDEX->value);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterSubDepartmentInMasterShop(MasterShop $masterShop, MasterProductCategory $masterSubDepartment, MasterCollection $masterCollection, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterCollection;
        $group        = group();

        $this->initialisation($group, $request)->withTab(MasterCollectionsTabsEnum::valuesExcept([MasterCollectionsTabsEnum::SALES]));

        return $this->handle($masterCollection, MasterCollectionsTabsEnum::INDEX->value);
    }


    public function inGroup(MasterCollection $masterCollection, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterCollection;
        $group        = group();

        $this->initialisation($group, $request)->withTab(MasterCollectionsTabsEnum::valuesExcept([MasterCollectionsTabsEnum::SALES]));

        return $this->handle($masterCollection, MasterCollectionsTabsEnum::INDEX->value);
    }
}
