<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Oct 2025 15:33:19 Central Indonesia Time, Kuta, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Payment\CheckoutCom;

use App\Models\PaymentGatewayLog;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessCheckoutComPaymentGatewayLog
{
    use asAction;

    public function handle(PaymentGatewayLog $paymentGatewayLog): PaymentGatewayLog
    {
        return $paymentGatewayLog;
    }





}
