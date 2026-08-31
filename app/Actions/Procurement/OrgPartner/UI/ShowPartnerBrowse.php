<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 29 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgPartner\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\OrgPartner\WithPartnerShoppingSubNavigation;
use App\Actions\Procurement\PartnerShoppingListItem\SuggestPartnerShoppingList;
use App\Actions\Search\SearchCatalogue;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Enums\Catalogue\Collection\CollectionStateEnum;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Shop;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Inventory\OrgStock;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowPartnerBrowse extends OrgAction
{
    use WithProcurementAuthorisation;
    use WithPartnerShoppingSubNavigation;

    private OrgPartner $orgPartner;
    private ?int $shopId;

    public function handle(OrgPartner $orgPartner, int $shopId, array $filters): array
    {
        $q              = Arr::get($filters, 'q');
        $department     = Arr::get($filters, 'department');
        $subDepartment  = Arr::get($filters, 'sub_department');
        $family         = Arr::get($filters, 'family');
        $collection     = Arr::get($filters, 'collection');

        if ($q) {
            return [
                'level'       => 'search',
                'categories'  => [],
                'collections' => [],
                'products'    => $this->searchProducts($shopId, $q),
            ];
        }

        if ($family || $collection) {
            return [
                'level'       => $family ? 'family' : 'collection',
                'categories'  => [],
                'collections' => [],
                'products'    => $this->productsQuery($shopId, $family, $collection)->paginate(24)->withQueryString(),
            ];
        }

        if ($subDepartment) {
            return [
                'level'       => 'sub_department',
                'categories'  => $this->categoriesQuery($shopId, 'family', 'sub_department_id', $subDepartment)->get(),
                'collections' => [],
                'products'    => null,
            ];
        }

        if ($department) {
            return [
                'level'       => 'department',
                'categories'  => $this->categoriesQuery($shopId, 'sub_department', 'department_id', $department)
                    ->get()
                    ->concat($this->categoriesQuery($shopId, 'family', 'department_id', $department)->get()),
                'collections' => [],
                'products'    => null,
            ];
        }

        return [
            'level'       => 'root',
            'categories'  => $this->categoriesQuery($shopId, 'department')->get(),
            'collections' => $this->collectionsQuery($shopId)->get(),
            'products'    => null,
        ];
    }

    private function categoriesQuery(int $shopId, string $type, ?string $parentColumn = null, ?string $parentKey = null)
    {
        $query = ProductCategory::query()
            ->whereIn('state', [ProductCategoryStateEnum::ACTIVE->value, ProductCategoryStateEnum::DISCONTINUING->value])
            ->where('type', $type)
            ->where('shop_id', $shopId)
            ->join('product_category_stats', 'product_categories.id', 'product_category_stats.product_category_id')
            ->select([
                'product_categories.id',
                'product_categories.slug',
                'product_categories.code',
                'product_categories.name',
                'product_categories.web_images',
                'product_categories.type',
                'product_category_stats.number_current_products',
            ]);

        if ($parentColumn) {
            $query->where("product_categories.$parentColumn", is_numeric($parentKey) ? $parentKey : ProductCategory::where('slug', $parentKey)->value('id'));
        }

        return $query;
    }

    private function collectionsQuery(int $shopId)
    {
        return Collection::query()
            ->where('state', CollectionStateEnum::ACTIVE)
            ->where('shop_id', $shopId)
            ->select(['id', 'slug', 'code', 'name', 'web_images']);
    }

    private function productsQuery(int $shopId, ?string $family, ?string $collection)
    {
        $query = Product::query()
            ->whereIn('state', [ProductStateEnum::ACTIVE->value, ProductStateEnum::DISCONTINUING->value])
            ->where('is_for_sale', true)
            ->where('shop_id', $shopId)
            ->whereHas('orgStocks');

        if ($family) {
            $query->where('family_id', is_numeric($family) ? $family : ProductCategory::where('slug', $family)->value('id'));
        }

        if ($collection) {
            $collectionId = is_numeric($collection) ? $collection : Collection::where('slug', $collection)->value('id');
            $query->join('collection_has_models', function ($join) use ($collectionId) {
                $join->on('products.id', '=', 'collection_has_models.model_id')
                    ->where('collection_has_models.model_type', class_basename(Product::class))
                    ->where('collection_has_models.collection_id', $collectionId);
            });
        }

        return $query->select([
            'products.id',
            'products.slug',
            'products.code',
            'products.name',
            'products.web_images',
            'products.price',
            'products.available_quantity',
            'products.units',
        ]);
    }

    private function searchProducts(int $shopId, string $q): LengthAwarePaginator
    {
        $productIds = collect(Arr::get(SearchCatalogue::run($q, ['shop_id' => $shopId]), 'results.products', []))
            ->pluck('id');

        return Product::query()
            ->whereIn('state', [ProductStateEnum::ACTIVE->value, ProductStateEnum::DISCONTINUING->value])
            ->where('is_for_sale', true)
            ->where('shop_id', $shopId)
            ->whereHas('orgStocks')
            ->whereIn('id', $productIds)
            ->select(['id', 'slug', 'code', 'name', 'web_images', 'price', 'available_quantity', 'units'])
            ->paginate(24)
            ->withQueryString();
    }

    /**
     * @param  array{categories: iterable, collections: iterable, products: LengthAwarePaginator|null}  $data
     */
    private function withOrgStock(array $data): array
    {
        $categories = collect($data['categories'])->map(fn ($category) => [
            'id'                     => $category->id,
            'slug'                   => $category->slug,
            'code'                   => $category->code,
            'name'                   => $category->name,
            'image'                  => Arr::get($category->web_images, 'main.gallery') ?? Arr::get($category->web_images, 'main.thumbnail'),
            'type'                   => $category->type->value,
            'number_current_products' => $category->number_current_products,
        ])->values();

        $collections = collect($data['collections'])->map(fn ($collection) => [
            'id'    => $collection->id,
            'slug'  => $collection->slug,
            'code'  => $collection->code,
            'name'  => $collection->name,
            'image' => Arr::get($collection->web_images, 'main.gallery') ?? Arr::get($collection->web_images, 'main.thumbnail'),
        ])->values();

        $products = $data['products'];
        if ($products) {
            $productIds      = collect($products->items())->pluck('id');
            $sellerOrgStocks = Product::whereIn('id', $productIds)
                ->with(['orgStocks' => fn ($query) => $query->where('org_stocks.organisation_id', $this->orgPartner->partner_id)])
                ->get()
                ->mapWithKeys(fn (Product $product) => [$product->id => $product->orgStocks->first()]);

            $buyerOrgStocks = OrgStock::with('stats')->where('organisation_id', $this->orgPartner->organisation_id)
                ->whereIn('stock_id', $sellerOrgStocks->map(fn ($orgStock) => $orgStock?->stock_id)->filter()->values())
                ->get()
                ->keyBy('stock_id');

            $usage = SuggestPartnerShoppingList::make()->buyerQuarterlyUsage($buyerOrgStocks->pluck('id')->all());

            $openItems = PartnerShoppingListItem::where('org_partner_id', $this->orgPartner->id)
                ->where('state', ShoppingListItemStateEnum::OPEN)
                ->whereIn('stock_id', $buyerOrgStocks->keys())
                ->get()
                ->keyBy('stock_id');

            $products->getCollection()->transform(function (Product $product) use ($sellerOrgStocks, $buyerOrgStocks, $usage, $openItems) {
                $sellerOrgStock = $sellerOrgStocks[$product->id] ?? null;
                $buyerOrgStock  = $sellerOrgStock ? $buyerOrgStocks->get($sellerOrgStock->stock_id) : null;
                $openItem       = $sellerOrgStock ? $openItems->get($sellerOrgStock->stock_id) : null;

                return [
                    'id'                => $product->id,
                    'slug'              => $product->slug,
                    'code'              => $product->code,
                    'name'              => $product->name,
                    'image'             => Arr::get($product->web_images, 'main.gallery') ?? Arr::get($product->web_images, 'main.thumbnail'),
                    'price'             => $product->price,
                    'available_quantity' => $product->available_quantity,
                    'units'             => $product->units,
                    'org_stock_slug'    => $sellerOrgStock?->slug,
                    'our_stock'         => $buyerOrgStock ? (float) $buyerOrgStock->quantity_available : null,
                    'our_quarterly_usage' => $buyerOrgStock ? round($usage[$buyerOrgStock->id] ?? 0, 1) : null,
                    'our_days_of_cover' => $buyerOrgStock?->stats?->days_of_cover !== null
                        ? (int) $buyerOrgStock->stats->days_of_cover
                        : null,
                    'recommended_quantity'  => $buyerOrgStock?->stats?->recommended_order_quantity !== null
                        ? (int) ceil((float) $buyerOrgStock->stats->recommended_order_quantity)
                        : null,
                    'shopping_list_item_id' => $openItem?->id,
                    'ordered_quantity'      => $openItem ? (float) $openItem->quantity : 0,
                ];
            });
        }

        return [
            'level'       => $data['level'],
            'categories'  => $categories,
            'collections' => $collections,
            'products'    => $products,
        ];
    }

    private function miniCart(): array
    {
        return GetPartnerMiniCart::run($this->orgPartner);
    }

    /**
     * @return array{products: int, in_stock: int, departments: int, collections: int}
     */
    private function browseStats(): array
    {
        $shopStats = Shop::find($this->shopId)->stats;

        return [
            'products'    => $shopStats->number_current_products,
            'in_stock'    => $shopStats->number_products_status_for_sale,
            'departments' => $shopStats->number_current_departments,
            'collections' => $shopStats->number_collections,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function filterNames(array $filters): array
    {
        $categorySlugs = Arr::only($filters, ['department', 'sub_department', 'family']);
        $names         = ProductCategory::whereIn('slug', $categorySlugs)->pluck('name', 'slug');
        if ($collection = Arr::get($filters, 'collection')) {
            $names[$collection] = Collection::where('slug', $collection)->value('name');
        }

        return collect($filters)->map(fn ($slug) => $names[$slug] ?? $slug)->all();
    }

    public function asController(Organisation $organisation, OrgPartner $orgPartner, ActionRequest $request): array
    {
        $this->orgPartner = $orgPartner;
        $this->shopId = Arr::get($orgPartner->partner->settings, 'procurement.shop_id');
        abort_unless($this->shopId, 404);

        $this->initialisation($organisation, $request);

        return $this->withOrgStock($this->handle($orgPartner, $this->shopId, $request->only(['q', 'department', 'sub_department', 'family', 'collection'])));
    }

    public function htmlResponse(array $data, ActionRequest $request): Response
    {
        return Inertia::render(
            'Procurement/PartnerBrowse',
            [
                'breadcrumbs' => $this->getBreadcrumbs($this->orgPartner, $request->route()->originalParameters()),
                'title'       => __('Browse'),
                'pageHead'    => [
                    'icon'          => [
                        'icon'  => ['fal', 'fa-store'],
                        'title' => __('Browse'),
                    ],
                    'model'         => $this->orgPartner->partner->name,
                    'title'         => __('Browse'),
                    'subNavigation' => $this->getPartnerShoppingNavigation($this->orgPartner),
                ],
                'orgPartner' => [
                    'id'       => $this->orgPartner->id,
                    'slug'     => $this->orgPartner->partner->slug,
                    'currency' => $this->orgPartner->partner->currency->code,
                ],
                'addRoute' => [
                    'name'       => 'grp.org.procurement.org_partners.show.shopping_list.store',
                    'parameters' => [$this->orgPartner->organisation->slug, $this->orgPartner->id],
                ],
                'miniCart'    => $this->miniCart(),
                'browseStats' => $this->browseStats(),
                'filters'     => $request->only(['q', 'department', 'sub_department', 'family', 'collection']),
                'filterNames' => $this->filterNames($request->only(['department', 'sub_department', 'family', 'collection'])),
                'level'       => $data['level'],
                'categories'  => $data['categories'],
                'collections' => $data['collections'],
                'products'    => $data['products'],
            ]
        );
    }

    public function getBreadcrumbs(OrgPartner $orgPartner, array $routeParameters): array
    {
        return array_merge(
            ShowOrgPartner::make()->getBreadcrumbs($orgPartner, $routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.procurement.org_partners.show.browse.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Browse'),
                        'icon'  => 'fal fa-store',
                    ],
                ],
            ]
        );
    }
}
