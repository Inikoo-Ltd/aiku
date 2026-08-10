<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Ecom\ExclusiveProducts\UI;

use App\Actions\RetinaAction;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Http\Resources\Catalogue\ProductsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * The products made for this customer alone. They are deliberately absent from the public site and
 * from the general catalogue, so this is the only place the customer can browse them.
 */
class IndexRetinaExclusiveProducts extends RetinaAction
{
    public function handle(Shop $shop, ?int $customerId, $prefix = null): LengthAwarePaginator
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

        $queryBuilder = QueryBuilder::for(Product::class);
        $queryBuilder->where('products.shop_id', $shop->id);
        $queryBuilder->whereIn('products.state', [ProductStateEnum::ACTIVE->value, ProductStateEnum::DISCONTINUING->value]);
        $queryBuilder->where('products.is_main', true);

        // Only this customer's own exclusives. Without a customer there is nothing to show, and
        // whereRaw('false') keeps that explicit rather than leaking the whole exclusive range.
        if ($customerId) {
            $queryBuilder->whereExists(function ($query) use ($customerId) {
                $query->from('product_has_exclusive_customers')
                    ->whereColumn('product_has_exclusive_customers.product_id', 'products.id')
                    ->where('product_has_exclusive_customers.customer_id', $customerId);
            });
        } else {
            $queryBuilder->whereRaw('false');
        }

        $queryBuilder
            ->defaultSort('products.code')
            ->select([
                'products.id',
                'products.code',
                'products.name',
                'products.state',
                'products.price',
                'products.created_at',
                'products.updated_at',
                'products.slug',
                'products.is_for_sale',
                'products.gross_weight',
                'products.rrp',
                'products.web_images',
                'available_quantity',
                'units',
            ])
            ->selectRaw("'{$shop->currency->code}'  as currency_code")
            ->leftJoin('product_stats', 'products.id', 'product_stats.product_id');

        return $queryBuilder->allowedSorts([
            'code',
            'name',
            'state',
            'price',
            'units',
            'available_quantity',
            'gross_weight',
            'rrp',
        ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure($prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState([
                    'title' => __('No exclusive products yet'),
                ]);

            $table
                ->column(key: 'state', label: ['fal', 'fa-yin-yang'], sortable: true, type: 'icon')
                ->column(key: 'image', label: __('Image'), type: 'icon')
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'available_quantity', label: __('Stock'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'weight', label: __('Weight'), canBeHidden: false, align: 'right')
                ->column(key: 'price', label: __('Price'), canBeHidden: false, sortable: true, align: 'right');
        };
    }

    public function jsonResponse(LengthAwarePaginator $products): AnonymousResourceCollection
    {
        return ProductsResource::collection($products);
    }

    public function htmlResponse(LengthAwarePaginator $products, ActionRequest $request): Response
    {
        $title = __('Exclusive Products');

        return Inertia::render(
            'Catalogue/RetinaProducts',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => $title,
                'pageHead'    => [
                    'title' => $title,
                    'model' => '',
                    'icon'  => [
                        'icon'  => ['fal', 'fa-gem'],
                        'title' => $title,
                    ],
                ],
                'data'        => ProductsResource::collection($products),
            ]
        )->table($this->tableStructure());
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        return $this->handle($this->shop, $this->customer?->id);
    }

    public function getBreadcrumbs(): array
    {
        return [
            [
                'type'   => 'simple',
                'simple' => [
                    'route' => ['name' => 'retina.exclusive_products.dashboard'],
                    'label' => __('Exclusive Products'),
                    'icon'  => 'fal fa-gem',
                ],
            ],
        ];
    }
}
