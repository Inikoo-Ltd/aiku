<?php

/*
 * author Louis Perez
 * created on 08-06-2026-10h-29m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Ordering\Order\Watcher;

use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Lorisleiva\Actions\Concerns\AsAction;
use OwenIt\Auditing\Events\AuditCustom;
use Sentry;
use Sentry\State\Scope;

class WatchMiscalculatedTransactionGrossAmt implements ShouldBeUniqueUntilProcessing
{
    use AsAction;

    private Order $order;

    public string $jobQueue = 'urgent';
    public int $jobTries = 3;

    public function uniqueId(Order $order): string
    {
        return $order->reference;
    }

    public function handle(Order $order, bool $forceRepair = false): void
    {
        $this->order        = $order;
        // Return if not submitted
        if (!$this->order->submitted_at) return;

        $transactions              = $order->itemTransactions()->with('historicAsset')->get();
        $miscalculatedTransactions = [];

        foreach ($transactions as $transaction) {
            $qtyOrdered     = $transaction->submitted_quantity_ordered;
            $historicPrice  = $transaction->historicAsset->price;
            $grossAmt       = trimDecimalZeros($qtyOrdered * $historicPrice);

            if ($grossAmt != $transaction->submitted_gross_amount) {
                data_set($miscalculatedTransactions, $transaction->id, [
                    'transaction_id'        => $transaction->id,
                    'item_code'             => $transaction->historicAsset->code,
                    'gross_amount'          => $transaction->submitted_gross_amount,
                    'net_amount'            => $transaction->submitted_net_amount,
                    'gross_amount_expected' => $grossAmt,
                    'net_amount_expected'   => $grossAmt * ($transaction->submitted_discount_factor ?? 1),
                ]);

                if ($forceRepair) {
                    $this->repairTransaction($transaction);
                }
            }
        }

        if (!empty($miscalculatedTransactions)) {
            Sentry::withScope(function (Scope $scope) use ($miscalculatedTransactions) {
                $scope->setContext('miscalculated_items', $miscalculatedTransactions);
                Sentry::captureMessage('Order Pricing Mismatch Detected');
            });
        }
    }

    public function repairTransaction(Transaction $transaction): void
    {
        $order = $this->order ?? $transaction->order;

        DB::transaction(function () use ($order, $transaction) {
            $grossAmt   = $transaction->submitted_quantity_ordered * $transaction->historicAsset->price;
            $netAmt     = $grossAmt * ($transaction->submitted_discount_factor?? 1);

            $order->auditEvent = 'miscalculated_total_amount_repair';
            $order->isCustomEvent = true;

            $order->auditCustomOld = [
                'item_code'     =>      '',
                'gross_amount'  =>      $transaction->submitted_gross_amount,
                'net_amount'    =>      $transaction->submitted_net_amount,
            ];

            $order->auditCustomNew = [
                'item_code'     =>      $transaction->historicAsset->code,
                'gross_amount'  =>      $grossAmt,
                'net_amount'    =>      $netAmt,
            ];

            $transaction->update([
                'gross_amount' => $grossAmt,
                'net_amount'   => $netAmt,
            ]);

            Event::dispatch(new AuditCustom($order));
        });

    }
}
