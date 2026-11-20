<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-20 14:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\PurchaseOrderTransaction;

use App\Actions\OrgAction;
use App\Actions\Procurement\AgentWarehouseStock\UnpackAgentWarehouseStock;
use App\Actions\Procurement\PurchaseOrder\RecordPurchaseOrderHandedOverWhenReady;
use App\Enums\Procurement\AgentWarehouseStock\AgentWarehouseStockStateEnum;
use App\Models\Procurement\AgentWarehouseStock;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * The agent takes back what it packed of a purchase order line by mistake: every packed row the agent still holds goes
 * back to received, waiting to be packed again (UnpackAgentWarehouseStock). Units already in a container stay packed.
 */
class UnpackPurchaseOrderTransaction extends OrgAction
{
    private PurchaseOrderTransaction $purchaseOrderTransaction;

    public static function packedStocks(PurchaseOrderTransaction $purchaseOrderTransaction): Builder
    {
        return AgentWarehouseStock::where('purchase_order_transaction_id', $purchaseOrderTransaction->id)
            ->where('state', '!=', AgentWarehouseStockStateEnum::RECEIVED)
            ->whereHeld();
    }

    public function handle(PurchaseOrderTransaction $purchaseOrderTransaction): PurchaseOrderTransaction
    {
        DB::transaction(function () use ($purchaseOrderTransaction) {
            $stockIds = self::packedStocks($purchaseOrderTransaction)->orderByDesc('id')->pluck('id');

            foreach ($stockIds as $stockId) {
                $stock = AgentWarehouseStock::whereKey($stockId)->lockForUpdate()->first();
                if ($stock && $stock->state !== AgentWarehouseStockStateEnum::RECEIVED && $this->isHeld($stock)) {
                    UnpackAgentWarehouseStock::unpackLocked($stock);
                }
            }
        });

        RecordPurchaseOrderHandedOverWhenReady::make()->forPurchaseOrderIds([$purchaseOrderTransaction->purchase_order_id]);

        return $purchaseOrderTransaction;
    }

    private function isHeld(AgentWarehouseStock $stock): bool
    {
        return AgentWarehouseStock::whereKey($stock->id)->whereHeld()->exists();
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
        if (!self::packedStocks($this->purchaseOrderTransaction)->exists()) {
            $validator->errors()->add('unpack', __('Nothing of this item is packed outside a container'));
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
