<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgSupplierProducts;

use App\Actions\Inventory\OrgStock\StoreOrgStock;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Models\Goods\Stock;
use App\Models\Inventory\OrgStock;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class ResolveOrgStockForSupplierProduct
{
    use AsObject;

    public function handle(Organisation $organisation, SupplierProduct $supplierProduct): ?OrgStock
    {
        $orgStock = $this->bestOrgStock(
            OrgStock::select('org_stocks.*')
                ->join('org_stock_has_org_supplier_products', 'org_stock_has_org_supplier_products.org_stock_id', 'org_stocks.id')
                ->join('org_supplier_products', 'org_supplier_products.id', 'org_stock_has_org_supplier_products.org_supplier_product_id')
                ->where('org_stocks.organisation_id', $organisation->id)
                ->where('org_supplier_products.supplier_product_id', $supplierProduct->id),
            ['org_stock_has_org_supplier_products.status desc', 'org_stock_has_org_supplier_products.local_priority']
        );
        if ($orgStock && !$this->isDiscontinued($orgStock)) {
            return $orgStock;
        }
        $discontinuedOrgStock = $orgStock;

        foreach ([$supplierProduct->stocks()->pluck('stocks.id'), $this->stockIdsFromTradeUnits($supplierProduct)] as $stockIds) {
            if ($stockIds->isEmpty()) {
                continue;
            }

            $orgStock = $this->bestOrgStock(OrgStock::where('organisation_id', $organisation->id)->whereIn('stock_id', $stockIds));
            if ($orgStock && !$this->isDiscontinued($orgStock)) {
                return $orgStock;
            }
            $discontinuedOrgStock ??= $orgStock;

            $stock = Stock::whereIn('id', $stockIds)
                ->whereNotIn('state', [StockStateEnum::DISCONTINUING, StockStateEnum::DISCONTINUED])
                ->orderBy('id')
                ->first();

            if ($stock) {
                return Cache::lock("org-stock:{$organisation->id}:{$stock->id}", 10)->block(5, function () use ($organisation, $stock) {
                    return OrgStock::where('organisation_id', $organisation->id)->where('stock_id', $stock->id)->first()
                        ?? StoreOrgStock::make()->action($organisation, $stock);
                });
            }
        }

        return $discontinuedOrgStock;
    }

    private function isDiscontinued(OrgStock $orgStock): bool
    {
        return in_array($orgStock->state, [OrgStockStateEnum::DISCONTINUING, OrgStockStateEnum::DISCONTINUED]);
    }

    private function bestOrgStock($query, array $thenOrderBy = []): ?OrgStock
    {
        $query->orderByRaw("CASE WHEN org_stocks.state IN ('".OrgStockStateEnum::DISCONTINUING->value."', '".OrgStockStateEnum::DISCONTINUED->value."') THEN 1 ELSE 0 END");

        foreach ($thenOrderBy as $order) {
            $query->orderByRaw($order);
        }

        return $query->orderBy('org_stocks.id')->first();
    }

    private function stockIdsFromTradeUnits(SupplierProduct $supplierProduct): Collection
    {
        return DB::table('model_has_trade_units')
            ->where('model_type', 'Stock')
            ->whereIn('trade_unit_id', DB::table('model_has_trade_units')
                ->where('model_type', 'SupplierProduct')
                ->where('model_id', $supplierProduct->id)
                ->pluck('trade_unit_id'))
            ->pluck('model_id');
    }
}
