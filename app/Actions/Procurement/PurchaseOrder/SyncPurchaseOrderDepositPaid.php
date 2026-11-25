<?php

namespace App\Actions\Procurement\PurchaseOrder;

use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderDepositPayment;
use App\Models\SupplyChain\Supplier;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Deposits are payments to the supplier towards the price of the products only: the order's extra costs, shipping,
 * duties and tax, and the supplier's estimated expenses, are not paid to the supplier through the order. The deposit
 * counts as paid, dated by its last payment, once nothing is left to pay. A payment means the supplier took the order,
 * so a submitted order is confirmed, as when any stage past submitted is marked.
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

        if (MarkPurchaseOrderStage::confirmsOrder($purchaseOrder, $firstPayment?->date) && !$purchaseOrder->isWaitingForLineAnswers()) {
            $purchaseOrder = UpdatePurchaseOrderStateToConfirmed::make()->action($purchaseOrder);
            $purchaseOrder->update(['confirmed_at' => $firstPayment->date->min(now())]);
        }

        return RecordPurchaseOrderHandedOverWhenReady::run($purchaseOrder);
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

    /**
     * The share of the order the supplier usually asks as a deposit (Sheikh: 40%), offered as the first payment.
     * Empty or 0 means none is set.
     */
    public static function defaultDepositPercentage(PurchaseOrder $purchaseOrder): ?float
    {
        if (!$purchaseOrder->supplier_id) {
            return null;
        }

        $percentage = Arr::get(Supplier::withTrashed()->whereKey($purchaseOrder->supplier_id)->value('settings') ?? [], 'default_deposit_percentage');

        return $percentage !== null && (float) $percentage > 0 ? min(100, (float) $percentage) : null;
    }

    public static function defaultDeposit(PurchaseOrder $purchaseOrder): ?float
    {
        $percentage = self::defaultDepositPercentage($purchaseOrder);

        return $percentage === null ? null : round(self::totalToPay($purchaseOrder) * $percentage / 100, 2);
    }
}
