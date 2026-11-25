<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Nov 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrderTransaction;

use App\Actions\GoodsIn\StockDelivery\StoreStockDeliveryFromPurchaseOrder;
use App\Actions\OrgAction;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * INI-525. Takes back what was kept at the agent, so those units are awaited again. Refused once the line settled.
 */
class UndoKeepPurchaseOrderTransactionAtAgent extends OrgAction
{
    private PurchaseOrderTransaction $purchaseOrderTransaction;

    public static function isUndoable(PurchaseOrderTransaction $purchaseOrderTransaction): bool
    {
        return self::refusal($purchaseOrderTransaction) === null;
    }

    /**
     * Why it can not be taken back: nothing kept, the line settled, or some kept units went in a container already
     * (those containers count them as kept).
     */
    public static function refusal(PurchaseOrderTransaction $purchaseOrderTransaction): ?string
    {
        $kept = (float) $purchaseOrderTransaction->quantity_kept_at_agent;

        return match (true) {
            $kept <= 0 => __('Nothing of this item is kept at the agent'),
            $purchaseOrderTransaction->state !== PurchaseOrderTransactionStateEnum::CONFIRMED || !$purchaseOrderTransaction->purchaseOrder?->state->isAgreed() => __('This order is settled: what was kept at the agent can no longer be taken back'),
            (float) $purchaseOrderTransaction->quantity_ordered - KeepPurchaseOrderTransactionAtAgent::onContainers($purchaseOrderTransaction) < $kept - StoreStockDeliveryFromPurchaseOrder::QUANTITY_TOLERANCE => __('Some of the units kept at the agent are in a container already: take them out of it first'),
            default    => null,
        };
    }

    public function handle(PurchaseOrderTransaction $purchaseOrderTransaction, ?int $userId): PurchaseOrderTransaction
    {
        DB::transaction(function () use ($purchaseOrderTransaction, $userId) {
            PurchaseOrder::whereKey($purchaseOrderTransaction->purchase_order_id)->lockForUpdate()->first();
            $purchaseOrderTransaction = PurchaseOrderTransaction::whereKey($purchaseOrderTransaction->id)->lockForUpdate()->firstOrFail();

            if ($refusal = self::refusal($purchaseOrderTransaction)) {
                throw ValidationException::withMessages(['kept_at_agent' => $refusal]);
            }

            $data                  = $purchaseOrderTransaction->data ?? [];
            $data['kept_at_agent'] = array_map(
                fn (array $kept) => isset($kept['undone_at']) ? $kept : [...$kept, 'undone_at' => now()->toIso8601String(), 'undone_by' => $userId],
                $data['kept_at_agent'] ?? []
            );

            $purchaseOrderTransaction->update([
                'quantity_kept_at_agent' => 0,
                'data'                   => $data,
            ]);
        });

        KeepPurchaseOrderTransactionAtAgent::resyncPurchaseOrder($purchaseOrderTransaction->purchaseOrder);

        return $purchaseOrderTransaction->refresh();
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return KeepPurchaseOrderTransactionAtAgent::userCanKeep($this->purchaseOrderTransaction->purchaseOrder, $request->user());
    }

    public function action(PurchaseOrderTransaction $purchaseOrderTransaction, ?int $userId = null): PurchaseOrderTransaction
    {
        $this->asAction                 = true;
        $this->purchaseOrderTransaction = $purchaseOrderTransaction;
        $this->initialisation($purchaseOrderTransaction->organisation, []);

        return $this->handle($purchaseOrderTransaction, $userId);
    }

    public function asController(PurchaseOrder $purchaseOrder, PurchaseOrderTransaction $purchaseOrderTransaction, ActionRequest $request): RedirectResponse
    {
        abort_unless($purchaseOrderTransaction->purchase_order_id === $purchaseOrder->id, 404);
        $this->purchaseOrderTransaction = $purchaseOrderTransaction;
        $this->initialisation($purchaseOrderTransaction->organisation, $request);

        $this->handle($purchaseOrderTransaction, $request->user()->id);

        return back();
    }
}
