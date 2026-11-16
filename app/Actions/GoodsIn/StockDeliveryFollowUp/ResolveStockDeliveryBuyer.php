<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDeliveryFollowUp;

use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Who buys what arrived: the buyer of the first of the delivery's purchase orders with a live line for that SKO
 * (or for that supplier), else the organisation's delivery buyer, else nobody (listed as unassigned).
 */
class ResolveStockDeliveryBuyer
{
    use AsObject;

    public function handle(StockDelivery $stockDelivery, ?int $orgStockId = null, ?int $supplierId = null): ?int
    {
        $buyerId = self::purchaseOrderBuyerQuery($stockDelivery->id, $orgStockId, $supplierId)->value('purchase_orders.buyer_id');

        return $buyerId ? (int) $buyerId : self::fallbackBuyerId($stockDelivery->organisation);
    }

    /**
     * The buyers of the delivery's linked purchase orders with a live line for the SKO or the supplier, first order
     * first. Columns may be given as raw SQL so the list can correlate it with each of its rows.
     */
    public static function purchaseOrderBuyerQuery(int|string $stockDeliveryId, int|string|null $orgStockId = null, ?int $supplierId = null): Builder
    {
        $column = fn (int|string $value) => is_string($value) ? DB::raw($value) : $value;

        return DB::table('purchase_orders')
            ->join('purchase_order_stock_delivery', 'purchase_order_stock_delivery.purchase_order_id', 'purchase_orders.id')
            ->join('users', 'users.id', 'purchase_orders.buyer_id')
            ->where('purchase_order_stock_delivery.stock_delivery_id', $column($stockDeliveryId))
            ->whereRaw('users.status')
            ->whereExists(fn ($query) => $query->from('purchase_order_transactions')
                ->whereColumn('purchase_order_transactions.purchase_order_id', 'purchase_orders.id')
                ->whereNull('purchase_order_transactions.deleted_at')
                ->where('purchase_order_transactions.state', '!=', PurchaseOrderTransactionStateEnum::CANCELLED->value)
                ->when($orgStockId !== null, fn ($query) => $query->where('purchase_order_transactions.org_stock_id', $column($orgStockId)))
                ->when($supplierId, fn ($query) => $query->whereIn(
                    'purchase_order_transactions.supplier_product_id',
                    DB::table('supplier_products')->where('supplier_id', $supplierId)->select('id')
                )))
            ->orderBy('purchase_orders.id');
    }

    public static function fallbackBuyerId(Organisation $organisation): ?int
    {
        $userId = Arr::get($organisation->settings, 'procurement.delivery_buyer_id');

        return $userId && User::where('id', $userId)->where('status', true)->exists() ? (int) $userId : null;
    }
}
