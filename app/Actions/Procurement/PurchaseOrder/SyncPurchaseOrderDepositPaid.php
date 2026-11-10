<?php

namespace App\Actions\Procurement\PurchaseOrder;

use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderDepositPayment;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Deposits are payments to the supplier towards the price of the products only: the order's extra costs, shipping,
 * duties and tax, and the supplier's estimated expenses, are not paid to the supplier through the order. The deposit
 * counts as paid, dated by its last payment, once nothing is left to pay.
 */
class SyncPurchaseOrderDepositPaid
{
    use AsObject;

    /**
     * The first payment is the deposit and paying the order in full is the balance, as the purchase order stages
     * read them. An order set to need no deposit keeps that until a payment is made.
     */
    public function handle(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        $payments     = $purchaseOrder->depositPayments()->orderBy('date')->orderBy('id')->get();
        /** @var PurchaseOrderDepositPayment|null $firstPayment */
        $firstPayment = $payments->first();
        /** @var PurchaseOrderDepositPayment|null $lastPayment */
        $lastPayment  = $payments->last();
        $paid         = self::paidAmount($purchaseOrder);
        $isPaid       = $lastPayment && self::leftToPay($purchaseOrder) <= 0;
        $noDeposit    = $purchaseOrder->deposit_amount !== null && (float) $purchaseOrder->deposit_amount == 0;

        $purchaseOrder->update([
            'deposit_amount'     => $paid > 0 ? $paid : ($noDeposit ? 0 : null),
            'deposit_paid_at'    => $firstPayment?->date->toDateString(),
            'deposit_paid_by_id' => $firstPayment?->created_by,
            'balance_paid_at'    => $isPaid ? $lastPayment->date->toDateString() : null,
        ]);

        return $isPaid ? RecordPurchaseOrderHandedOverWhenReady::run($purchaseOrder) : $purchaseOrder;
    }

    public static function totalToPay(PurchaseOrder $purchaseOrder): float
    {
        return round((float) $purchaseOrder->cost_items, 2);
    }

    public static function paidAmount(PurchaseOrder $purchaseOrder): float
    {
        return round((float) $purchaseOrder->depositPayments()->sum('amount'), 2);
    }

    public static function leftToPay(PurchaseOrder $purchaseOrder): float
    {
        return max(0, round(self::totalToPay($purchaseOrder) - self::paidAmount($purchaseOrder), 2));
    }
}
