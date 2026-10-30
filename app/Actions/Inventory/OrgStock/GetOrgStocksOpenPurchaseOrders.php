<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 30 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock;

use App\Actions\GoodsIn\StockDelivery\StoreStockDeliveryFromPurchaseOrder;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetOrgStocksOpenPurchaseOrders
{
    use AsObject;

    /**
     * Submitted or confirmed purchase order lines with a quantity not yet on a stock delivery. Quantities are in units.
     *
     * @param  Collection<int, int>  $supplierProductIds
     * @param  Collection<int, int>  $orgStockIds
     * @return Collection<int, object{supplier_product_id: int|null, org_stock_id: int|null, slug: string, reference: string, state: string, quantity_ordered: float}>
     */
    public function handle(int $organisationId, Collection $supplierProductIds, Collection $orgStockIds, ?int $exceptPurchaseOrderId = null): Collection
    {
        if ($supplierProductIds->isEmpty() && $orgStockIds->isEmpty()) {
            return collect();
        }

        $quantityOnDelivery = StoreStockDeliveryFromPurchaseOrder::quantityOnDelivery();

        return DB::table('purchase_order_transactions')
            ->join('purchase_orders', 'purchase_orders.id', 'purchase_order_transactions.purchase_order_id')
            ->where(function ($query) use ($supplierProductIds, $orgStockIds) {
                $query->whereIn('purchase_order_transactions.supplier_product_id', $supplierProductIds)
                    ->orWhereIn('purchase_order_transactions.org_stock_id', $orgStockIds);
            })
            ->where('purchase_orders.organisation_id', $organisationId)
            ->when($exceptPurchaseOrderId, fn ($query) => $query->where('purchase_orders.id', '!=', $exceptPurchaseOrderId))
            ->whereIn('purchase_orders.state', [
                PurchaseOrderStateEnum::SUBMITTED->value,
                PurchaseOrderStateEnum::CONFIRMED->value,
            ])
            ->whereNull('purchase_orders.deleted_at')
            ->whereNull('purchase_order_transactions.deleted_at')
            ->whereNotIn('purchase_order_transactions.state', [PurchaseOrderTransactionStateEnum::CANCELLED->value, PurchaseOrderTransactionStateEnum::NOT_RECEIVED->value])
            ->whereRaw('purchase_order_transactions.quantity_ordered - ('.$quantityOnDelivery->toSql().') > '.StoreStockDeliveryFromPurchaseOrder::QUANTITY_TOLERANCE, $quantityOnDelivery->getBindings())
            ->orderBy('purchase_orders.id')
            ->select([
                'purchase_order_transactions.supplier_product_id',
                'purchase_order_transactions.org_stock_id',
                'purchase_orders.slug',
                'purchase_orders.reference',
                'purchase_orders.state',
            ])
            ->selectRaw('purchase_order_transactions.quantity_ordered - ('.$quantityOnDelivery->toSql().') as quantity_ordered', $quantityOnDelivery->getBindings())
            ->get();
    }
}
