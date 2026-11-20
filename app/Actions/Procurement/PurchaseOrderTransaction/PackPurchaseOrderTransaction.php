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
use App\Actions\Procurement\AgentWarehouseStock\PackAgentWarehouseStock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;
use App\Actions\Procurement\PurchaseOrder\RecordPurchaseOrderHandedOverWhenReady;

/**
 * The agent packs what it received of a purchase order line, all of it or only some units: that warehouse stock is
 * packed for the next container, oldest first, and its check can no longer be changed, so goods packed before they
 * were checked stay unchecked.
 */
class PackPurchaseOrderTransaction extends OrgAction
{
    private const float QUANTITY_TOLERANCE = 0.0001;

    private PurchaseOrderTransaction $purchaseOrderTransaction;

    /**
     * The agent's warehouse stock from goods receipts not cancelled, received and not packed yet. A part packed off a
     * goods receipt row and taken back while the row stays packed has no receipt line of its own (left joined).
     */
    public static function receivedStocks(): Builder
    {
        return DB::table('agent_warehouse_stocks')
            ->join('agent_goods_receipts', 'agent_goods_receipts.id', 'agent_warehouse_stocks.agent_goods_receipt_id')
            ->leftJoin('agent_goods_receipt_items', 'agent_goods_receipt_items.agent_warehouse_stock_id', 'agent_warehouse_stocks.id')
            ->where('agent_goods_receipts.state', AgentGoodsReceiptStateEnum::RECEIVED->value)
            ->where('agent_warehouse_stocks.state', AgentWarehouseStockStateEnum::RECEIVED->value)
            ->where('agent_warehouse_stocks.quantity', '>', 0)
            ->whereRaw(AgentWarehouseStock::heldQuantitySql().' > 0');
    }

    public static function uncheckedStocks(): Builder
    {
        return self::receivedStocks()
            ->whereNotNull('agent_goods_receipt_items.id')
            ->whereNull('agent_goods_receipt_items.checked_at');
    }

    public static function packableQuantity(PurchaseOrderTransaction $purchaseOrderTransaction): float
    {
        return round((float) self::receivedStocks()
            ->where('agent_warehouse_stocks.purchase_order_transaction_id', $purchaseOrderTransaction->id)
            ->sum(DB::raw(AgentWarehouseStock::heldQuantitySql())), 3);
    }

    /**
     * @param  float|null  $quantity  units to pack, all that is received waiting to be packed when null
     */
    public function handle(PurchaseOrderTransaction $purchaseOrderTransaction, ?float $quantity = null): PurchaseOrderTransaction
    {
        DB::transaction(function () use ($purchaseOrderTransaction, $quantity) {
            $stockIds = self::receivedStocks()
                ->where('agent_warehouse_stocks.purchase_order_transaction_id', $purchaseOrderTransaction->id)
                ->pluck('agent_warehouse_stocks.id');

            $stocks = AgentWarehouseStock::whereIn('id', $stockIds)
                ->where('state', AgentWarehouseStockStateEnum::RECEIVED)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($quantity === null) {
                $stocks->each(fn (AgentWarehouseStock $stock) => $stock->update(['state' => AgentWarehouseStockStateEnum::PACKED_FOR_NEXT_CONTAINER]));

                return;
            }

            $this->packQuantity($stocks, round($quantity, 3));
        });

        RecordPurchaseOrderHandedOverWhenReady::make()->forPurchaseOrderIds([$purchaseOrderTransaction->purchase_order_id]);

        return $purchaseOrderTransaction;
    }

    /**
     * @param  Collection<int, AgentWarehouseStock>  $stocks
     */
    private function packQuantity(Collection $stocks, float $quantity): void
    {
        $left = $quantity;

        foreach ($stocks as $stock) {
            if ($left <= self::QUANTITY_TOLERANCE) {
                break;
            }

            $held = round((float) AgentWarehouseStock::whereKey($stock->id)->selectRaw(AgentWarehouseStock::heldQuantitySql().' as held')->value('held'), 3);
            if ($held <= 0) {
                continue;
            }

            if ($left >= $held - self::QUANTITY_TOLERANCE) {
                PackAgentWarehouseStock::packLocked($stock);
                $left = round($left - $held, 3);
            } else {
                PackAgentWarehouseStock::packLocked($stock, $left);
                $left = 0;
            }
        }

        if ($left > self::QUANTITY_TOLERANCE) {
            throw ValidationException::withMessages(['quantity' => __('Only :units units are received waiting to be packed', ['units' => round($quantity - $left, 3)])]);
        }
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

    public function rules(): array
    {
        return [
            'quantity' => ['sometimes', 'nullable', 'numeric', 'gt:0'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        $packable = self::packableQuantity($this->purchaseOrderTransaction);

        if ($packable <= 0) {
            $validator->errors()->add('pack', __('Nothing of this item is received waiting to be packed'));

            return;
        }

        $quantity = $this->get('quantity');
        if ($quantity !== null && (float) $quantity > $packable + self::QUANTITY_TOLERANCE) {
            $validator->errors()->add('quantity', __('Pack between 1 and :units units', ['units' => $packable]));
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

        $quantity = $this->validatedData['quantity'] ?? null;
        $this->handle($purchaseOrderTransaction, $quantity === null ? null : (float) $quantity);

        return back();
    }
}
