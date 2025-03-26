<?php
/*
 * author Arya Permana - Kirin
 * created on 26-03-2025-15h-10m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Procurement\StockDeliveryItem\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithFulfilmentShopAuthorisation;
use App\Actions\Traits\Authorisations\WithWarehouseManagementAuthorisation;
use App\Enums\Fulfilment\PalletDelivery\PalletDeliveryStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Http\Resources\Fulfilment\PalletsResource;
use App\Http\Resources\Procurement\StockDeliveryItemsResource;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Fulfilment\PalletReturn;
use App\Models\SysAdmin\Organisation;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\InertiaTable\InertiaTable;
use App\Models\Procurement\StockDelivery;
use App\Models\Procurement\StockDeliveryItem;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use App\Services\QueryBuilder;

class IndexStockDeliveryItems extends OrgAction
{
    use WithWarehouseManagementAuthorisation;

    private StockDelivery $stockDelivery;


    public function handle(StockDelivery $stockDelivery, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('supplier_products.customer_reference', $value)
                    ->orWhereWith('supplier_products.reference', $value);
            });
        });


        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(StockDeliveryItem::class);

        $query->where('stock_delivery_id', $stockDelivery->id);

        $query->leftJoin('supplier_products', 'stock_delivery_items.supplier_product_id', 'supplier_products.id');

        $query->defaultSort('stock_delivery_items.id')
            ->select(
                'stock_delivery_items.id',
                'stock_delivery_items.state',
                'stock_delivery_items.net_amount',
                'stock_delivery_items.gross_amount',
                'stock_delivery_items.unit_quantity',
                'stock_delivery_items.unit_quantity_checked',
                'stock_delivery_items.unit_quantity_placed',
                'stock_delivery_items.net_unit_price',
                'stock_delivery_items.gross_unit_price',
                'supplier_products.code as supplier_product_code',
                'supplier_products.name as supplier_product_name',
                'supplier_products.cost as supplier_product_cost',

            );


        return $query->allowedSorts(['net_amount'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $items): AnonymousResourceCollection
    {
        return StockDeliveryItemsResource::collection($items);
    }

    public function tableStructure(StockDelivery $stockDelivery, $prefix = null, $modelOperations = []): Closure
    {
        return function (InertiaTable $table) use ($prefix, $modelOperations, $stockDelivery) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }


            $emptyStateData = [
                'title' => __('No items in this delivery'),
                'count' => $stockDelivery->number_stock_delivery_items
            ];


            $table->withGlobalSearch();

            $table->withEmptyState($emptyStateData)
                ->withModelOperations($modelOperations);

            $table->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'avatar');
            $table->column(key: 'supplier_product_code', label: __('Code'));
            $table->column(key: 'supplier_product_name', label: __('Name'));
            $table->column(key: 'net_amount', label: __('Net Amount'));
            $table->defaultSort('reference');
        };
    }




}
