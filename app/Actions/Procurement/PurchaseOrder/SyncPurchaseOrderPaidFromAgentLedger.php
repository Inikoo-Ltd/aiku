<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-12 13:30
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\PurchaseOrder;

use App\Enums\SupplyChain\AgentPayment\AgentPaymentTypeEnum;
use App\Events\BroadcastAgentIncomingPurchaseOrdersChanged;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * On an agent order the agent pays the supplier from the client's money and records it in its ledger: its first
 * supplier payment is the deposit and the payment that covers the goods is the balance. A date nobody set, or one this
 * sync set from the ledger before the change, follows the ledger, so deleting the payment takes it back; a date the
 * buyer marked by hand is kept. The first payment confirms a submitted order, as any stage past submitted does.
 */
class SyncPurchaseOrderPaidFromAgentLedger
{
    use AsObject;

    /**
     * @param  array{deposit_paid_at: string|null, balance_paid_at: string|null}  $ledgerDatesBefore
     */
    public function handle(PurchaseOrder $purchaseOrder, array $ledgerDatesBefore): PurchaseOrder
    {
        if (!$purchaseOrder->isAgentOrder()) {
            return $purchaseOrder;
        }

        $ledgerDates = self::ledgerDates($purchaseOrder);

        $changes = [];
        foreach ($ledgerDates as $field => $ledgerDate) {
            $current = $purchaseOrder->{$field}?->toDateString();
            $followsLedger = $current === null || $current === $ledgerDatesBefore[$field];

            if ($followsLedger && $current !== $ledgerDate) {
                $changes[$field] = $ledgerDate;
            }
        }

        if ($changes === []) {
            return $purchaseOrder;
        }

        $depositDate = $changes['deposit_paid_at'] ?? null;
        if (MarkPurchaseOrderStage::confirmsOrder($purchaseOrder, $depositDate) && !$purchaseOrder->isWaitingForLineAnswers()) {
            $purchaseOrder = UpdatePurchaseOrderStateToConfirmed::make()->action($purchaseOrder);
            $purchaseOrder->update(['confirmed_at' => Carbon::parse($depositDate)->min(now())]);
        }

        $purchaseOrder = UpdatePurchaseOrder::make()->action($purchaseOrder, $changes);

        if (($changes['balance_paid_at'] ?? null) !== null) {
            $purchaseOrder = RecordPurchaseOrderHandedOverWhenReady::run($purchaseOrder);
        }

        BroadcastAgentIncomingPurchaseOrdersChanged::dispatchFor($purchaseOrder);

        return $purchaseOrder;
    }

    /**
     * @return array{deposit_paid_at: string|null, balance_paid_at: string|null}
     */
    public static function ledgerDates(PurchaseOrder $purchaseOrder): array
    {
        $payments = DB::table('agent_payments')
            ->where('purchase_order_id', $purchaseOrder->id)
            ->whereIn('type', AgentPaymentTypeEnum::supplierPaymentValues())
            ->whereNull('deleted_at')
            ->whereNotNull('date')
            ->orderBy('date')
            ->orderBy('id')
            ->get(['date', 'amount']);

        $goods       = round((float) $purchaseOrder->cost_items, 2);
        $paid        = 0.0;
        $balanceDate = null;
        foreach ($payments as $payment) {
            $paid += (float) $payment->amount;
            if ($goods > 0 && round($paid, 2) >= $goods) {
                $balanceDate = $payment->date;
                break;
            }
        }

        return [
            'deposit_paid_at' => $payments->first()?->date,
            'balance_paid_at' => $balanceDate,
        ];
    }

    /**
     * @param  array<int, array{deposit_paid_at: string|null, balance_paid_at: string|null}>  $ledgerDatesBeforeByOrder
     */
    public static function runForOrders(array $ledgerDatesBeforeByOrder): void
    {
        if ($ledgerDatesBeforeByOrder === []) {
            return;
        }

        PurchaseOrder::whereIn('id', array_keys($ledgerDatesBeforeByOrder))
            ->get()
            ->each(fn (PurchaseOrder $purchaseOrder) => self::run($purchaseOrder, $ledgerDatesBeforeByOrder[$purchaseOrder->id]));
    }

    /**
     * @param  array<int, int|null>  $purchaseOrderIds
     * @return array<int, array{deposit_paid_at: string|null, balance_paid_at: string|null}>
     */
    public static function ledgerDatesFor(array $purchaseOrderIds): array
    {
        $purchaseOrderIds = array_filter(array_unique($purchaseOrderIds));
        if ($purchaseOrderIds === []) {
            return [];
        }

        return PurchaseOrder::whereIn('id', $purchaseOrderIds)
            ->get()
            ->mapWithKeys(fn (PurchaseOrder $purchaseOrder) => [$purchaseOrder->id => self::ledgerDates($purchaseOrder)])
            ->all();
    }
}
