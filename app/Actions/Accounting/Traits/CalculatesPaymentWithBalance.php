<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 08 Jul 2025 12:19:00 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Traits;

trait CalculatesPaymentWithBalance
{
    /**
     * Calculate payment amounts using customer balance.
     *
     * @param float $totalAmount The total amount to pay
     * @param float $balance The customer's balance
     * @return array Returns an array with 'by_balance' and 'by_other' payment amounts
     */
    protected function calculatePaymentWithBalance(float $totalAmount, float $balance): array
    {
        $toPay = (float) max($totalAmount, 0.0);

        $toPay = round($toPay, 2);

        $payFloatWithBalance = min($toPay, $balance);



        $remainingBalance = $toPay - $payFloatWithBalance;


        return [
            'by_balance' => $payFloatWithBalance,
            'by_other' => $remainingBalance,
            'total' => $toPay
        ];
    }
}
