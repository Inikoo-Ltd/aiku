<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 23 Sept 2025 11:56:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Accounting;

use App\Actions\Helpers\SerialReference\GetSerialReference;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum;
use App\Enums\Helpers\SerialReference\SerialReferenceModelEnum;
use App\Models\Accounting\CreditTransaction;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Payment;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\SerialReference;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairAccountPaymentsCreditTransactionMismatch
{
    use WithActionUpdate;

    protected function handle(CreditTransaction $creditTransaction, Command $command): void
    {

        if($creditTransaction->type!=CreditTransactionTypeEnum::PAYMENT){
            return;
        }

        $payment = $creditTransaction->payment;
//        if($payment->id!=991480){
//            return;
//        }

        $paymentAmount=round(-$payment->amount,2);
        $creditTransactionAmount=round($creditTransaction->amount,2);
        $diff=$paymentAmount-$creditTransactionAmount;

        if($diff!=0){
           // dd($diff,$paymentAmount,$creditTransactionAmount,$creditTransaction->type);

            $command->line(" $diff  Payment  {$payment->id}  {$payment->created_at->format('c')}  ($payment->slug)  amount mismatch {$payment->amount} != {$creditTransaction->amount}");
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
