<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-20 16:56
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\PurchaseOrderTransaction;

use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\CalculatePurchaseOrderTotalAmounts;
use App\Actions\Procurement\PurchaseOrder\Hydrators\PurchaseOrderHydrateTransactions;
use App\Actions\Procurement\PurchaseOrder\NotifyBuyerOfAgentStageChange;
use App\Actions\Traits\Authorisations\WithPurchaseOrderAgentEditAuthorisation;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * Puts a cancelled item back on its order with the quantity and unit cost it had, in the state the order's lines carry.
 * Items cancelled by a move or a pull are given back with Undo move instead.
 */
class UndoCancelPurchaseOrderTransaction extends OrgAction
{
    use WithPurchaseOrderAgentEditAuthorisation;

    public function handle(PurchaseOrderTransaction $purchaseOrderTransaction): PurchaseOrderTransaction
    {
        $purchaseOrderTransaction = DB::transaction(function () use ($purchaseOrderTransaction) {
            $purchaseOrder = PurchaseOrder::whereKey($purchaseOrderTransaction->purchase_order_id)->lockForUpdate()->firstOrFail();
            $purchaseOrderTransaction->refresh()->setRelation('purchaseOrder', $purchaseOrder);

            if ($blockedReason = self::blockedReason($purchaseOrderTransaction)) {
                throw ValidationException::withMessages(['undo_cancel' => $blockedReason]);
            }

            $netAmount = round((float) $purchaseOrderTransaction->unit_cost * (float) $purchaseOrderTransaction->quantity_ordered, 2);

            $purchaseOrderTransaction->update([
                'state'          => $purchaseOrder->state->transactionState(),
                'net_amount'     => $netAmount,
                'grp_net_amount' => round($netAmount * (float) ($purchaseOrderTransaction->grp_exchange ?? 1), 2),
                'org_net_amount' => round($netAmount * (float) ($purchaseOrderTransaction->org_exchange ?? 1), 2),
                'data'           => Arr::except($purchaseOrderTransaction->data ?? [], 'cancellation_reason') ?: (object) [],
            ]);

            CalculatePurchaseOrderTotalAmounts::run($purchaseOrder);

            return $purchaseOrderTransaction;
        });

        PurchaseOrderHydrateTransactions::dispatch($purchaseOrderTransaction->purchaseOrder)->delay($this->hydratorsDelay);

        return $purchaseOrderTransaction;
    }

    /**
     * Why a cancelled item can not go back on its order, null when it can.
     */
    public static function blockedReason(PurchaseOrderTransaction $purchaseOrderTransaction): ?string
    {
        $purchaseOrder = $purchaseOrderTransaction->purchaseOrder;

        if ($purchaseOrderTransaction->state !== PurchaseOrderTransactionStateEnum::CANCELLED) {
            return __('This item is not cancelled');
        }

        if (self::isCancelledByMove($purchaseOrderTransaction)) {
            return __('This item was cancelled by a move: undo the move instead');
        }

        if (!in_array($purchaseOrder->state, PurchaseOrderStateEnum::open(), true)) {
            return __('The order is :state: its items can no longer change', [
                'state' => PurchaseOrderStateEnum::labels()[$purchaseOrder->state->value] ?? $purchaseOrder->state->value,
            ]);
        }

        if ($purchaseOrder->stockDeliveries()->where('stock_deliveries.state', '!=', StockDeliveryStateEnum::CANCELLED)->exists()) {
            return __('The order has a stock delivery: order the item again instead');
        }

        return null;
    }

    public static function isCancelledByMove(PurchaseOrderTransaction $purchaseOrderTransaction): bool
    {
        if (Arr::get($purchaseOrderTransaction->data, 'moved_from.undone_at')) {
            return true;
        }

        return collect(Arr::get($purchaseOrderTransaction->data, 'moved_to', []))
            ->contains(fn (array $movedTo) => !Arr::get($movedTo, 'undone_at'));
    }

    public function asController(PurchaseOrder $purchaseOrder, PurchaseOrderTransaction $purchaseOrderTransaction, ActionRequest $request): RedirectResponse
    {
        abort_unless($purchaseOrderTransaction->purchase_order_id === $purchaseOrder->id, 404);
        $this->initialisation($purchaseOrderTransaction->organisation, $request);

        $purchaseOrderTransaction = $this->handle($purchaseOrderTransaction);

        NotifyBuyerOfAgentStageChange::run($purchaseOrder, $request->user(), fn () => __('the agent put :code back on the order', [
            'code' => $purchaseOrderTransaction->supplierProduct?->code ?? $purchaseOrderTransaction->orgStock?->code,
        ]));

        return back();
    }

    public function action(PurchaseOrderTransaction $purchaseOrderTransaction): PurchaseOrderTransaction
    {
        $this->asAction = true;
        $this->initialisation($purchaseOrderTransaction->organisation, []);

        return $this->handle($purchaseOrderTransaction);
    }
}
