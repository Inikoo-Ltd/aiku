<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-11 14:32
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\PurchaseOrderTransaction;

use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\CalculatePurchaseOrderTotalAmounts;
use App\Actions\Procurement\PurchaseOrder\DeleteEmptyPurchaseOrders;
use App\Actions\Procurement\PurchaseOrder\DeletePurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\Hydrators\PurchaseOrderHydrateTransactions;
use App\Actions\Procurement\PurchaseOrder\MarkPurchaseOrderStage;
use App\Actions\Procurement\PurchaseOrder\NotifyBuyerOfAgentStageChange;
use App\Actions\Procurement\PurchaseOrder\Traits\HasPurchaseOrderHydrators;
use App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrderStateToCancelled;
use App\Actions\Traits\Authorisations\WithPurchaseOrderAgentEditAuthorisation;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Throwable;

/**
 * Takes a moved line back to the line it came from: its units return to that line (reopening the order when the move
 * had emptied and cancelled it), the moved line is cancelled, and the order it landed on is deleted or cancelled when
 * left empty.
 * Both lines keep the move with who undid it and when.
 */
class UndoMovePurchaseOrderTransaction extends OrgAction
{
    use WithPurchaseOrderAgentEditAuthorisation;
    use HasPurchaseOrderHydrators;

    /**
     * @return array{source: PurchaseOrderTransaction, moved: PurchaseOrderTransaction, quantity: float, target_closed: 'deleted'|'cancelled'|null}
     */
    public function handle(PurchaseOrderTransaction $movedTransaction, ?User $user = null): array
    {
        return DB::transaction(function () use ($movedTransaction, $user) {
            $targetPurchaseOrder = PurchaseOrder::whereKey($movedTransaction->purchase_order_id)->lockForUpdate()->firstOrFail();
            $movedTransaction->refresh();
            $movedFrom           = Arr::get($movedTransaction->data, 'moved_from');
            $sourcePurchaseOrder = $movedFrom ? PurchaseOrder::whereKey($movedFrom['purchase_order_id'])->lockForUpdate()->first() : null;

            if ($blockedReason = self::blockedReason($movedTransaction)) {
                throw ValidationException::withMessages(['undo_move' => $blockedReason]);
            }

            $sourceTransaction = PurchaseOrderTransaction::where('purchase_order_id', $sourcePurchaseOrder->id)->findOrFail($movedFrom['purchase_order_transaction_id']);
            $quantity          = round((float) $movedTransaction->quantity_ordered, 3);

            if ($sourcePurchaseOrder->state === PurchaseOrderStateEnum::CANCELLED) {
                $sourcePurchaseOrder = $this->reopen($sourcePurchaseOrder);
            }

            $sourceTransaction = $sourceTransaction->state === PurchaseOrderTransactionStateEnum::CANCELLED
                ? $this->restore($sourceTransaction, $sourcePurchaseOrder, $quantity)
                : UpdatePurchaseOrderTransaction::make()->action($sourceTransaction, ['quantity_ordered' => round((float) $sourceTransaction->quantity_ordered + $quantity, 3)]);

            $movedTransaction = CancelPurchaseOrderTransaction::make()->action($movedTransaction, cancelEmptyOrder: false);

            $undone = ['undone_by' => $user?->id, 'undone_at' => now()->toIso8601String()];
            $movedTransaction->update(['data' => array_merge($movedTransaction->data ?? [], [
                'moved_from' => array_merge($movedFrom, $undone),
            ])]);
            $sourceTransaction->update(['data' => array_merge($sourceTransaction->data ?? [], [
                'moved_to' => array_map(
                    fn (array $movedTo) => (int) Arr::get($movedTo, 'purchase_order_transaction_id') === $movedTransaction->id && !Arr::get($movedTo, 'undone_at') ? array_merge($movedTo, $undone) : $movedTo,
                    Arr::get($sourceTransaction->data, 'moved_to', [])
                ),
            ])]);

            $targetClosed = $this->closeWhenEmpty($targetPurchaseOrder->refresh());

            return [
                'source'        => $sourceTransaction->refresh(),
                'moved'         => $targetClosed === 'deleted' ? $movedTransaction : $movedTransaction->refresh(),
                'quantity'      => $quantity,
                'target_closed' => $targetClosed,
            ];
        });
    }

    /**
     * Why the units of a moved line can not go back to the line they came from, null when they can.
     */
    public static function blockedReason(PurchaseOrderTransaction $movedTransaction): ?string
    {
        $movedFrom = Arr::get($movedTransaction->data, 'moved_from');

        if (!$movedFrom) {
            return __('This item was not moved from another supplier');
        }

        if (Arr::get($movedFrom, 'undone_at')) {
            return __('These units were already given back to :reference', ['reference' => $movedFrom['reference']]);
        }

        if (!MovePurchaseOrderTransactionToSupplier::isMovable($movedTransaction)) {
            return __('Only items not yet in production nor on a stock delivery can go back to their supplier');
        }

        $sourcePurchaseOrder = PurchaseOrder::find($movedFrom['purchase_order_id']);
        $sourceTransaction   = $sourcePurchaseOrder
            ? PurchaseOrderTransaction::where('purchase_order_id', $sourcePurchaseOrder->id)->find($movedFrom['purchase_order_transaction_id'])
            : null;

        if (!$sourceTransaction) {
            return __(':reference no longer exists', ['reference' => $movedFrom['reference']]);
        }

        if ($sourcePurchaseOrder->state === PurchaseOrderStateEnum::CANCELLED) {
            if (Arr::get($sourcePurchaseOrder->data, 'cancellation.source') !== UpdatePurchaseOrderStateToCancelled::SOURCE_MOVED
                || !in_array(Arr::get($sourcePurchaseOrder->data, 'cancellation.state'), [PurchaseOrderStateEnum::SUBMITTED->value, PurchaseOrderStateEnum::CONFIRMED->value], true)) {
                return __(':reference was cancelled: order the item again instead', ['reference' => $sourcePurchaseOrder->reference]);
            }

            return $sourcePurchaseOrder->stockDeliveries()->where('stock_deliveries.state', '!=', StockDeliveryStateEnum::CANCELLED)->exists()
                ? __(':reference has a stock delivery', ['reference' => $sourcePurchaseOrder->reference])
                : null;
        }

        if (!in_array($sourcePurchaseOrder->state, [PurchaseOrderStateEnum::SUBMITTED, PurchaseOrderStateEnum::CONFIRMED], true)) {
            return __(':reference is :state: its items can no longer change', ['reference' => $sourcePurchaseOrder->reference, 'state' => PurchaseOrderStateEnum::labels()[$sourcePurchaseOrder->state->value] ?? $sourcePurchaseOrder->state->value]);
        }

        if ($sourcePurchaseOrder->areLinesLockedByProduction()) {
            return __('The supplier is already making :reference', ['reference' => $sourcePurchaseOrder->reference]);
        }

        if ($sourceTransaction->state !== PurchaseOrderTransactionStateEnum::CANCELLED && !MovePurchaseOrderTransactionToSupplier::isMovable($sourceTransaction)) {
            return __('The item on :reference is on a stock delivery', ['reference' => $sourcePurchaseOrder->reference]);
        }

        return null;
    }

    /**
     * The order the lines landed on, left with nothing to buy: deleted when it only ever held moved lines and nothing
     * hangs off it (deposit, goods, delivery), as if the move never happened; cancelled when it had lines of its own or
     * anything else, so its record stays.
     *
     * @return 'deleted'|'cancelled'|null
     */
    private function closeWhenEmpty(PurchaseOrder $purchaseOrder): ?string
    {
        $liveStates = [PurchaseOrderTransactionStateEnum::SUBMITTED, PurchaseOrderTransactionStateEnum::CONFIRMED];

        if ($purchaseOrder->purchaseOrderTransactions()->whereIn('state', $liveStates)->exists()) {
            return null;
        }

        $onlyMovedLines = $purchaseOrder->purchaseOrderTransactions()->get(['id', 'data'])
            ->every(fn (PurchaseOrderTransaction $transaction) => Arr::get($transaction->data, 'moved_from') !== null);

        if ($onlyMovedLines && DeleteEmptyPurchaseOrders::deletableIds($purchaseOrder->group, [$purchaseOrder->id])) {
            DeletePurchaseOrder::make()->handle($purchaseOrder);

            return 'deleted';
        }

        if ($purchaseOrder->depositPayments()->exists()
            || $purchaseOrder->stockDeliveries()->where('stock_deliveries.state', '!=', StockDeliveryStateEnum::CANCELLED)->exists()) {
            return null;
        }

        UpdatePurchaseOrderStateToCancelled::make()->handle($purchaseOrder, source: UpdatePurchaseOrderStateToCancelled::SOURCE_MOVED);

        return 'cancelled';
    }

    /**
     * The order the move emptied and cancelled goes back to the state and costs it had.
     */
    private function reopen(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        $cancellation = Arr::get($purchaseOrder->data, 'cancellation', []);

        $purchaseOrder->update(array_merge(Arr::only(Arr::get($cancellation, 'costs', []), ['cost_extra', 'cost_shipping', 'cost_duties', 'cost_tax']), [
            'state'        => $cancellation['state'],
            'cancelled_at' => null,
            'data'         => Arr::except($purchaseOrder->data ?? [], 'cancellation'),
        ]));

        MarkPurchaseOrderStage::auditOverride($purchaseOrder, 'state', PurchaseOrderStateEnum::CANCELLED->value, $cancellation['state'], __('Move undone'));

        return $purchaseOrder->refresh();
    }

    private function restore(PurchaseOrderTransaction $transaction, PurchaseOrder $purchaseOrder, float $quantity): PurchaseOrderTransaction
    {
        $netAmount = round($quantity * (float) $transaction->unit_cost, 2);

        $transaction->update([
            'state'            => PurchaseOrderTransactionStateEnum::from($purchaseOrder->state->value),
            'quantity_ordered' => $quantity,
            'net_amount'       => $netAmount,
            'grp_net_amount'   => round($netAmount * (float) ($transaction->grp_exchange ?? 1), 2),
            'org_net_amount'   => round($netAmount * (float) ($transaction->org_exchange ?? 1), 2),
            'data'             => Arr::except($transaction->data ?? [], 'cancellation_reason'),
        ]);

        CalculatePurchaseOrderTotalAmounts::run($purchaseOrder);
        PurchaseOrderHydrateTransactions::dispatch($purchaseOrder)->afterCommit();
        $this->purchaseOrderHydrate($purchaseOrder);

        return $transaction;
    }

    public function asController(PurchaseOrder $purchaseOrder, PurchaseOrderTransaction $purchaseOrderTransaction, ActionRequest $request): RedirectResponse
    {
        abort_unless($purchaseOrderTransaction->purchase_order_id === $purchaseOrder->id, 404);
        $this->initialisation($purchaseOrderTransaction->organisation, $request);

        $result = $this->handle($purchaseOrderTransaction, $request->user());
        $source = $result['source'];

        NotifyBuyerOfAgentStageChange::run($source->purchaseOrder, $request->user(), fn () => implode(' ', array_filter([
            __('the agent undid the move of :quantity units of :code to :supplier (:reference): they are back on this order.', [
                'quantity'  => trimDecimalZeros($result['quantity']),
                'code'      => $source->supplierProduct?->code ?? $source->orgStock?->code,
                'supplier'  => $purchaseOrder->parent_name,
                'reference' => $purchaseOrder->reference,
            ]),
            match ($result['target_closed']) {
                'deleted'   => __(':reference had nothing left and was deleted.', ['reference' => $purchaseOrder->reference]),
                'cancelled' => __(':reference had nothing left and was cancelled.', ['reference' => $purchaseOrder->reference]),
                default     => null,
            },
        ])));

        if ($result['target_closed'] === 'deleted') {
            return redirect()->to($this->samePageOf($source->purchaseOrder) ?? url()->previous());
        }

        return back();
    }

    /**
     * The page the user was on (organisation or agent view) for another order, as the one they were on was deleted.
     */
    private function samePageOf(PurchaseOrder $purchaseOrder): ?string
    {
        $previous = Request::create(url()->previous());

        try {
            $route = app('router')->getRoutes()->match($previous);
        } catch (Throwable) {
            return null;
        }

        $route->bind($previous);

        if (!$route->getName() || !array_key_exists('purchaseOrder', $route->parameters())) {
            return null;
        }

        return route($route->getName(), array_merge($route->parameters(), ['purchaseOrder' => $purchaseOrder->slug]));
    }
}
