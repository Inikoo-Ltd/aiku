<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 27 Feb 2023 11:37:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\PaymentAccount\Hydrators;

use App\Models\Accounting\PaymentAccount;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;


class PaymentAccountHydratePayments implements ShouldBeUnique
{

    use AsAction;

    public function handle(PaymentAccount $paymentAccount): void
    {
        $paymentRecords = $paymentAccount->payments()->count();
        $refunds        = $paymentAccount->payments()->where('type', 'refund')->count();

        $dCAmountSuccessfullyPaid = $paymentAccount->payments()
            ->where('type', 'payment')
            ->where('status', 'success')
            ->sum('dc_amount');
        $dCAmountRefunded         = $paymentAccount->payments()
            ->where('type', 'refund')
            ->where('status', 'success')
            ->sum('dc_amount');

        $stats = [
            'number_payment_records'      => $paymentRecords,
            'number_payments'             => $paymentRecords - $refunds,
            'number_refunds'              => $refunds,
            'dc_amount'                   => $dCAmountSuccessfullyPaid + $dCAmountRefunded,
            'dc_amount_successfully_paid' => $dCAmountSuccessfullyPaid,
            'dc_amount_refunded'          => $dCAmountRefunded


        ];
        $paymentAccount->stats->update($stats);
    }

    public function getJobUniqueId(PaymentAccount $paymentAccount): int
    {
        return $paymentAccount->id;
    }


}


