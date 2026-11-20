<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-10-16 16:45
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\AgentGoodsReceipt\UI;

use App\Actions\Procurement\AgentWarehouseStock\UI\IndexAgentProductsInProduction;
use App\Actions\Procurement\PurchaseOrderTransaction\UI\IndexPurchaseOrderTransactions;
use App\Enums\Procurement\AgentGoodsReceipt\AgentGoodsReceiptStateEnum;
use App\Enums\Procurement\AgentWarehouseStock\AgentWarehouseStockStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Models\Helpers\Media;
use App\Models\Procurement\AgentGoodsReceipt;
use App\Models\Procurement\AgentGoodsReceiptItem;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SysAdmin\User;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * What the agent received of each line of a purchase order over its goods receipts, the rejects found when checked,
 * and what the supplier still owes: ordered less what was kept.
 */
class GetPurchaseOrderGoodsReceipts
{
    use AsObject;

    public function handle(PurchaseOrder $purchaseOrder, bool $canEdit): ?array
    {
        $agentOrganisation = $purchaseOrder->agent->organisation;

        $items = AgentGoodsReceiptItem::where('purchase_order_id', $purchaseOrder->id)
            ->whereHas('agentGoodsReceipt', fn ($query) => $query->where('state', AgentGoodsReceiptStateEnum::RECEIVED))
            ->with(['agentGoodsReceipt.user:id,contact_name,username', 'agentWarehouseStock:id,state', 'checkedBy:id,contact_name,username', 'attachments'])
            ->get()
            ->sortBy(fn (AgentGoodsReceiptItem $item) => [$item->agentGoodsReceipt->received_at->toDateString(), $item->id])
            ->groupBy('purchase_order_transaction_id');

        $scopes     = $canEdit ? IndexAgentProductsInProduction::receiveScopes($purchaseOrder->agent) : null;
        $receivable = $scopes && collect($scopes['purchase_orders'])->contains('value', $purchaseOrder->id);

        if ($items->isEmpty() && !$receivable && !$purchaseOrder->hasProductionStarted()) {
            return null;
        }

        $lines = $purchaseOrder->purchaseOrderTransactions()
            ->whereNotNull('supplier_product_id')
            ->select('purchase_order_transactions.*')
            ->tap(fn ($query) => IndexPurchaseOrderTransactions::selectAgentWarehouseQuantities($query))
            ->with(['supplierProduct:id,code,name,units_per_pack', 'orgStock.tradeUnits.image'])
            ->get()
            ->sortBy(fn (PurchaseOrderTransaction $transaction) => $transaction->supplierProduct?->code)
            ->map(fn (PurchaseOrderTransaction $transaction) => $this->line($transaction, $items->get($transaction->id, collect()), $agentOrganisation->slug, $canEdit, $this->isPastProduction($purchaseOrder)))
            ->values();

        return [
            'can_edit'       => $canEdit,
            'check_many_route' => $canEdit ? [
                'name'       => 'grp.org.agent.warehouse.received.goods_receipts.items.check_many',
                'parameters' => [$agentOrganisation->slug],
            ] : null,
            'lines'          => $lines,
            'receive'        => $receivable ? [
                'scopes'            => $scopes,
                'purchase_order_id' => $purchaseOrder->id,
            ] : null,
            'goods_receipts' => $items->flatten(1)
                ->map(fn (AgentGoodsReceiptItem $item) => $item->agentGoodsReceipt)
                ->unique('id')
                ->sortByDesc(fn (AgentGoodsReceipt $receipt) => $receipt->received_at->toDateString())
                ->map(fn (AgentGoodsReceipt $receipt) => [
                    'reference'          => $receipt->reference,
                    'received_at'        => $receipt->received_at->toDateString(),
                    'show_route'         => ['name' => 'grp.org.agent.warehouse.received.goods_receipts.show', 'parameters' => [$agentOrganisation->slug, $receipt->slug]],
                    'receipt_note_route' => ['name' => 'grp.org.agent.warehouse.received.goods_receipts.receipt_note', 'parameters' => [$agentOrganisation->slug, $receipt->slug]],
                ])
                ->values(),
        ];
    }

    /**
     * Past production only the rejected units are still being made, as on In production: the rest is ready or on its way.
     */
    private function isPastProduction(PurchaseOrder $purchaseOrder): bool
    {
        return $purchaseOrder->hasProductionStarted()
            && ($purchaseOrder->qc_passed_at !== null || !in_array($purchaseOrder->delivery_state?->value, ['in_process', 'confirmed'], true));
    }

    private function line(PurchaseOrderTransaction $transaction, Collection $items, string $agentOrganisationSlug, bool $canEdit, bool $isPastProduction): array
    {
        $ordered   = (float) $transaction->quantity_ordered;
        $received  = (float) $items->sum('quantity');
        $rejected  = (float) $items->sum('quantity_rejected');
        $accepted  = $received - $rejected;
        $owed      = max($ordered - $accepted, 0);
        $tradeUnit = $transaction->orgStock?->tradeUnits->first(fn ($tradeUnit) => $tradeUnit->image_id !== null);
        $isLive    = in_array($transaction->state, [PurchaseOrderTransactionStateEnum::SUBMITTED, PurchaseOrderTransactionStateEnum::CONFIRMED], true);

        return [
            'id'              => $transaction->id,
            'code'            => $transaction->supplierProduct?->code,
            'name'            => $transaction->supplierProduct?->name,
            'image_thumbnail' => $tradeUnit?->imageSources(80, 80),
            'image_full'      => $tradeUnit?->image?->fullResolutionUrls(),
            'state'           => $transaction->state->value,
            'units_per_pack'  => (float) ($transaction->orgStock?->packed_in ?: $transaction->supplierProduct?->units_per_pack ?: 1),
            'ordered'         => $ordered,
            'received'        => $received,
            'rejected'        => $rejected,
            'accepted'        => $accepted,
            'outstanding'     => $isLive ? ($isPastProduction ? min($owed, $rejected) : $owed) : 0,
            'unchecked'       => $items->filter(fn (AgentGoodsReceiptItem $item) => !$item->isChecked())->count(),
            'items'           => $items->map(fn (AgentGoodsReceiptItem $item) => $this->item($item, $agentOrganisationSlug, $canEdit))->values(),
            'packing'         => $this->packing($transaction, $canEdit),
        ];
    }

    /**
     * What of the line is received waiting to be packed and what is packed outside a container, with packing or
     * unpacking it for the agent editing its own order.
     */
    private function packing(PurchaseOrderTransaction $transaction, bool $canEdit): array
    {
        $packable = round((float) $transaction->getAttribute('quantity_packable'), 3);
        $packed   = round((float) $transaction->getAttribute('quantity_packed'), 3);
        $route    = fn (string $name) => [
            'name'       => $name,
            'parameters' => [
                'purchaseOrder'            => $transaction->purchase_order_id,
                'purchaseOrderTransaction' => $transaction->id,
            ],
            'method'     => 'patch',
        ];

        return [
            'quantity_packable' => $packable,
            'quantity_to_check' => round((float) $transaction->getAttribute('quantity_to_check'), 3),
            'quantity_packed'   => $packed,
            'pack_route'        => $canEdit && $packable > 0 ? $route('grp.models.purchase-order.transaction.pack') : null,
            'unpack_route'      => $canEdit && $packed > 0 ? $route('grp.models.purchase-order.transaction.unpack') : null,
        ];
    }

    private function item(AgentGoodsReceiptItem $item, string $agentOrganisationSlug, bool $canEdit): array
    {
        $receipt = $item->agentGoodsReceipt;
        $person  = fn (?User $user) => $user ? ($user->contact_name ?: $user->username) : null;

        return [
            'id'                => $item->id,
            'reference'         => $receipt->reference,
            'show_route'        => ['name' => 'grp.org.agent.warehouse.received.goods_receipts.show', 'parameters' => [$agentOrganisationSlug, $receipt->slug]],
            'received_at'       => $receipt->received_at->toDateString(),
            'quantity'          => (float) $item->quantity,
            'quantity_rejected' => $item->quantity_rejected === null ? null : (float) $item->quantity_rejected,
            'is_checked'        => $item->isChecked(),
            'reject_reason'     => $item->reject_reason,
            'received_by'       => $person($receipt->user),
            'checked_by'        => $person($item->checkedBy),
            'is_packed'         => $item->agentWarehouseStock !== null && $item->agentWarehouseStock->state !== AgentWarehouseStockStateEnum::RECEIVED,
            'photos'            => $item->attachments->map(fn (Media $media) => [
                'id'   => $media->id,
                'name' => $media->file_name,
                'url'  => route('grp.media.download', [$media->ulid, 'inline' => 1]),
            ])->values(),
            'check_route'       => $canEdit && $item->isEditable() ? [
                'name'       => 'grp.org.agent.warehouse.received.goods_receipts.items.check',
                'parameters' => [$agentOrganisationSlug, $receipt->slug, $item->id],
            ] : null,
        ];
    }
}
