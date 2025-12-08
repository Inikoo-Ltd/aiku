<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 29 Dec 2024 00:34:37 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Hydrators;

use App\Models\Accounting\OrgPaymentServiceProvider;
use App\Models\Accounting\PaymentAccount;
use App\Models\Accounting\PaymentServiceProvider;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;

trait WithPaymentAggregators
{
    public function paidAmounts(OrgPaymentServiceProvider|PaymentAccount|PaymentServiceProvider|Group|Organisation|Shop $model, string $currencyField): array
    {
        $successfullyPaidAmount = $model->payments()
            ->where('payments.type', 'payment')
            ->where('status', 'success')
            ->sum($currencyField);
        $refundedAmount = $model->payments()
            ->where('payments.type', 'refund')
            ->where('status', 'success')
            ->sum($currencyField);
        $balanceAmount = $successfullyPaidAmount + $refundedAmount;

        $prefix = '';
        if ($currencyField == 'grp_amount') {
            $prefix = 'grp_';
        } elseif ($currencyField == 'org_amount') {
            $prefix = 'org_';
        }

        return [

            $prefix.'amount_successfully_paid' => $successfullyPaidAmount,
            $prefix.'amount_refunded' => $refundedAmount,
            $prefix.'amount_paid_balance' => $balanceAmount,
        ];
    }
}
