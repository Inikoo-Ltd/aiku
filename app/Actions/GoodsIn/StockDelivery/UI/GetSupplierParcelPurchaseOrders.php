<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery\UI;

use App\Actions\GoodsIn\StockDelivery\ReceiveSupplierParcel;
use App\Actions\GoodsIn\StockDelivery\StoreStockDeliveryFromPurchaseOrder;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithGoodsInBookInAuthorisation;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Inventory\Warehouse;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

/**
 * What goods in can receive a supplier's parcel against: the direct suppliers (no agent) with open orders, and for one
 * of them its orders with the SKOs still awaited. A submitted order is listed too: receiving it agrees it (INI-523).
 * No prices: goods in never sees costs.
 */
class GetSupplierParcelPurchaseOrders extends OrgAction
{
    use WithGoodsInBookInAuthorisation;

    public static function directOpenPurchaseOrders(Organisation $organisation): Builder
    {
        return PurchaseOrder::where('purchase_orders.organisation_id', $organisation->id)
            ->whereNull('purchase_orders.agent_id')
            ->where('purchase_orders.parent_type', 'OrgSupplier')
            ->whereIn('purchase_orders.state', PurchaseOrderStateEnum::open());
    }

    /**
     * @return list<array{id: int, code: string, name: string, purchase_orders: int}>
     */
    public function suppliers(Organisation $organisation): array
    {
        return DB::table('org_suppliers')
            ->join('suppliers', 'suppliers.id', 'org_suppliers.supplier_id')
            ->joinSub(
                self::directOpenPurchaseOrders($organisation)->toBase()->selectRaw('parent_id, count(*) as purchase_orders')->groupBy('parent_id'),
                'open_orders',
                'open_orders.parent_id',
                'org_suppliers.id'
            )
            ->orderBy('suppliers.name')
            ->get(['org_suppliers.id', 'suppliers.code', 'suppliers.name', 'open_orders.purchase_orders'])
            ->map(fn (object $row) => (array) $row)
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function purchaseOrders(Organisation $organisation, Warehouse $warehouse, int $orgSupplierId): array
    {
        return self::directOpenPurchaseOrders($organisation)
            ->where('purchase_orders.parent_id', $orgSupplierId)
            ->orderBy('purchase_orders.submitted_at')
            ->get()
            ->map(fn (PurchaseOrder $purchaseOrder) => [
                'id'               => $purchaseOrder->id,
                'reference'        => $purchaseOrder->reference,
                'submitted_at'     => $purchaseOrder->submitted_at,
                'needs_agreeing'   => $purchaseOrder->state === PurchaseOrderStateEnum::SUBMITTED,
                'lines'            => self::receivableLines($purchaseOrder)->map(fn (PurchaseOrderTransaction $line) => [
                    'id'            => $line->id,
                    'code'          => $line->orgStock?->code ?? $line->historicSupplierProduct?->code,
                    'name'          => $line->orgStock?->name ?? $line->historicSupplierProduct?->name,
                    'supplier_code' => $line->historicSupplierProduct?->code,
                    'skos_awaiting' => round((float) $line->quantity_ready_to_ship / ReceiveSupplierParcel::unitsPerSko($line), 4),
                ])->values()->all(),
                'open_deliveries' => $purchaseOrder->stockDeliveries()
                    ->whereIn('stock_deliveries.state', [StockDeliveryStateEnum::IN_PROCESS, StockDeliveryStateEnum::CONFIRMED, StockDeliveryStateEnum::READY_TO_SHIP, StockDeliveryStateEnum::DISPATCHED])
                    ->get()
                    ->map(fn (StockDelivery $stockDelivery) => [
                        'reference' => $stockDelivery->reference,
                        'route'     => [
                            'name'       => 'grp.org.warehouses.show.incoming.stock_deliveries.show',
                            'parameters' => [$organisation->slug, $warehouse->slug, $stockDelivery->slug],
                        ],
                    ])->all(),
            ])
            ->filter(fn (array $purchaseOrder) => $purchaseOrder['lines'] || $purchaseOrder['open_deliveries'])
            ->values()
            ->all();
    }

    /**
     * Lines with units not on any live delivery yet. A submitted order's lines are not agreed yet, so all of them are.
     *
     * @return \Illuminate\Support\Collection<int, PurchaseOrderTransaction>
     */
    public static function receivableLines(PurchaseOrder $purchaseOrder): \Illuminate\Support\Collection
    {
        if ($purchaseOrder->state === PurchaseOrderStateEnum::SUBMITTED) {
            return $purchaseOrder->purchaseOrderTransactions()
                ->where('state', PurchaseOrderTransactionStateEnum::SUBMITTED)
                ->with(['orgStock', 'historicSupplierProduct'])
                ->select('purchase_order_transactions.*')
                ->selectRaw('purchase_order_transactions.quantity_ordered as quantity_ready_to_ship')
                ->get();
        }

        return StoreStockDeliveryFromPurchaseOrder::withQuantityReadyToShip(
            StoreStockDeliveryFromPurchaseOrder::whereShippable($purchaseOrder->purchaseOrderTransactions()),
            false
        )
            ->with(['orgStock', 'historicSupplierProduct'])
            ->get()
            ->filter(fn (PurchaseOrderTransaction $line) => (float) $line->quantity_ready_to_ship > StoreStockDeliveryFromPurchaseOrder::QUANTITY_TOLERANCE)
            ->values();
    }

    /**
     * @return array{suppliers?: list<array<string, mixed>>, purchase_orders?: list<array<string, mixed>>}
     */
    public function handle(Organisation $organisation, Warehouse $warehouse, ?int $orgSupplierId): array
    {
        return $orgSupplierId
            ? ['purchase_orders' => $this->purchaseOrders($organisation, $warehouse, $orgSupplierId)]
            : ['suppliers' => $this->suppliers($organisation)];
    }

    public function rules(): array
    {
        return [
            'org_supplier_id' => ['sometimes', 'nullable', 'integer'],
        ];
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): array
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($this->organisation, $warehouse, $this->validatedData['org_supplier_id'] ?? null);
    }
}
