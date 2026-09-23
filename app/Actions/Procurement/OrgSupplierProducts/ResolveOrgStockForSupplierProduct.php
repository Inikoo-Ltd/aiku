<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgSupplierProducts;

use App\Actions\Inventory\OrgStock\StoreOrgStock;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Models\Goods\Stock;
use App\Models\Inventory\OrgStock;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class ResolveOrgStockForSupplierProduct
{
    use AsObject;

    public function handle(Organisation $organisation, SupplierProduct $supplierProduct): ?OrgStock
    {
        $tradeUnitIds = DB::table('model_has_trade_units')
            ->where('model_type', 'SupplierProduct')
            ->where('model_id', $supplierProduct->id)
            ->pluck('trade_unit_id');

        $stockIds = DB::table('model_has_trade_units')
            ->where('model_type', 'Stock')
            ->whereIn('trade_unit_id', $tradeUnitIds)
            ->pluck('model_id')
            ->merge($supplierProduct->stocks()->pluck('stocks.id'))
            ->unique();

        $orgStock = OrgStock::where('organisation_id', $organisation->id)
            ->where(function ($query) use ($stockIds, $tradeUnitIds, $organisation, $supplierProduct) {
                $query->whereIn('id', DB::table('org_stock_has_org_supplier_products')
                    ->join('org_supplier_products', 'org_supplier_products.id', 'org_stock_has_org_supplier_products.org_supplier_product_id')
                    ->where('org_supplier_products.organisation_id', $organisation->id)
                    ->where('org_supplier_products.supplier_product_id', $supplierProduct->id)
                    ->pluck('org_stock_has_org_supplier_products.org_stock_id'))
                    ->orWhereIn('stock_id', $stockIds)
                    ->orWhereIn('id', DB::table('model_has_trade_units')
                        ->where('model_type', 'OrgStock')
                        ->whereIn('trade_unit_id', $tradeUnitIds)
                        ->pluck('model_id'));
            })
            ->first();

        if ($orgStock) {
            return $orgStock;
        }

        $stock = Stock::whereIn('id', $stockIds)
            ->whereNotIn('state', [StockStateEnum::DISCONTINUING, StockStateEnum::DISCONTINUED])
            ->first();

        return $stock ? StoreOrgStock::make()->action($organisation, $stock) : null;
    }
}
