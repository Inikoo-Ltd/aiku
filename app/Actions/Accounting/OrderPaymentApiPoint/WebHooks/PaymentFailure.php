<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 May 2025 19:14:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\OrderPaymentApiPoint\WebHooks;

use App\Actions\OrgAction;
use App\Models\Accounting\PaymentAccountShop;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class PaymentFailure extends OrgAction
{
    use AsAction;

    public function handle(PaymentAccountShop $paymentAccountShop)
    {

    }

    public function asController(PaymentAccountShop $paymentAccountShop, ActionRequest $request)
    {
        $this->initialisation($paymentAccountShop->organisation, $request);
    }

}
