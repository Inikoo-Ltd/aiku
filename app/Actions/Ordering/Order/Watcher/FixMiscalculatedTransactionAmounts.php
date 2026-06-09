<?php

/*
 * author Louis Perez
 * created on 08-06-2026-10h-29m
 * GitHub: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Ordering\Order\Watcher;

use App\Actions\Ordering\Order\CalculateOrderTotalAmounts;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Lorisleiva\Actions\Concerns\AsAction;
use OwenIt\Auditing\Events\AuditCustom;
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
            $qtyOrdered    = $transaction->quantity_ordered;
            $historicPrice = $transaction->historicAsset->price;
            $grossAmountExpected   = round($qtyOrdered * $historicPrice,2);
            $netAmountExpected     = round(($qtyOrdered * $historicPrice) * ($transaction->current_discount_factor ?? 1),2);


            if ($grossAmountExpected != $transaction->gross_amount || $netAmountExpected != $transaction->net_amount) {
                data_set($miscalculatedTransactionsDebugData, $transaction->id, [
                    'transaction_id'        => $transaction->id,
                    'item_code'             => $transaction->historicAsset->code,
                    'gross_amount'          => $transaction->gross_amount,
                    'net_amount'            => $transaction->net_amount,
                    'gross_amount_expected' => $grossAmountExpected,
                    'net_amount_expected'   => $netAmountExpected,
                ]);

                if ($repairAmount) {
                    $this->repairTransactionAmounts($transaction);
                    $orderRepaired = true;
                }
            }
        }

        if (!empty($miscalculatedTransactionsDebugData)) {
            Sentry::withScope(function (Scope $scope) use ($miscalculatedTransactionsDebugData, $order) {
                $scope->setContext('miscalculated_items', $miscalculatedTransactionsDebugData);
                Sentry::captureMessage("Order $order->id: Pricing mismatch detected");
            });
        }

        if ($orderRepaired) {
            CalculateOrderTotalAmounts::run($order);
            $order->refresh();
        }

        return $order;
    }

    /**
     * @throws \Throwable
     */
    public function repairTransactionAmounts(Transaction $transaction): Order
    {
        $order = $transaction->order;

        DB::transaction(function () use ($order, $transaction) {
            $grossAmount = $transaction->quantity_ordered * $transaction->historicAsset->price;
            $netAmount   = $grossAmount * ($transaction->current_discount_factor ?? 1);

            $order->auditEvent    = 'miscalculated_total_amount_repair';
            $order->isCustomEvent = true;

            $order->auditCustomOld = [
                'item_code'    => '',
                'gross_amount' => $transaction->gross_amount,
                'net_amount'   => $transaction->net_amount,
            ];

            $order->auditCustomNew = [
                'item_code'    => $transaction->historicAsset->code,
                'gross_amount' => $grossAmount,
                'net_amount'   => $netAmount,
            ];

            $transaction->update([
                'gross_amount' => $grossAmount,
                'net_amount'   => $netAmount,
            ]);

            Event::dispatch(new AuditCustom($order));
        });

        return $order;
    }


}
