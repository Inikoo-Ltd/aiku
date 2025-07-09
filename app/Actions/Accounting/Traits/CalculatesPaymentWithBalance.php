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

        $decimalPart = $toPay - floor($toPay);

        $payFloatWithBalance = min($decimalPart, $balance);

        $remainingBalance = $balance - $payFloatWithBalance;
        $payIntWithBalance = min(floor($toPay), floor($remainingBalance));

        $toPayByBalance = round($payFloatWithBalance + $payIntWithBalance, 2);
        $toPayByOther = round($toPay - $toPayByBalance, 2);

        return [
            'by_balance' => $toPayByBalance,
            'by_other' => $toPayByOther,
            'total' => $toPay
        ];
    }
}
