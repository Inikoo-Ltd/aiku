<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Sept 2025 11:59:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Billables\ShippingZoneSchema\UI;

use App\Actions\Billables\ShippingZoneSchema\WithShippingZoneSchemaSubNavigation;
use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Http\Resources\Catalogue\ProductsResource;
use App\Http\Resources\Catalogue\ShippingZoneSchemasResource;
use App\InertiaTable\InertiaTable;
use App\Models\Billables\ShippingZoneSchema;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexShippingZoneSchemas extends OrgAction
{
    use WithCatalogueAuthorisation;
    use WithShippingZoneSchemaSubNavigation;

    private Group|Shop $parent;

    public function handle(Group|Shop $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('products.name', $value)
                    ->orWhereStartWith('products.code', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(ShippingZoneSchema::class)
            ->leftJoin('organisations', 'shipping_zone_schemas.organisation_id', '=', 'organisations.id')
            ->leftJoin('shops', 'shipping_zone_schemas.shop_id', '=', 'shops.id')
            ->leftJoin('currencies', 'shops.currency_id', '=', 'currencies.id');

        if ($parent instanceof Group) {
            $queryBuilder->where('shipping_zone_schemas.group_id', $parent->id);
        } elseif (class_basename($parent) == 'Shop') {
            $queryBuilder->where('shipping_zone_schemas.shop_id', $parent->id);
        } else {
            abort(419);
        }

        $queryBuilder->leftjoin('shipping_zone_schema_stats', 'shipping_zone_schema_stats.shipping_zone_schema_id', '=', 'shipping_zone_schemas.id');

        $queryBuilder
            ->defaultSort('shipping_zone_schemas.name')
            ->select([
                'shipping_zone_schemas.id',
                'shipping_zone_schemas.slug',
                'shipping_zone_schemas.name',
                'shipping_zone_schemas.created_at',
                'shipping_zone_schemas.state',
                'shipping_zone_schema_stats.number_customers',
                'shipping_zone_schema_stats.number_orders',
                'shipping_zone_schema_stats.number_shipping_zones',
                'shipping_zone_schema_stats.amount',
                'shipping_zone_schema_stats.first_used_at',
                'shipping_zone_schema_stats.last_used_at',
                'shops.name as shop_name',
                'shops.slug as shop_slug',
                'currencies.code as currency_code',
                'organisations.name as organisation_name',
                'organisations.slug as organisation_slug',
            ]);

        return $queryBuilder->allowedSorts(['name', 'status'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Group|Shop $parent, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState(
                    match (class_basename($parent)) {
                        'Shop' => [
                            'title' => __("No schemas found"),
                        ],
                        default => null
                    }
                );
            $table->column(key: 'state_icon', label: '', canBeHidden: false, type: 'icon');
            $table->column(key: 'slug', label: __('Code'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);
            if ($parent instanceof Group) {
                $table->column(key: 'organisation_name', label: __('organisation'), canBeHidden: false, sortable: true, searchable: true)
                        ->column(key: 'shop_name', label: __('shop'), canBeHidden: false, sortable: true, searchable: true);
            }
            $table->column(key: 'zones', label: __('zones'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'first_used', label: __('first used'), canBeHidden: false);
            $table->column(key: 'last_used', label: __('last used'), canBeHidden: false);
            $table->column(key: 'number_customers', label: __('customers'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'number_orders', label: __('orders'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'amount', label: __('amount'), canBeHidden: false, sortable: true, searchable: true, type: 'currency');
        };
    }

    public function jsonResponse(LengthAwarePaginator $shippingZoneSchemas): AnonymousResourceCollection
    {
        return ProductsResource::collection($shippingZoneSchemas);
    }

    public function htmlResponse(LengthAwarePaginator $shippingZoneSchemas, ActionRequest $request): Response
    {
        $subNavigation = null;

        $title      = __('Shipping Schemas');
        $icon       = [
            'icon'  => ['fal', 'fa-shipping-fast'],
            'title' => __('Shipping Schemas')
        ];
        $afterTitle = null;
        $iconRight  = null;

        $actions =  [
            [
                'type'    => 'button',
                'style'   => 'create',
                'tooltip' => __('New shipping schema'),
                'label'   => __('Shipping schema'),
                'route'   => [
                    'name'       => str_replace('index', 'create', $request->route()->getName()),
                    'parameters' => $request->route()->originalParameters()
                ]
            ]
        ];

        if ($this->parent instanceof Shop) {

            $subNavigation = $this->getShippingZoneSchemaSubNavigation($this->parent);
        } elseif ($this->parent instanceof Group) {
            $actions = null;
        }
        return Inertia::render(
            'Org/Catalogue/Shippings',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Shipping'),
                'pageHead'    => array_filter([
                    'title'         => $title,
                    'icon'          => $icon,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'actions'       => $actions,
                    'subNavigation' => $subNavigation,
                ]),
                'data'        => ShippingZoneSchemasResource::collection($shippingZoneSchemas),



            ]
        )->table($this->tableStructure($this->parent));
    }

    public function inGroup(ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = group();
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($this->parent);
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);

        return $this->handle(parent: $shop);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, string $suffix = null): array
    {
        $headCrumb = function (array $routeParameters, ?string $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Shippings'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ]
            ];
        };


        return match ($routeName) {
            'grp.org.shops.show.billables.shipping.index' =>
            array_merge(
                ShowShop::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                    $suffix
                )
            ),
            'grp.overview.billables.shipping.index' =>
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
