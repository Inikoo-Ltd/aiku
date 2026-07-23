<?php

namespace App\Actions\Maintenance\Ordering;

use App\Actions\Ordering\Order\CalculateOrderTotalAmounts;
use App\Models\Ordering\Transaction;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairIsFollowOnTransactionsTotal
{
    use AsAction;

    public function handle(Transaction $transaction, ?Command $command = null)
    {
        $transaction->update([
            'gross_amount'      => 0,
            'net_amount'        => 0,
            'grp_net_amount'    => 0,
            'org_net_amount'    => 0,
        ]);

        $order = $transaction->order;

        $command->info("Repaired: {$transaction->id} | Order: {$order->slug}");

        CalculateOrderTotalAmounts::run($order);
    }

    public string $commandSignature = 'repair:is_follow_on_transactions';

    public function asCommand(Command $command)
    {
        $transactions = Transaction::where('is_follow_on', true)
            ->where(function ($q) {
                $q->where('net_amount', '>', 0)
                    ->orWhere('gross_amount', '>', 0);
            });

        $transactions->chunkById(100, function ($chunks) use ($command) {
            foreach ($chunks as $transaction) {
                $this->handle($transaction, $command);
            }
        });
    }
}
