<?php

namespace App\Actions\Inventory\OrgStock\Hydrators;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\Inventory\OrgStock;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;

class OrgStockHydratePurchaseOrders implements ShouldBeUnique
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:org-stock-purchase-orders {organisations?*} {--s|slugs=}';

    public function __construct()
    {
        $this->model = OrgStock::class;
    }

    public function getJobUniqueId(OrgStock $orgStock): string
    {
        return $orgStock->id;
    }

    public function handle(OrgStock $orgStock): void
    {
        $baseQuery = fn () => DB::table('purchase_order_transactions')
            ->join('purchase_orders', 'purchase_order_transactions.purchase_order_id', '=', 'purchase_orders.id')
            ->where('purchase_order_transactions.org_stock_id', $orgStock->id)
            ->whereIn('purchase_orders.delivery_state', [
                PurchaseOrderDeliveryStateEnum::READY_TO_SHIP->value,
                PurchaseOrderDeliveryStateEnum::DISPATCHED->value,
            ])
            ->whereNotIn('purchase_orders.state', [
                PurchaseOrderStateEnum::CANCELLED->value,
                PurchaseOrderStateEnum::NOT_RECEIVED->value,
            ]);

        $orgStock->stats->update([
            'on_the_way_po_value' => $baseQuery()->sum('purchase_order_transactions.org_net_amount') ?? 0,
            'on_the_way_po_count' => $baseQuery()->distinct('purchase_orders.id')->count('purchase_orders.id'),
        ]);
    }
}
