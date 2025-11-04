<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 23 Sept 2025 11:56:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Accounting;

use App\Actions\Traits\WithActionUpdate;
use App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum;
use App\Models\Accounting\CreditTransaction;
use Illuminate\Console\Command;

class RepairAccountPaymentsCreditTransactionMismatch
{
    use WithActionUpdate;

    protected function handle(CreditTransaction $creditTransaction, Command $command): void
    {

        if ($creditTransaction->type != CreditTransactionTypeEnum::PAYMENT) {
            return;
        }

        $payment = $creditTransaction->payment;


        $order = $payment->orders()->first();




        $paymentAmount = round(-$payment->amount, 2);
        $creditTransactionAmount = round($creditTransaction->amount, 2);
        $diff = $paymentAmount - $creditTransactionAmount;

        if ($diff != 0) {
            $command->line(" $diff  $order->slug  $order->platform_id  Payment  {$payment->id}  {$payment->created_at->format('c')}  ($payment->slug)  amount mismatch {$payment->amount} != {$creditTransaction->amount}");
        }



    }


    public string $commandSignature = 'repair:payments_credit_mismatch ';

    public function asCommand(Command $command): void
    {

        CreditTransaction::whereNull('source_id')->whereNotNull('payment_id')
            ->orderBy('date')
            ->chunk(1000, function ($creditTransactions) use ($command) {
                foreach ($creditTransactions as $creditTransaction) {
                    $this->handle($creditTransaction, $command);
                }
            });
    }

}
