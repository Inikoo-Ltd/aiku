<?php

namespace App\Actions\Retina\Dropshipping\Collection\UI;

use App\Actions\Retina\Dropshipping\Catalogue\ShowRetinaCatalogue;
use App\Actions\RetinaAction;
use App\Http\Resources\Catalogue\CollectionsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexRetinaCollections extends RetinaAction
{
    public function handle(Shop|ProductCategory $parent, $prefix = null): LengthAwarePaginator
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
        if($parent instanceof Shop) {
            $queryBuilder->where('collections.shop_id', $parent->id);
        } elseif($parent instanceof ProductCategory){
            $queryBuilder->join('model_has_collections', function ($join) {
                $join->on('model_has_collections.collection_id', '=', 'collections.id');
            });
            $queryBuilder->where('model_has_collections.model_id', $parent->id);
            $queryBuilder->where('model_has_collections.model_type', 'ProductCategory');
        }
        
        $queryBuilder->leftjoin('collection_stats', 'collections.id', 'collection_stats.collection_id');


        $queryBuilder
            ->leftJoin('webpages', function ($join) {
                $join->on('collections.id', '=', 'webpages.model_id')
                    ->where('webpages.model_type', '=', 'Collection');
            });

        $queryBuilder
            ->leftJoin('organisations', 'collections.organisation_id', '=', 'organisations.id')
            ->leftJoin('shops', 'collections.shop_id', '=', 'shops.id')
            ->leftJoin('websites', 'websites.shop_id', '=', 'shops.id');
        $queryBuilder
            ->defaultSort('collections.code')
            ->select([
                'collections.id',
                'collections.code',
                'collections.state',
                'collections.name',
                'collections.description',
                'collections.created_at',
                'collections.updated_at',
                'collections.slug',
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
            ->allowedSorts(['code', 'name', 'number_parents', 'number_families', 'number_products', 'state'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(
        Shop $shop,
        $prefix = null,
    ): Closure {
        return function (InertiaTable $table) use ($shop, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title'       => __("No collections found"),
                        'description' => __('Get started by creating a new collection. ✨'),
                        'count'       => $shop->stats->number_collections,
                    ]
                );

            $table->column(key: 'state', label: '', canBeHidden: false, type: 'icon', sortable: false);
            $table->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'number_families', label: __('Families'), canBeHidden: false, sortable: true);
            $table->column(key: 'number_products', label: __('Products'), canBeHidden: false, sortable: true);
            $table->column(key: 'actions', label: '', searchable: true);
        };
    }

    public function jsonResponse(LengthAwarePaginator $collections): AnonymousResourceCollection
    {
        return CollectionsResource::collection($collections);
    }

    public function htmlResponse(LengthAwarePaginator $collections, ActionRequest $request): Response
    {
        $container = null;

        $subNavigation = null;

        $title     = __('Collections');
        $icon      = [
            'icon'  => ['fal', 'fa-album-collection'],
            'title' => $title
        ];
        $iconRight = null;

        return Inertia::render(
            'Catalogue/RetinaCollections',
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
                        'label' => '@ '.__('shop').' '.$this->shop->code,
                    ],
                    'iconRight'     => $iconRight,
                    'container'     => $container,
                    'subNavigation' => $subNavigation,
                ],
                'data'           => CollectionsResource::collection($collections),
            ]
        )->table($this->tableStructure($this->shop));
    }


    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        return $this->handle(parent: $this->shop);
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
            'retina.catalogue.collections.index' =>
            array_merge(
                ShowRetinaCatalogue::make()->getBreadcrumbs(),
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
