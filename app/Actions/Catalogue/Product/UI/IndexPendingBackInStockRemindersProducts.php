<?php

/*
 * author Louis Perez
 * created on 09-02-2026-11h-46m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Catalogue\Product\UI;

use App\Actions\Catalogue\Shop\UI\ShowCatalogue;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\UI\Catalogue\ProductsTabsEnum;
use App\Http\Resources\Catalogue\ProductIndexPendingBackInStockReminderResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Comms\BackInStockReminder;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexPendingBackInStockRemindersProducts extends OrgAction
{
    use WithCatalogueAuthorisation;


    public function handle(Shop $shop, $prefix = null): LengthAwarePaginator
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

        $queryBuilder = QueryBuilder::for(BackInStockReminder::class)
            ->leftJoin('products', 'products.id', 'back_in_stock_reminders.product_id');
        $queryBuilder
            ->where('back_in_stock_reminders.shop_id', $shop->id)
            ->where('products.is_main', true)
            ->whereIn('products.state', [ProductStateEnum::ACTIVE, ProductStateEnum::DISCONTINUING]);
        $queryBuilder
            ->orderBy('products.state');

        return $queryBuilder
            ->groupBy(['back_in_stock_reminders.product_id', 'products.id'])
            ->defaultSort('products.code')
            ->select([
                'products.id as product_id',
                DB::raw("COUNT(*) as number_of_distinct_reminders"),
                'products.code',
                'products.name',
                'products.state',
                'products.price',
                'products.created_at',
                'products.updated_at',
                'products.slug',
                'products.available_quantity'
            ])
            ->allowedSorts(['code', 'name', 'state', 'available_quantity', 'number_of_distinct_reminders'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Shop $shop, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($shop, $modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState(
                    [
                        'title' => __("No Pending Back in Stock Reminders found"),
                        'count' => 0,

                    ]
                );

            $table
                ->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon', sortable: true, searchable: false)
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_of_distinct_reminders', label: __('Number of Reminders'), canBeHidden: false, sortable: true, searchable: false, align: 'right')
                ->column(key: 'available_quantity', label: __('Available Qty'), canBeHidden: false, sortable: true, searchable: false, align: 'right');
        };
    }

    public function htmlResponse(LengthAwarePaginator $products, ActionRequest $request): Response
    {
        /** @var Shop $shop */
        $shop = $request->route('shop');

        $navigation    = ProductsTabsEnum::navigation();

        unset($navigation[ProductsTabsEnum::SALES->value]);

        $title = __('Products (Pending Back-in-Stock)');

        $icon       = [
            'icon'  => ['fal', 'fa-cube'],
            'title' => $title
        ];
        $afterTitle = null;
        $iconRight  = null;
        $model      = null;


        return Inertia::render(
            'Org/Catalogue/Products',
            [
                'breadcrumbs'       => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'                        => $title,
                'pageHead'                     => [
                    'title'         => $title,
                    'model'         => $model,
                    'icon'          => $icon,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                ],
                'data'                         => ProductIndexPendingBackInStockReminderResource::collection($products)->resolve(),
                'tabs'                         => [
                    'current'    => $this->tab,
                    'navigation' => $navigation,
                ],

                ProductsTabsEnum::INDEX->value => $this->tab == ProductsTabsEnum::INDEX->value ?
                    fn () => ProductIndexPendingBackInStockReminderResource::collection($products)
                    : Inertia::lazy(fn () => ProductIndexPendingBackInStockReminderResource::collection($products)),

            ]
        )
        ->table($this->tableStructure(shop: $shop, prefix: ProductsTabsEnum::INDEX->value));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, ?string $suffix = null): array
    {
        $headCrumb = function (array $routeParameters, ?string $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Products'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ]
            ];
        };


        return match ($routeName) {
            'grp.org.shops.show.catalogue.products.pending_back_in_stock_reminders.index', =>
            array_merge(
                ShowCatalogue::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                    trim('('.__('Pending Back-in-Stock').') '.$suffix)
                )
            ),
            default => []
        };
    }


    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($shop, $request)->withTab(ProductsTabsEnum::values());
        return $this->handle(shop:$shop, prefix: ProductsTabsEnum::INDEX->value);
    }

}
