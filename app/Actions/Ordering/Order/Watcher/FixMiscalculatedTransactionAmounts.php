<?php

/*
 * author Louis Perez
 * created on 08-06-2026-10h-29m
 * GitHub: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Ordering\Order\Watcher;

use App\Actions\Ordering\Order\CalculateOrderDiscounts;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry;
use Sentry\State\Scope;

class FixMiscalculatedTransactionAmounts
{
    use AsAction;

    /**
     * @throws \Throwable
     */
    public function handle(Order $order, bool $repairAmount = false): Order
    {
        $transactions                       = $order->itemTransactions()->with('historicAsset')->get();
        $miscalculatedTransactionsDebugData = [];

        $orderRepaired = false;
        /** @var Transaction $transaction */
        foreach ($transactions as $transaction) {
            $qtyOrdered          = $transaction->quantity_ordered;
            $historicPrice       = $transaction->historicAsset->price;
            $grossAmountExpected = round($qtyOrdered * $historicPrice, 2);
            $netAmountExpected   = round($qtyOrdered * $historicPrice, 2)
                - discountAmountOffGross(round($qtyOrdered * $historicPrice, 2), $transaction->current_discount_factor);

            $diffGross = abs($grossAmountExpected - $transaction->gross_amount);
            $diffNet   = abs($netAmountExpected - $transaction->net_amount);

            /**
             * A line already sold has one right answer, the price it was sold at, so it is held
             * to the cent rather than to the tolerance below. The tolerance exists for baskets,
             * where a mismatch means a calculation went wrong; here it was wide enough to hide
             * every penny drift this watcher was meant to catch.
             */
            $soldPriceMoved = $transaction->submitted_net_amount !== null
                && (float)$transaction->submitted_discount_factor === (float)$transaction->current_discount_factor
                && (float)$transaction->quantity_ordered === (float)$transaction->submitted_quantity_ordered
                && (float)$transaction->quantity_picked === (float)$transaction->quantity_ordered
                && round((float)$transaction->net_amount - (float)$transaction->submitted_net_amount, 2) != 0;

            if ($soldPriceMoved || ($diffGross > 0.016) || ($diffNet > 0.016)) {
                data_set($miscalculatedTransactionsDebugData, $transaction->id, [
                    'transaction_id'          => $transaction->id,
                    'item_code'               => $transaction->historicAsset->code,
                    'historic_asset_id'       => $transaction->historicAsset->id,
                    'gross_amount'            => $transaction->gross_amount,
                    'net_amount'              => $transaction->net_amount,
                    'gross_amount_expected'   => $grossAmountExpected,
                    'net_amount_expected'     => $netAmountExpected,
                    'quantity_ordered'        => $qtyOrdered,
                    'historic_price'          => $historicPrice,
                    'offer_data'              => $transaction->offers_data,
                    'current_discount_factor' => $transaction->current_discount_factor,
                    'diff_gross'              => $diffGross,
                    'diff_net'                => $diffNet,
                    'submitted_net_amount'    => $transaction->submitted_net_amount,
                    'sold_price_moved'        => $soldPriceMoved,
                ]);

                if ($repairAmount) {
                    $orderRepaired = true;
                }
            }
        }

        if (!empty($miscalculatedTransactionsDebugData)) {
            Sentry::withScope(function (Scope $scope) use ($miscalculatedTransactionsDebugData, $order) {
                $scope->setContext('miscalculated_items', $miscalculatedTransactionsDebugData);
                Sentry::captureMessage("Order $order->id: Pricing mismatch detected V5");
            });
        }

        if ($orderRepaired) {
            CalculateOrderDiscounts::run($order);
            $order->refresh();
        }

        return $order;
    }


}
