<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 25 Aug 2025 12:11:37 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Payment;

use App\Models\Accounting\Payment;
use Lorisleiva\Actions\Concerns\AsAction;

class RefundPayment
{
    use AsAction;

    public function handle(Payment $payment)
    {

    }

}
