<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\PreOrder;

use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateQuantityInLocations;
use App\Enums\Ordering\PreOrder\PreOrderStateEnum;
use App\Models\Ordering\PreOrder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Goods that arrived for a pre-order are kept for it until it is sent or cancelled: they are
 * taken off what the website offers, so another customer cannot buy them meanwhile.
 */
class HydratePreOrderReservedStock
{
    use AsObject;

    /**
     * @param  array<int, int>  $orgStockIds
     */
    public function handle(array $orgStockIds): void
    {
        if (!$orgStockIds) {
            return;
        }

        $reserved = DB::table('pre_orders')
            ->join('transactions', 'transactions.order_id', 'pre_orders.order_id')
            ->join('product_has_org_stocks', 'product_has_org_stocks.product_id', 'transactions.model_id')
            ->whereIn('pre_orders.state', array_map(fn (PreOrderStateEnum $state) => $state->value, PreOrderStateEnum::holdingStock()))
            ->where('transactions.model_type', 'Product')
            ->whereNull('transactions.deleted_at')
            ->whereIn('product_has_org_stocks.org_stock_id', $orgStockIds)
            ->groupBy('product_has_org_stocks.org_stock_id')
            ->selectRaw('product_has_org_stocks.org_stock_id, sum(transactions.quantity_ordered * product_has_org_stocks.quantity) as quantity')
            ->pluck('quantity', 'org_stock_id');

        foreach ($orgStockIds as $orgStockId) {
            DB::table('org_stocks')->where('id', $orgStockId)->update([
                'quantity_reserved_for_pre_orders' => (float) ($reserved[$orgStockId] ?? 0),
            ]);
            OrgStockHydrateQuantityInLocations::run($orgStockId);
        }
    }

    /**
     * @return array<int, int>
     */
    public function orgStockIds(PreOrder $preOrder): array
    {
        return DB::table('transactions')
            ->join('product_has_org_stocks', 'product_has_org_stocks.product_id', 'transactions.model_id')
            ->where('transactions.order_id', $preOrder->order_id)
            ->where('transactions.model_type', 'Product')
            ->whereNull('transactions.deleted_at')
            ->distinct()
            ->pluck('product_has_org_stocks.org_stock_id')
            ->all();
    }
}
