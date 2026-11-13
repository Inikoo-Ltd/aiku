<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-05 14:10
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\PurchaseOrder\UI;

use App\Models\Procurement\AgentWarehouseStock;
use App\Actions\Procurement\AgentGoodsReceipt\StoreAgentGoodsReceipt;
use App\Enums\Procurement\AgentGoodsReceipt\AgentGoodsReceiptStateEnum;
use App\Enums\Procurement\AgentWarehouseStock\AgentWarehouseStockStateEnum;
use App\Models\Procurement\AgentGoodsReceiptItem;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * What still keeps the Packing and QC checks of Ready to Roll open on an order through an agent warehouse: the lines
 * received and not packed yet, and every goods receipt line with whether it is checked, with the routes that pack or
 * check them all.
 */
class GetPurchaseOrderReadyChecks
{
    use AsObject;

    /**
     * @return array{packing: array<string, mixed>, qc: array<string, mixed>}|null
     */
    public function handle(PurchaseOrder $purchaseOrder, bool $agentCanEdit): ?array
    {
        if (!$purchaseOrder->isAgentOrder() || !$purchaseOrder->agent || !GetPurchaseOrderStages::make()->usesAgentWarehouse($purchaseOrder)) {
            return null;
        }

        $receiptItems = AgentGoodsReceiptItem::where('purchase_order_id', $purchaseOrder->id)
            ->whereHas('agentGoodsReceipt', fn ($query) => $query->where('state', AgentGoodsReceiptStateEnum::RECEIVED))
            ->with(['agentGoodsReceipt:id,reference,state', 'agentWarehouseStock' => fn ($query) => $query->select('id', 'state', AgentWarehouseStock::heldQuantityColumn())])
            ->orderBy('id')
            ->get();

        $transactions = $purchaseOrder->purchaseOrderTransactions()
            ->whereIn('id', $receiptItems->pluck('purchase_order_transaction_id')->filter()->unique())
            ->with(['supplierProduct:id,code,name', 'orgStock:id,code,name', 'orgStock.tradeUnits.image'])
            ->get()
            ->keyBy('id');

        $isReceivedInFull  = StoreAgentGoodsReceipt::make()->isReceivedInFull($purchaseOrder);
        $agentOrganisation = $purchaseOrder->agent->organisation;

        return [
            'packing' => [
                'received_in_full' => $isReceivedInFull,
                'items'            => $this->itemsToPack($receiptItems, $transactions),
                'route'            => $agentCanEdit ? [
                    'name'       => 'grp.models.purchase-order.transaction.pack_many',
                    'parameters' => ['purchaseOrder' => $purchaseOrder->id],
                ] : null,
            ],
            'qc'      => [
                'received_in_full' => $isReceivedInFull,
                'items'            => $this->itemsForQc($receiptItems, $transactions),
                'route'            => $agentCanEdit ? [
                    'name'       => 'grp.org.agent.warehouse.received.goods_receipts.items.check_many',
                    'parameters' => [$agentOrganisation->slug],
                ] : null,
            ],
        ];
    }

    /**
     * @param  Collection<int, AgentGoodsReceiptItem>  $receiptItems
     * @param  Collection<int, PurchaseOrderTransaction>  $transactions
     *
     * @return array<int, array<string, mixed>>
     */
    private function itemsToPack(Collection $receiptItems, Collection $transactions): array
    {
        return $receiptItems
            ->filter(fn (AgentGoodsReceiptItem $item) => $item->agentWarehouseStock?->state === AgentWarehouseStockStateEnum::RECEIVED && (float) $item->agentWarehouseStock->quantity > 0)
            ->groupBy('purchase_order_transaction_id')
            ->map(fn (Collection $items, int $transactionId) => [
                ...$this->product($transactions->get($transactionId)),
                'id'            => $transactionId,
                'quantity'      => (float) $items->sum(fn (AgentGoodsReceiptItem $item) => (float) $item->agentWarehouseStock->quantity),
                'has_unchecked' => $items->contains(fn (AgentGoodsReceiptItem $item) => !$item->isChecked()),
            ])
            ->sortBy('code')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, AgentGoodsReceiptItem>  $receiptItems
     * @param  Collection<int, PurchaseOrderTransaction>  $transactions
     *
     * @return array<int, array<string, mixed>>
     */
    private function itemsForQc(Collection $receiptItems, Collection $transactions): array
    {
        return $receiptItems
            ->map(fn (AgentGoodsReceiptItem $item) => [
                ...$this->product($transactions->get($item->purchase_order_transaction_id)),
                'id'                => $item->id,
                'reference'         => $item->agentGoodsReceipt->reference,
                'quantity'          => (float) $item->quantity,
                'is_checked'        => $item->isChecked(),
                'checked_at'        => $item->checked_at,
                'quantity_rejected' => (float) $item->quantity_rejected,
                'is_checkable'      => !$item->isChecked() && $item->isEditable(),
            ])
            ->sortBy(fn (array $item) => [$item['is_checked'], $item['code']])
            ->values()
            ->all();
    }

    /**
     * @return array{code: ?string, name: ?string, image_thumbnail: ?array}
     */
    private function product(?PurchaseOrderTransaction $transaction): array
    {
        $tradeUnit = $transaction?->orgStock?->tradeUnits->first(fn ($tradeUnit) => $tradeUnit->image_id !== null);

        return [
            'code'            => $transaction?->supplierProduct?->code ?? $transaction?->orgStock?->code,
            'name'            => $transaction?->supplierProduct?->name ?? $transaction?->orgStock?->name,
            'image_thumbnail' => $tradeUnit?->imageSources(80, 80),
        ];
    }
}
