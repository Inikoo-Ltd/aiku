<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 29 Dec 2024 03:11:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterCollection\UI;

use App\Actions\Goods\UI\WithMasterCatalogueSubNavigation;
use App\Actions\Masters\UI\ShowMastersDashboard;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersAuthorisation;
use App\Http\Resources\Masters\MasterCollectionsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Masters\MasterCollection;
use App\Models\Masters\MasterShop;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
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
                    ->orWhereStartWith('master_collections.description', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(MasterCollection::class);
        $queryBuilder->select(
            [
                'master_collections.id',
                'master_collections.code',
                'master_collections.description',
                'master_collections.slug',
                'master_collections.status',
                'master_collections.data',
            ]
        );

        if ($parent instanceof MasterShop) {
            $queryBuilder->where('master_collections.master_shop_id', $parent->id);
        } else {
            $queryBuilder->where('master_collections.group_id', $parent->id);
        }

        $queryBuilder->leftJoin('master_shops', 'master_shops.id', 'master_collections.master_shop_id');
        $queryBuilder->addSelect([
            'master_shops.slug as master_shop_slug',
            'master_shops.code as master_shop_code',
            'master_shops.name as master_shop_name',
        ]);

        return $queryBuilder
            ->defaultSort('master_collections.code')
            ->allowedSorts(['code', 'description'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure($prefix = null): \Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }
            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title' => __("No master collections found"),
                    ],
                );

            $table->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'description', label: __('description'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'status', label: __('status'), canBeHidden: false)
                ->defaultSort('code');
        };
    }

    public function jsonResponse(LengthAwarePaginator $masterCollections): AnonymousResourceCollection
    {
        return MasterCollectionsResource::collection($masterCollections);
    }

    public function htmlResponse(LengthAwarePaginator $masterCollections, ActionRequest $request): Response
    {
        $title = __('master collections');

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

        return Inertia::render(
            'Masters/MasterCollections',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName()),
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

            ]
        )->table($this->tableStructure());
    }

    public function getBreadcrumbs(string $routeName, string $suffix = null): array
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
            'grp.masters.master_collections.index' =>
            array_merge(
                ShowMastersDashboard::make()->getBreadcrumbs(),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => []
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
        $this->initialisationFromGroup($group, $request);

        return $this->handle(parent: $masterShop);
    }

    public function inGroup(ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = group();
        $group        = $this->parent;
        $this->initialisationFromGroup($group, $request);

        return $this->handle(parent: $group);
    }
}
