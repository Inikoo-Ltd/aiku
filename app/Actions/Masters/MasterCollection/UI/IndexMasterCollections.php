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
use App\Http\Resources\Masters\MasterCollectionsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Masters\MasterCollection;
use App\Models\Masters\MasterProductCategory;
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
                    ->orWhereStartWith('master_collections.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(MasterCollection::class);
        $queryBuilder->leftjoin('master_collection_stats', 'master_collections.id', 'master_collection_stats.master_collection_id');

        $queryBuilder->select(
            [
                'master_collections.id',
                'master_collections.code',
                'master_collections.slug',
                'master_collections.products_status',
                'master_collections.data',
                'master_collections.name',
                'master_collections.status',
                'master_collections.web_images',
                'master_collection_stats.number_current_master_families',
                'master_collection_stats.number_current_master_products',
                'master_collection_stats.number_current_master_collections',
                'master_collections.web_images',
            ]
        )
            ->selectRaw("EXISTS(SELECT 1 FROM collections JOIN webpages ON collections.id = webpages.model_id WHERE collections.master_collection_id = master_collections.id AND collections.webpage_id IS NOT NULL AND webpages.deleted_at IS NULL AND webpages.model_type = 'Collection') as has_active_webpage")
            ->selectRaw(
                '(
        SELECT concat(string_agg(master_product_categories.slug,\',\'),\'|\',string_agg(master_product_categories.type,\',\'),\'|\',string_agg(master_product_categories.code,\',\'),\'|\',string_agg(master_product_categories.name,\',\')) FROM model_has_master_collections
        left join master_product_categories on model_has_master_collections.model_id = master_product_categories.id
        WHERE model_has_master_collections.master_collection_id = master_collections.id
   
        AND model_has_master_collections.model_type = ?
    ) as parents_data',
                ['MasterProductCategory',]
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
            ->allowedSorts(
                [
                    'status',
                    'code',
                    'name',
                    'number_current_master_families',
                    'number_current_master_products',
                    'number_current_master_collections'
                ]
            )
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

            $table
                ->column(key: 'status_icon', label: '', canBeHidden: false, type: 'icon');
            $table->column(key: 'parents', label: __('Parents'), canBeHidden: false);
            $table->column(key: 'image_thumbnail', label: '', type: 'avatar');
            $table->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'number_current_master_families', label: __('Families'), canBeHidden: false, sortable: true);
            $table->column(key: 'number_current_master_products', label: __('Products'), canBeHidden: false, sortable: true);
            $table->column(key: 'number_current_master_collections', label: __('Collections'), canBeHidden: false, sortable: true);
            $table->column(key: 'actions', label: __('Action'));
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
                                MasterProductCategory::class => [
                                ],
                                default => [
                                    'name'       => 'grp.masters.master_shops.show.master_collections.create',
                                    'parameters' => $request->route()->originalParameters()
                                ]
                            }
                        ],
                    ],
                ],
                'data'        => MasterCollectionsResource::collection($masterCollections),

            ]
        )->table($this->tableStructure());
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
                ShowMasterShop::make()->getBreadcrumbs($parent, $routeName),
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
