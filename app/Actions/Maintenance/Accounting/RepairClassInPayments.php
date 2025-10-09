<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 01 Aug 2025 12:48:39 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Accounting;

use App\Actions\Traits\WithActionUpdate;
use App\Enums\Accounting\Payment\PaymentClassEnum;
use App\Models\Accounting\Payment;
use Illuminate\Console\Command;

class RepairClassInPayments
{
    use WithActionUpdate;

    protected function handle(Payment $payment): void
    {
        $payment->update([
            'class' => PaymentClassEnum::TOPUP
        ]);
    }

    public string $commandSignature = 'repair:class_payments';

    public function asCommand(Command $command): void
    {
        $count = Payment::has('creditTransaction')
            ->count();

        $command->info("pending: $count");

        Payment::has('creditTransaction')
            ->chunk(1000, function ($payments) {
                foreach ($payments as $payment) {
                    $this->handle($payment);
                }
            });
    }

}
