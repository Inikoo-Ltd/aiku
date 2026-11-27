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
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Actions\SupplyChain\SupplierShareLink\NotifySupplierOfDepositAnswer;
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
     * @param  list<string>  $fields  the dates to follow; a change of the goods alone only moves the balance date
     */
    public function handle(PurchaseOrder $purchaseOrder, array $ledgerDatesBefore, array $fields = ['deposit_paid_at', 'balance_paid_at']): PurchaseOrder
    {
        if (!$purchaseOrder->isAgentOrder()) {
            return $purchaseOrder;
        }

        $ledgerDates = Arr::only(self::ledgerDates($purchaseOrder), $fields);

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

        if (array_key_exists('balance_paid_at', $changes)) {
            $purchaseOrder = RecordPurchaseOrderHandedOverWhenReady::run($purchaseOrder);
        }

        if (array_key_exists('deposit_paid_at', $changes)) {
            NotifySupplierOfDepositAnswer::make()->paid($purchaseOrder);
        }

        BroadcastAgentIncomingPurchaseOrdersChanged::dispatchFor($purchaseOrder);

        return $purchaseOrder;
    }

    /**
     * The balance is paid once the payments in the order's currency add up to its goods: a payment in another currency
     * is a different amount of money and never counts towards it. $goods is the order's goods, or what they were
     * before a change.
     *
     * @return array{deposit_paid_at: string|null, balance_paid_at: string|null}
     */
    public static function ledgerDates(PurchaseOrder $purchaseOrder, ?float $goods = null): array
    {
        $payments = DB::table('agent_payments')
            ->where('purchase_order_id', $purchaseOrder->id)
            ->whereIn('type', AgentPaymentTypeEnum::supplierPaymentValues())
            ->whereNull('deleted_at')
            ->whereNotNull('date')
            ->orderBy('date')
            ->orderBy('id')
            ->get(['date', 'amount', 'currency_id']);

        $goods       = round($goods ?? (float) $purchaseOrder->cost_items, 2);
        $paid        = 0.0;
        $balanceDate = null;
        foreach ($payments->where('currency_id', $purchaseOrder->currency_id) as $payment) {
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
