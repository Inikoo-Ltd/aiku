<?php

namespace App\Actions\CRM\Customer\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMEditAuthorisation;
use App\Http\Resources\CRM\ProductsForPortfolioSelectResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Services\QueryBuilder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetProductsForPortfolioSelect extends OrgAction
{
    use WithCRMEditAuthorisation;

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

        $queryBuilder->where('products.is_for_sale', true);

        $queryBuilder->where('products.shop_id', $customerSalesChannel->shop_id)
            ->whereNotIn('products.id', function ($subQuery) use ($customerSalesChannel) {
                $subQuery->select('item_id')
                    ->from('portfolios')
                    ->where('item_type', class_basename(Product::class))
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
                'products.web_images',
                'products.slug',
                'currencies.code as currency_code',
                'currencies.id as currency_id',
            ])
            ->leftJoin('product_stats', 'products.id', 'product_stats.product_id');

        return $queryBuilder->allowedSorts(['code', 'name', 'shop_slug', 'department_slug', 'family_slug'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $products): AnonymousResourceCollection
    {
        return ProductsForPortfolioSelectResource::collection($products);
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($customerSalesChannel->shop, $request);

        return $this->handle($customerSalesChannel);
    }

}
