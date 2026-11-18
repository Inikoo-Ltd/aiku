<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-18 15:30
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\Hydrators\PurchaseOrderHydrateDeliveryState;
use App\Actions\Procurement\PurchaseOrder\Hydrators\PurchaseOrderHydrateState;
use App\Actions\Procurement\PurchaseOrder\Hydrators\PurchaseOrderHydrateTransactions;
use App\Actions\Procurement\PurchaseOrder\Traits\HasPurchaseOrderHydrators;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Events\BroadcastAgentIncomingPurchaseOrdersChanged;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Reopens an order closed with CloseOldPurchaseOrder: the order and the lines it settled go back to the state they
 * had, a stock delivery linked by the close is unlinked, and the CHS exclusion returns to what it was before.
 */
class UndoCloseOldPurchaseOrder extends OrgAction
{
    use HasPurchaseOrderHydrators;

    private PurchaseOrder $purchaseOrder;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return CloseOldPurchaseOrder::userIsAgent($request->user(), $this->purchaseOrder);
    }

    public function handle(PurchaseOrder $purchaseOrder, array $modelData): PurchaseOrder
    {
        $close         = $purchaseOrder->closedAsOld();
        $stateBefore   = PurchaseOrderStateEnum::from($close['state_before']);
        $stockDelivery = $close['stock_delivery_id'] ? StockDelivery::find($close['stock_delivery_id']) : null;

        DB::transaction(function () use ($purchaseOrder, $close, $stateBefore, $stockDelivery) {
            $purchaseOrder->purchaseOrderTransactions()
                ->whereIn('id', $close['settled_transaction_ids'])
                ->where('state', PurchaseOrderTransactionStateEnum::SETTLED)
                ->update(['state' => $stateBefore === PurchaseOrderStateEnum::SUBMITTED ? PurchaseOrderTransactionStateEnum::SUBMITTED : PurchaseOrderTransactionStateEnum::CONFIRMED]);

            if ($stockDelivery) {
                $stockDelivery->purchaseOrders()->detach($purchaseOrder->id);
                $stockDelivery->update(['number_purchase_orders' => $stockDelivery->purchaseOrders()->count()]);
            }

            $data = $purchaseOrder->data ?? [];
            Arr::forget($data, 'closed_as_old');

            $purchaseOrder->update([
                'data'                 => $data,
                'state'                => $stateBefore,
                'settled_at'           => null,
                'chs_excluded'         => $close['chs_excluded_before'] ?? false,
                'chs_exclusion_reason' => $close['chs_exclusion_reason_before'] ?? null,
            ]);
        });

        $purchaseOrder->refresh();
        if ($stockDelivery) {
            PurchaseOrderHydrateDeliveryState::run($purchaseOrder);
        }
        PurchaseOrderHydrateState::run($purchaseOrder->refresh());
        PurchaseOrderHydrateTransactions::dispatch($purchaseOrder);
        $this->purchaseOrderHydrate($purchaseOrder);

        MarkPurchaseOrderStage::auditOverride($purchaseOrder, 'closed_as_old', PurchaseOrderStateEnum::SETTLED->value, $stateBefore->value, $modelData['reason']);
        BroadcastAgentIncomingPurchaseOrdersChanged::dispatchFor($purchaseOrder);

        return $purchaseOrder->refresh();
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        if ($this->purchaseOrder->closedAsOld() === null || $this->purchaseOrder->state !== PurchaseOrderStateEnum::SETTLED) {
            $validator->errors()->add('reason', __('The order is not closed as an old order'));
        }
    }

    public function action(PurchaseOrder $purchaseOrder, array $modelData): PurchaseOrder
    {
        $this->asAction      = true;
        $this->purchaseOrder = $purchaseOrder;
        $this->initialisation($purchaseOrder->organisation, $modelData);

        return $this->handle($purchaseOrder, $this->validatedData);
    }

    public function asController(PurchaseOrder $purchaseOrder, ActionRequest $request): PurchaseOrder
    {
        $this->purchaseOrder = $purchaseOrder;
        $this->initialisation($purchaseOrder->organisation, $request);

        return $this->handle($purchaseOrder, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
