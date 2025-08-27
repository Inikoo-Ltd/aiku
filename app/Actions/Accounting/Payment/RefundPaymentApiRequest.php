<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 26 Aug 2025 16:36:49 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Payment;

use App\Actions\Accounting\WithCheckoutCom;
use App\Actions\OrgAction;
use App\Models\Accounting\Payment;

class RefundPaymentApiRequest extends OrgAction
{
    use WithCheckoutCom;

    public function handle(Payment $payment, string $paymentId): array
    {
        return $this->refundPayment($payment->paymentAccountShop, $paymentId, abs($payment->amount));
    }
}
