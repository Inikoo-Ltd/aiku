<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-10-16 16:35
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\AgentGoodsReceipt;

use App\Actions\Helpers\Media\SaveModelAttachment;
use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\MarkPurchaseOrderStage;
use App\Enums\Procurement\AgentGoodsReceipt\AgentGoodsReceiptStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\Procurement\AgentGoodsReceipt;
use App\Models\Procurement\AgentGoodsReceiptItem;
use App\Models\Procurement\AgentWarehouseStock;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use App\Actions\Procurement\PurchaseOrder\RecordPurchaseOrderHandedOverWhenReady;

/**
 * The agent checks a line of a goods receipt for rejects. What passed stays as warehouse stock; rejected units are owed
 * by the supplier again and go back to In production, also after the order's quality check. Production done follows:
 * cleared while rejects are owed, set again when a new check leaves the order received in full.
 */
class CheckAgentGoodsReceiptItem extends OrgAction
{
    use WithAgentGoodsReceipt;

    public const string PHOTO_SCOPE = 'reject_photo';

    public function handle(AgentGoodsReceiptItem $item, array $modelData, ?User $user = null): AgentGoodsReceiptItem
    {
        $photos = Arr::pull($modelData, 'photos', []);

        DB::transaction(function () use ($item, $modelData, $user) {
            $transaction = $item->purchase_order_transaction_id ? PurchaseOrderTransaction::lockForUpdate()->find($item->purchase_order_transaction_id) : null;
            $item        = AgentGoodsReceiptItem::lockForUpdate()->findOrFail($item->id);
            if ($item->agent_warehouse_stock_id) {
                $item->setRelation('agentWarehouseStock', AgentWarehouseStock::lockForUpdate()->find($item->agent_warehouse_stock_id));
            }

            if (!$item->isEditable()) {
                throw ValidationException::withMessages(['quantity_rejected' => __('These goods are already packed for a container or the goods receipt was cancelled: the check can no longer be changed')]);
            }

            $quantityRejected = round((float) $modelData['quantity_rejected'], 3);
            if ($quantityRejected > (float) $item->quantity) {
                throw ValidationException::withMessages(['quantity_rejected' => __('Only :quantity units were received: no more than that can be rejected', ['quantity' => (float) $item->quantity])]);
            }

            if ($transaction) {
                $keptOnOtherLines = (float) AgentGoodsReceiptItem::where('purchase_order_transaction_id', $transaction->id)
                    ->where('id', '!=', $item->id)
                    ->whereHas('agentGoodsReceipt', fn ($query) => $query->where('state', AgentGoodsReceiptStateEnum::RECEIVED))
                    ->sum(DB::raw('quantity - coalesce(quantity_rejected, 0)'));

                $keptAfterCheck = $keptOnOtherLines + (float) $item->quantity - $quantityRejected;
                if ($keptAfterCheck > (float) $transaction->quantity_ordered + 0.0001) {
                    throw ValidationException::withMessages(['quantity_rejected' => __('The replacements for these rejects were already received: at least :quantity units must stay rejected', [
                        'quantity' => round($keptAfterCheck - (float) $transaction->quantity_ordered + $quantityRejected, 3),
                    ])]);
                }
            }

            $item->update([
                'quantity_rejected'  => $quantityRejected,
                'reject_reason'      => Arr::get($modelData, 'reject_reason'),
                'checked_at'         => now(),
                'checked_by_user_id' => $user?->id,
            ]);
            $item->agentWarehouseStock?->update(['quantity' => $item->quantityAccepted()]);
        });

        $this->savePhotos($item, $photos);
        $this->syncProductionDone($item->refresh());
        RecordPurchaseOrderHandedOverWhenReady::make()->forPurchaseOrderIds([$item->purchase_order_id]);

        return $item;
    }

    private function syncProductionDone(AgentGoodsReceiptItem $item): void
    {
        $purchaseOrder = $item->purchase_order_id ? PurchaseOrder::find($item->purchase_order_id) : null;
        if (!in_array($purchaseOrder?->state, [PurchaseOrderStateEnum::SUBMITTED, PurchaseOrderStateEnum::CONFIRMED], true)) {
            return;
        }

        $isReceivedInFull = StoreAgentGoodsReceipt::make()->isReceivedInFull($purchaseOrder);
        $reference        = $item->agentGoodsReceipt->reference;
        $code             = $item->supplierProduct?->code;

        if ($purchaseOrder->produced_at && !$isReceivedInFull) {
            MarkPurchaseOrderStage::make()->action($purchaseOrder, [
                'field'  => 'produced_at',
                'date'   => null,
                'reason' => __('Goods receipt :reference: :code has rejects, the supplier owes them again', ['reference' => $reference, 'code' => $code]),
            ]);
        } elseif (!$purchaseOrder->produced_at && $isReceivedInFull) {
            $lastReceivedAt = AgentGoodsReceipt::where('state', AgentGoodsReceiptStateEnum::RECEIVED)
                ->whereHas('items', fn ($query) => $query->where('purchase_order_id', $purchaseOrder->id))
                ->max('received_at');

            MarkPurchaseOrderStage::make()->action($purchaseOrder, [
                'field'  => 'produced_at',
                'date'   => Carbon::parse($lastReceivedAt ?? now())->toDateString(),
                'reason' => __('Goods receipt :reference: :code checked again, all goods of this order are at the agent warehouse', ['reference' => $reference, 'code' => $code]),
            ]);
        }
    }

    /**
     * @param  array<int, UploadedFile>  $photos
     */
    private function savePhotos(AgentGoodsReceiptItem $item, array $photos): void
    {
        foreach ($photos as $photo) {
            SaveModelAttachment::make()->action($item, [
                'path'         => $photo->getPathName(),
                'originalName' => $photo->getClientOriginalName(),
                'extension'    => $photo->guessExtension() ?? $photo->getClientOriginalExtension(),
                'scope'        => self::PHOTO_SCOPE,
                'caption'      => __('Rejected :code', ['code' => $item->supplierProduct?->code]),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'quantity_rejected' => ['required', 'numeric', 'min:0'],
            'reject_reason'     => ['sometimes', 'nullable', 'string', 'max:5000'],
            'photos'            => ['sometimes', 'array', 'max:10'],
            'photos.*'          => ['file', 'mimes:jpg,jpeg,png,webp,heic,heif', 'max:20000'],
        ];
    }

    public function asController(Organisation $organisation, AgentGoodsReceipt $agentGoodsReceipt, AgentGoodsReceiptItem $agentGoodsReceiptItem, ActionRequest $request): AgentGoodsReceiptItem
    {
        $this->ensureReceiptOfOrganisationAgent($organisation, $agentGoodsReceipt);
        abort_unless($agentGoodsReceiptItem->agent_goods_receipt_id === $agentGoodsReceipt->id, 404);
        $this->initialisation($organisation, $request);

        return $this->handle($agentGoodsReceiptItem, $this->validatedData, $request->user());
    }

    public function htmlResponse(AgentGoodsReceiptItem $item): RedirectResponse
    {
        return Redirect::back()->with('notification', [
            'status'  => 'success',
            'title'   => __('Checked :code', ['code' => $item->supplierProduct?->code]),
            'message' => (float) $item->quantity_rejected > 0
                ? __(':units units rejected, back in production', ['units' => (float) $item->quantity_rejected])
                : __('No rejects'),
        ]);
    }
}
