<?php

namespace App\Actions\Inventory\OrgStockFamily\Hydrators;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\Inventory\OrgStockFamily;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;

class OrgStockFamilyHydratePurchaseOrders implements ShouldBeUnique
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:org-stock-family-purchase-orders {organisations?*} {--s|slugs=}';

    public function __construct()
    {
        $this->model = OrgStockFamily::class;
    }

    public function getJobUniqueId(OrgStockFamily $orgStockFamily): string
    {
        return $orgStockFamily->id;
    }

    public function handle(OrgStockFamily $orgStockFamily): void
    {
        $baseQuery = fn () => DB::table('purchase_order_transactions')
            ->join('purchase_orders', 'purchase_order_transactions.purchase_order_id', '=', 'purchase_orders.id')
            ->join('org_stocks', 'purchase_order_transactions.org_stock_id', '=', 'org_stocks.id')
            ->where('org_stocks.org_stock_family_id', $orgStockFamily->id)
            ->whereIn('purchase_orders.delivery_state', [
                PurchaseOrderDeliveryStateEnum::READY_TO_SHIP->value,
                PurchaseOrderDeliveryStateEnum::DISPATCHED->value,
            ])
            ->whereNotIn('purchase_orders.state', [
                PurchaseOrderStateEnum::CANCELLED->value,
                PurchaseOrderStateEnum::NOT_RECEIVED->value,
            ]);

        $orgStockFamily->stats->update([
            'on_the_way_po_value' => $baseQuery()->sum('purchase_order_transactions.org_net_amount') ?? 0,
            'on_the_way_po_count' => $baseQuery()->distinct('purchase_orders.id')->count('purchase_orders.id'),
        ]);
    }
}
