<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\UI;

use App\Actions\Catalogue\Shop\UI\ShowCatalogue;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Http\Resources\CRM\CustomersResource;
use App\InertiaTable\InertiaTable;
use App\Models\CRM\Customer;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * The customers who have a range made for them, with how much of it there is. The way in from the
 * catalogue to the CRM record behind an exclusive range.
 */
class IndexExclusiveProductsCustomers extends OrgAction
{
    use WithCatalogueAuthorisation;

    public function handle(Shop $shop, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('customers.name', $value)
                    ->orWhereStartWith('customers.reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Customer::class);
        $queryBuilder->where('customers.shop_id', $shop->id);
        $queryBuilder->where('customers.number_exclusive_products', '>', 0);

        $queryBuilder
            ->defaultSort('-customers.number_exclusive_products')
            ->select([
                'customers.id',
                'customers.slug',
                'customers.reference',
                'customers.name',
                'customers.email',
                'customers.state',
                'customers.status',
                'customers.created_at',
                'customers.number_exclusive_products',
            ])
            ->selectRaw("'{$shop->slug}' as shop_slug");

        return $queryBuilder
            ->allowedSorts(['reference', 'name', 'number_exclusive_products'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure($prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState([
                    'title' => __('No customer has a range of their own'),
                ]);

            $table
                ->column(key: 'reference', label: __('Reference'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_exclusive_products', label: __('Exclusive products'), canBeHidden: false, sortable: true, align: 'right')
                ->defaultSort('-number_exclusive_products');
        };
    }

    public function jsonResponse(LengthAwarePaginator $customers): AnonymousResourceCollection
    {
        return CustomersResource::collection($customers);
    }

    public function htmlResponse(LengthAwarePaginator $customers, ActionRequest $request): Response
    {
        $title = __('Customers with Exclusive Products');

        return Inertia::render(
            'Org/Catalogue/ExclusiveProductsCustomers',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => $title,
                'pageHead'    => [
                    'title' => $title,
                    'icon'  => ['icon' => ['fal', 'fa-users'], 'title' => $title],
                ],
                'data'        => CustomersResource::collection($customers),
            ]
        )->table($this->tableStructure());
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowCatalogue::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.catalogue.exclusive_products.customers.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Customers with Exclusive Products'),
                        'icon'  => 'fal fa-users',
                    ],
                ],
            ]
        );
    }
}
