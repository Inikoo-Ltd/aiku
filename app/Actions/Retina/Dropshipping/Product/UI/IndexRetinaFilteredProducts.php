<?php

/*
 * author Arya Permana - Kirin
 * created on 21-05-2025-13h-40m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Product\UI;

use App\Actions\RetinaAction;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Http\Resources\CRM\FilteredProductsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Services\QueryBuilder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexRetinaFilteredProducts extends RetinaAction
{
    public function handle(CustomerSalesChannel $customerSalesChannel, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            // Get the type filter to determine how to filter products
            $type = Arr::get(request()->get('filter'), 'type');

            switch (strtolower($type)) {
                case 'department':
                    // Find products that belong to departments matching the search term
                    $query->whereHas('department', function ($query) use ($value) {
                        $query->whereAnyWordStartWith('product_categories.name', $value);
                    });
                    break;

                case 'family':
                    // Find products that belong to families matching the search term
                    $query->whereHas('family', function ($query) use ($value) {
                        $query->whereAnyWordStartWith('product_categories.name', $value);
                    });
                    break;

                case 'sub_department':
                    // Find products that belong to sub-departments matching the search term
                    $query->whereHas('subDepartment', function ($query) use ($value) {
                        $query->whereAnyWordStartWith('product_categories.name', $value);
                    });
                    break;

                case 'all':
                default:
                    // Search products by their own attributes (name, code, etc.)
                    $query->where(function ($query) use ($value) {
                        $query->whereAnyWordStartWith('products.name', $value)
                            ->orWhereStartWith('products.code', $value);
                    });
                    break;
            }
        });

        // Type filter - validates the type parameter
        $typeFilter = AllowedFilter::callback('type', function ($query, $value) {
            // This filter doesn't modify the query directly
            // It's used by the global search to determine filtering behavior
            $allowedTypes = ['all', 'department', 'family', 'sub_department'];
            if (!in_array(strtolower($value), $allowedTypes)) {
                // Default to 'all' if invalid type provided
                request()->merge(['filter' => array_merge(request()->get('filter', []), ['type' => 'all'])]);
            }
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Product::class);

        $queryBuilder->where('products.status', ProductStatusEnum::FOR_SALE);

        $queryBuilder->where('products.shop_id', $this->shop->id)
        ->whereNotIn('products.id', function ($subQuery) use ($customerSalesChannel) {
            $subQuery->select('item_id')
                ->from('portfolios')
                ->where('item_type', class_basename(Product::class))
                ->where('customer_id', $customerSalesChannel->customer->id)
                ->where('platform_id', $customerSalesChannel->platform->id)
                ->where('customer_sales_channel_id', $customerSalesChannel->id);
        });

        $queryBuilder->leftJoin('currencies', 'currencies.id', 'products.currency_id');


        $queryBuilder
            ->defaultSort('products.code')
            ->select([
                'products.id',
                'products.code',
                'products.name',
                'products.price',
                'products.state',
                'products.created_at',
                'products.updated_at',
                'products.gross_weight',
                'products.slug',
                'currencies.code as currency_code',
                'currencies.id as currency_id',
            ])
            ->leftJoin('product_stats', 'products.id', 'product_stats.product_id')
            ->leftJoin('media', 'products.image_id', '=', 'media.id');

        return $queryBuilder->allowedSorts(['code', 'name', 'shop_slug', 'department_slug', 'family_slug'])
            ->allowedFilters([$globalSearch, $typeFilter])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $products): AnonymousResourceCollection
    {
        return FilteredProductsResource::collection($products);
    }

    public function authorize(ActionRequest $request): bool
    {
        $customerSalesChannel = $request->route()->parameter('customerSalesChannel');
        if ($customerSalesChannel->customer_id == $this->customer->id) {
            return true;
        }
        return false;
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        return $this->handle($customerSalesChannel);
    }

}
