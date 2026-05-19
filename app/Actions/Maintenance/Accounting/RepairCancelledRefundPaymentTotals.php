<?php

/*
 * author Louis Perez
 * created on 19-05-2026-11h-19m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Maintenance\Accounting;

use App\Actions\Traits\WithActionUpdate;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Models\Accounting\Payment;
use Illuminate\Console\Command;

class RepairCancelledRefundPaymentTotals
{
    use WithActionUpdate;

    public function handle(Payment $payment): void
    {
        $originalPayment = $payment->originalPayment;
        if ($originalPayment) {
            $totalRefund = abs($originalPayment->refunds()->whereNot('state', PaymentStateEnum::CANCELLED->value)->sum('amount'));
            $originalPayment->update([
                'total_refund' => $totalRefund,
            ]);
        }
    }

    public string $commandSignature = 'repair:cancelled_refund_totals';

    public function asCommand(Command $command): void
    {
        $query = Payment::where('type', PaymentTypeEnum::REFUND->value)
            ->where('state', PaymentStateEnum::CANCELLED->value)
            ->whereNotNull('original_payment_id');

        $total = (clone $query)->count();        
            
        $progressBar   = $command->getOutput()->createProgressBar($total);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $progressBar->start();

        $query
            ->orderBy('id', 'asc')
            ->chunkById(1000, function ($chunk)  use (&$progressBar) {
                foreach($chunk as $payment) {
                    $this->handle($payment);
                    $progressBar->advance();
                }
            });
    }
}
