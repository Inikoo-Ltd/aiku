<?php

/*
 * author Arya Permana - Kirin
 * created on 26-06-2025-11h-00m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Masters\MasterCollection;

use App\Actions\GrpAction;
use App\Actions\Masters\MasterCollection\UI\ShowMasterCollection;
use App\Actions\Masters\MasterCollection\UI\WithMasterCollectionSubNavigation;
use App\Actions\Traits\Authorisations\WithMastersAuthorisation;
use App\Enums\UI\Catalogue\MasterCollectionsTabsEnum;
use App\Http\Resources\Masters\MasterCollectionsResource;
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

class GetMasterCollectionsInMasterCollection extends GrpAction
{
    use WithMastersAuthorisation;
    use WithMasterCollectionSubNavigation;

    private MasterCollection $parent;

    public function handle(MasterCollection $masterCollection, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('master_collections.name', $value)
                    ->orWhereStartWith('master_collections.code', $value);
            });
        });
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(MasterCollection::class);

        $queryBuilder->join('master_collection_has_models', function ($join) {
            $join->on('master_collections.id', '=', 'master_collection_has_models.model_id')
                ->where('master_collection_has_models.model_type', '=', 'MasterCollection');

        });
        $queryBuilder->where('master_collection_has_models.master_collection_id', '=', $masterCollection->id);

        return $queryBuilder
            ->defaultSort('master_collections.code')
            ->select([
                'master_collections.id',
                'master_collections.slug',
                'master_collections.code',
                'master_collections.name',
            ])
            ->allowedSorts(['code', 'name',])
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
                        'title' => __("Collection doesn't have any collections"),
                        'count' => 0,
                    ]
                )
                ->withGlobalSearch()
                ->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon');


            $table->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);
            if ($action) {
                $table->column(key: 'actions', label: __('Action'), canBeHidden: false, sortable: true, searchable: true);
            }


        };
    }

    public function htmlResponse(LengthAwarePaginator $masterCollections, ActionRequest $request): Response
    {
        $subNavigation = $this->getMasterCollectionSubNavigation($this->parent);

        $title = $this->parent->name;
        $model           = '';
        $icon            = [
            'icon'  => ['fal', 'fa-album-collection'],
            'title' => __('Master Collections')
        ];
        $afterTitle      = [
            'label' => __('Linked Master Collection')
        ];
        $iconRight       = [
            'icon' => 'fal fa-album-collection',
        ];

        return Inertia::render(
            'Masters/MasterCollections',
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
                    'subNavigation' => $subNavigation,
                ],
                'data'        => MasterCollectionsResource::collection($masterCollections),
                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => MasterCollectionsTabsEnum::navigationExcept([MasterCollectionsTabsEnum::SALES]),
                ],
                'accessedFromCollection'    => true,
                'routes'    => [
                    'dataList'     => [
                        'name'       => 'grp.json.master_shop.master_collections_not_attached_to_master_collection',
                        'parameters' => [
                            'masterShop'  => $this->parent->masterShop->slug,
                            'scope' => $this->parent->slug
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
                MasterCollectionsTabsEnum::INDEX->value => $this->tab == MasterCollectionsTabsEnum::INDEX->value ?
                    fn () => MasterCollectionsResource::collection($masterCollections)
                    : Inertia::lazy(fn () => MasterCollectionsResource::collection($masterCollections)),
            ]
        )
        ->table($this->tableStructure($this->parent, MasterCollectionsTabsEnum::INDEX->value));
    }

    public function getBreadcrumbs(MasterCollection $parent, string $routeName, array $routeParameters, ?string $suffix = null): array
    {
        $headCrumb = function (array $routeParameters, ?string $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Linked Master collections'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ],
            ];
        };

        return match ($routeName) {
            'grp.masters.master_shops.show.master_sub_departments.master_collections.linked_master_collections',
            'grp.masters.master_shops.show.master_departments.show.master_collections.linked_master_collections',
            'grp.masters.master_departments.show.master_collections.linked_master_collections',
            'grp.masters.master_shops.show.master_collections.linked_master_collections' =>
            array_merge(
                ShowMasterCollection::make()->getBreadcrumbs($this->parent, preg_replace('/linked_master_collections$/', 'show', $routeName), $routeParameters),
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

        $this->initialisation($group, $request)->withTab(MasterCollectionsTabsEnum::valuesExcept([MasterCollectionsTabsEnum::SALES]));

        return $this->handle($masterCollection, MasterCollectionsTabsEnum::INDEX->value);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterDepartmentInMasterShop(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterCollection $masterCollection, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterCollection;
        $group = group();

        $this->initialisation($group, $request)->withTab(MasterCollectionsTabsEnum::valuesExcept([MasterCollectionsTabsEnum::SALES]));

        return $this->handle($masterCollection, MasterCollectionsTabsEnum::INDEX->value);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterSubDepartmentInMasterShop(MasterShop $masterShop, MasterProductCategory $masterSubDepartment, MasterCollection $masterCollection, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterCollection;
        $group = group();

        $this->initialisation($group, $request)->withTab(MasterCollectionsTabsEnum::valuesExcept([MasterCollectionsTabsEnum::SALES]));

        return $this->handle($masterCollection, MasterCollectionsTabsEnum::INDEX->value);
    }


    public function inGroup(MasterCollection $masterCollection, ActionRequest $request): LengthAwarePaginator
    {
        $group        = group();
        $this->parent = $masterCollection;
        $this->initialisation($group, $request)->withTab(MasterCollectionsTabsEnum::valuesExcept([MasterCollectionsTabsEnum::SALES]));

        return $this->handle($masterCollection, MasterCollectionsTabsEnum::INDEX->value);
    }
}
