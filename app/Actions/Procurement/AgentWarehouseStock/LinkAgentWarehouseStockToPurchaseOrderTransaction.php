<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-10 14:15
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\AgentWarehouseStock;

use App\Actions\OrgAction;
use App\Actions\Procurement\AgentWarehouseStock\UI\GetAgentWarehouseStockPurchaseOrderLines;
use App\Actions\Procurement\PurchaseOrder\RecordPurchaseOrderHandedOverWhenReady;
use App\Models\Procurement\AgentWarehouseStock;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * The agent says which purchase order line a line of its warehouse belongs to. Lines imported from the stock sheet only
 * know the order the sheet named, or a guessed one, and never the line, so they can not go in a container until the
 * agent picks it. A line received through a goods receipt already knows its line and is never moved.
 */
class LinkAgentWarehouseStockToPurchaseOrderTransaction extends OrgAction
{
    public function handle(AgentWarehouseStock $agentWarehouseStock, int $purchaseOrderTransactionId): AgentWarehouseStock
    {
        $stock = DB::transaction(function () use ($agentWarehouseStock, $purchaseOrderTransactionId) {
            $stock = AgentWarehouseStock::whereKey($agentWarehouseStock->id)->lockForUpdate()->firstOrFail();
            $code  = $stock->supplierProduct?->code;

            if ($stock->agent_goods_receipt_id) {
                throw ValidationException::withMessages(['purchase_order_transaction_id' => __(':code was received through a goods receipt and stays on its purchase order line', ['code' => $code])]);
            }

            $line = GetAgentWarehouseStockPurchaseOrderLines::linkableLines($stock)
                ->where('purchase_order_transactions.id', $purchaseOrderTransactionId)
                ->select(['purchase_order_transactions.id', 'purchase_order_transactions.purchase_order_id', DB::raw(GetAgentWarehouseStockPurchaseOrderLines::hasGoodsReceiptSql().' as has_goods_receipt')])
                ->first();

            if (!$line) {
                throw ValidationException::withMessages(['purchase_order_transaction_id' => __('This purchase order line is not an open order of :code', ['code' => $code])]);
            }

            if ($line->has_goods_receipt) {
                throw ValidationException::withMessages(['purchase_order_transaction_id' => __('This purchase order line was already received through a goods receipt')]);
            }

            $stock->update([
                'purchase_order_id'             => $line->purchase_order_id,
                'purchase_order_transaction_id' => $line->id,
            ]);

            return $stock;
        });

        RecordPurchaseOrderHandedOverWhenReady::make()->forPurchaseOrderIds([$stock->purchase_order_id]);

        return $stock;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    public function rules(): array
    {
        return [
            'purchase_order_transaction_id' => ['required', 'integer'],
        ];
    }

    public function asController(Organisation $organisation, AgentWarehouseStock $agentWarehouseStock, ActionRequest $request): AgentWarehouseStock
    {
        abort_unless($organisation->agent && $agentWarehouseStock->agent_id === $organisation->agent->id, 404);
        $this->initialisation($organisation, $request);

        return $this->handle($agentWarehouseStock, (int) $this->validatedData['purchase_order_transaction_id']);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
