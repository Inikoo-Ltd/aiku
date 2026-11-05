<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-10-21 15:29
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\PurchaseOrderTransaction;

use App\Actions\OrgAction;
use App\Enums\Procurement\AgentGoodsReceipt\AgentGoodsReceiptStateEnum;
use App\Enums\Procurement\AgentWarehouseStock\AgentWarehouseStockStateEnum;
use App\Models\Procurement\AgentWarehouseStock;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;
use App\Actions\Procurement\PurchaseOrder\RecordPurchaseOrderHandedOverWhenReady;

/**
 * The agent packs what it received of a purchase order line: that warehouse stock is packed for the next container,
 * and its check can no longer be changed, so goods packed before they were checked stay unchecked.
 */
class PackPurchaseOrderTransaction extends OrgAction
{
    private PurchaseOrderTransaction $purchaseOrderTransaction;

    /**
     * The agent's warehouse stock from goods receipts not cancelled, received and not packed yet.
     */
    public static function receivedStocks(): Builder
    {
        return DB::table('agent_warehouse_stocks')
            ->join('agent_goods_receipt_items', 'agent_goods_receipt_items.agent_warehouse_stock_id', 'agent_warehouse_stocks.id')
            ->join('agent_goods_receipts', 'agent_goods_receipts.id', 'agent_goods_receipt_items.agent_goods_receipt_id')
            ->where('agent_goods_receipts.state', AgentGoodsReceiptStateEnum::RECEIVED->value)
            ->where('agent_warehouse_stocks.state', AgentWarehouseStockStateEnum::RECEIVED->value)
            ->where('agent_warehouse_stocks.quantity', '>', 0);
    }

    public function handle(PurchaseOrderTransaction $purchaseOrderTransaction): PurchaseOrderTransaction
    {
        DB::transaction(function () use ($purchaseOrderTransaction) {
            $stockIds = self::receivedStocks()
                ->where('agent_warehouse_stocks.purchase_order_transaction_id', $purchaseOrderTransaction->id)
                ->pluck('agent_warehouse_stocks.id');

            AgentWarehouseStock::whereIn('id', $stockIds)
                ->where('state', AgentWarehouseStockStateEnum::RECEIVED)
                ->lockForUpdate()
                ->get()
                ->each(fn (AgentWarehouseStock $stock) => $stock->update(['state' => AgentWarehouseStockStateEnum::PACKED_FOR_NEXT_CONTAINER]));
        });

        RecordPurchaseOrderHandedOverWhenReady::make()->forPurchaseOrderIds([$purchaseOrderTransaction->purchase_order_id]);

        return $purchaseOrderTransaction;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $purchaseOrder       = $this->purchaseOrderTransaction->purchaseOrder;
        $agentOrganisationId = $purchaseOrder->isAgentOrder() ? $purchaseOrder->agent?->organisation_id : null;

        return $agentOrganisationId !== null && $request->user()->authTo("procurement.$agentOrganisationId.edit");
    }

    public function afterValidator(Validator $validator): void
    {
        $hasPackable = self::receivedStocks()
            ->where('agent_warehouse_stocks.purchase_order_transaction_id', $this->purchaseOrderTransaction->id)
            ->exists();

        if (!$hasPackable) {
            $validator->errors()->add('pack', __('Nothing of this item is received waiting to be packed'));
        }
    }

    public function action(PurchaseOrderTransaction $purchaseOrderTransaction): PurchaseOrderTransaction
    {
        $this->asAction                 = true;
        $this->purchaseOrderTransaction = $purchaseOrderTransaction;
        $this->initialisation($purchaseOrderTransaction->organisation, []);

        return $this->handle($purchaseOrderTransaction);
    }

    public function asController(PurchaseOrder $purchaseOrder, PurchaseOrderTransaction $purchaseOrderTransaction, ActionRequest $request): RedirectResponse
    {
        abort_unless($purchaseOrderTransaction->purchase_order_id === $purchaseOrder->id, 404);
        $this->purchaseOrderTransaction = $purchaseOrderTransaction;
        $this->initialisation($purchaseOrderTransaction->organisation, $request);

        $this->handle($purchaseOrderTransaction);

        return back();
    }
}
