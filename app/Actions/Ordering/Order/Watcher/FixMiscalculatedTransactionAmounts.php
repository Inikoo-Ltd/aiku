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
            $netAmountExpected   = round(($qtyOrdered * $historicPrice) * ($transaction->current_discount_factor ?? 1), 2);

            $diffGross = abs($grossAmountExpected - $transaction->gross_amount);
            $diffNet   = abs($netAmountExpected - $transaction->net_amount);

            if (($diffGross > 0.016) || ($diffNet > 0.016)) {
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
                    'diff_net'                => $diffNet
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
