<?php
/*
 * author Arya Permana - Kirin
 * created on 21-05-2025-13h-40m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/


namespace App\Actions\Retina\Dropshipping\Product\UI;

use App\Actions\OrgAction;
use App\Actions\RetinaAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Http\Resources\CRM\FilteredProductsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexRetinaFilteredProducts extends RetinaAction
{
    public function handle(CustomerSalesChannel $customerSalesChannel, $prefix = null): LengthAwarePaginator
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
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $products): AnonymousResourceCollection
    {
        return FilteredProductsResource::collection($products);
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromPlatform($customerSalesChannel->platform, $request);

        return $this->handle($customerSalesChannel);
    }

}
