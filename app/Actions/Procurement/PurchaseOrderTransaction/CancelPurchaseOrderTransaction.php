<?php

namespace App\Actions\Procurement\PurchaseOrderTransaction;

use App\Actions\Traits\Authorisations\WithPurchaseOrderAgentEditAuthorisation;
use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\CalculatePurchaseOrderTotalAmounts;
use App\Actions\Procurement\PurchaseOrder\DeleteEmptyPurchaseOrders;
use App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrderStateToCancelled;
use App\Actions\Procurement\PurchaseOrder\NotifyBuyerOfAgentStageChange;
use App\Actions\GoodsIn\StockDelivery\StoreStockDeliveryFromPurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\Hydrators\PurchaseOrderHydrateTransactions;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Http\Resources\Procurement\PurchaseOrderTransactionResource;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class CancelPurchaseOrderTransaction extends OrgAction
{
    use WithPurchaseOrderAgentEditAuthorisation;
    use WithActionUpdate;

    public function handle(PurchaseOrderTransaction $purchaseOrderTransaction, ?string $reason = null, bool $cancelEmptyOrder = true): PurchaseOrderTransaction
    {
        $purchaseOrder = $purchaseOrderTransaction->purchaseOrder;

        $purchaseOrderTransaction = DB::transaction(function () use ($purchaseOrder, $purchaseOrderTransaction, $reason, $cancelEmptyOrder) {
            $purchaseOrder = PurchaseOrder::whereKey($purchaseOrder->id)->lockForUpdate()->first();
            $purchaseOrderTransaction->refresh();

            if ($purchaseOrder->areLinesLockedByProduction()) {
                abort(422, __('The supplier is already making this order: its items can no longer be cancelled'));
            }

            if ($purchaseOrderTransaction->state === PurchaseOrderTransactionStateEnum::CONFIRMED) {
                $isOnDelivery = $purchaseOrder->purchaseOrderTransactions()
                    ->whereKey($purchaseOrderTransaction->id)
                    ->whereExists(fn (Builder $items) => StoreStockDeliveryFromPurchaseOrder::liveDeliveryItemsOfTransaction($items))
                    ->exists();

                if ($isOnDelivery) {
                    abort(422, __('This item is on a stock delivery, deal with it there'));
                }
            } elseif ($purchaseOrderTransaction->state !== PurchaseOrderTransactionStateEnum::SUBMITTED) {
                abort(422, __('Only submitted or confirmed items can be cancelled'));
            }

            $purchaseOrderTransaction = $this->update($purchaseOrderTransaction, [
                'state'          => PurchaseOrderTransactionStateEnum::CANCELLED,
                'net_amount'     => 0,
                'grp_net_amount' => 0,
                'org_net_amount' => 0,
                'data'           => array_merge($purchaseOrderTransaction->data ?? [], ['cancellation_reason' => $reason ?: null]),
            ]);

            CalculatePurchaseOrderTotalAmounts::run($purchaseOrder);

            if ($purchaseOrder->state->isAgreed()
                && $purchaseOrder->stockDeliveries()->where('stock_deliveries.state', '!=', StockDeliveryStateEnum::CANCELLED)->exists()
                && !$purchaseOrder->purchaseOrderTransactions()->where('state', PurchaseOrderTransactionStateEnum::CONFIRMED)->exists()) {
                $purchaseOrder->update([
                    'state'      => PurchaseOrderStateEnum::SETTLED,
                    'settled_at' => now(),
                ]);
            }

            if ($cancelEmptyOrder && $this->isLeftEmpty($purchaseOrder)) {
                UpdatePurchaseOrderStateToCancelled::make()->handle($purchaseOrder, source: UpdatePurchaseOrderStateToCancelled::SOURCE_EMPTIED);
            }

            return $purchaseOrderTransaction;
        });

        PurchaseOrderHydrateTransactions::dispatch($purchaseOrderTransaction->purchaseOrder)->delay($this->hydratorsDelay);

        return $purchaseOrderTransaction;
    }

    /**
     * An order whose last live line was cancelled has nothing left to order, so it is cancelled too, unless money or goods
     * hang off it (a deposit, a payment, goods at the agent, a live delivery) and someone has to settle them first.
     */
    public function isLeftEmpty(PurchaseOrder $purchaseOrder): bool
    {
        return in_array($purchaseOrder->state, PurchaseOrderStateEnum::open(), true)
            && !$purchaseOrder->purchaseOrderTransactions()->where('state', '!=', PurchaseOrderTransactionStateEnum::CANCELLED)->exists()
            && DeleteEmptyPurchaseOrders::deletableIds($purchaseOrder->group, [$purchaseOrder->id]) === [$purchaseOrder->id];
    }

    public function asController(PurchaseOrder $purchaseOrder, PurchaseOrderTransaction $purchaseOrderTransaction, ActionRequest $request): PurchaseOrderTransaction
    {
        abort_unless($purchaseOrderTransaction->purchase_order_id === $purchaseOrder->id, 404);
        $this->initialisation($purchaseOrderTransaction->organisation, $request);

        $reason                   = trim((string) ($this->validatedData['reason'] ?? ''));
        $purchaseOrderTransaction = $this->handle($purchaseOrderTransaction, $reason);

        NotifyBuyerOfAgentStageChange::run($purchaseOrder, $request->user(), fn () => __('the agent cancelled :code: :reason', [
            'code'   => $purchaseOrderTransaction->supplierProduct?->code ?? $purchaseOrderTransaction->orgStock?->code,
            'reason' => $reason,
        ]));

        return $purchaseOrderTransaction;
    }

    /**
     * The agent's staff must say why: the buyer only learns of the cancellation from the notification.
     */
    public function rules(): array
    {
        return [
            'reason' => [$this->asAction || request()->user()?->authTo("procurement.{$this->organisation->id}.edit") ? 'nullable' : 'required', 'string', 'max:500'],
        ];
    }

    /**
     * Moving lines to another supplier and undoing a move settle an emptied order themselves, so they pass $cancelEmptyOrder false.
     */
    public function action(PurchaseOrderTransaction $purchaseOrderTransaction, int $hydratorsDelay = 0, bool $cancelEmptyOrder = true): PurchaseOrderTransaction
    {
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;

        $this->initialisation($purchaseOrderTransaction->organisation, []);

        return $this->handle($purchaseOrderTransaction, cancelEmptyOrder: $cancelEmptyOrder);
    }

    public function jsonResponse(PurchaseOrderTransaction $purchaseOrderTransaction): PurchaseOrderTransactionResource
    {
        return new PurchaseOrderTransactionResource($purchaseOrderTransaction);
    }
}
