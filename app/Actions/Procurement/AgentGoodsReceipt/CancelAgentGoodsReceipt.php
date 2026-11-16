<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-10-16 15:45
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\AgentGoodsReceipt;

use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\RecordPurchaseOrderHandedOverWhenReady;
use App\Enums\Procurement\AgentGoodsReceipt\AgentGoodsReceiptStateEnum;
use App\Enums\Procurement\AgentWarehouseStock\AgentWarehouseStockStateEnum;
use App\Models\Procurement\AgentGoodsReceipt;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * Undoes a goods receipt booked by mistake: its lines go back in production and the stock it put in the warehouse goes
 * away. Only while all that stock is still in the warehouse as received, not packed for a container.
 */
class CancelAgentGoodsReceipt extends OrgAction
{
    use WithAgentGoodsReceipt;

    public function handle(AgentGoodsReceipt $receipt, ?User $user = null): AgentGoodsReceipt
    {
        $receipt = DB::transaction(function () use ($receipt, $user) {
            $receipt = AgentGoodsReceipt::lockForUpdate()->findOrFail($receipt->id);

            if ($receipt->state === AgentGoodsReceiptStateEnum::CANCELLED) {
                throw ValidationException::withMessages(['state' => __('This goods receipt is already cancelled')]);
            }

            $items = $receipt->items()->orderBy('id')->lockForUpdate()->get();
            $receipt->warehouseStocks()->orderBy('id')->lockForUpdate()->get();
            $moved = $items->load('agentWarehouseStock')
                ->contains(fn ($item) => $item->agentWarehouseStock?->state !== AgentWarehouseStockStateEnum::RECEIVED);

            if ($moved) {
                throw ValidationException::withMessages(['state' => __('Part of these goods was already packed or moved, it can not be cancelled')]);
            }

            $receipt->warehouseStocks()->delete();
            $receipt->update([
                'state'                => AgentGoodsReceiptStateEnum::CANCELLED,
                'cancelled_at'         => now(),
                'cancelled_by_user_id' => $user?->id,
            ]);

            return $receipt;
        });

        RecordPurchaseOrderHandedOverWhenReady::make()->forPurchaseOrderIds($receipt->items()->pluck('purchase_order_id'));

        return $receipt;
    }

    public function asController(Organisation $organisation, AgentGoodsReceipt $agentGoodsReceipt, ActionRequest $request): AgentGoodsReceipt
    {
        $this->ensureReceiptOfOrganisationAgent($organisation, $agentGoodsReceipt);
        $this->initialisation($organisation, $request);

        return $this->handle($agentGoodsReceipt, $request->user());
    }

    public function htmlResponse(AgentGoodsReceipt $receipt): RedirectResponse
    {
        return Redirect::back()->with('notification', [
            'status'  => 'success',
            'title'   => __('Goods receipt :reference cancelled', ['reference' => $receipt->reference]),
            'message' => __('Its lines are back in production'),
        ]);
    }
}
