<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 May 2025 19:14:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\OrderPaymentApiPoint\WebHooks;

use App\Actions\OrgAction;
use App\Models\Accounting\OrderPaymentApiPoint;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckoutComOrderPaymentFailure extends OrgAction
{
    use AsAction;

    public function handle(OrderPaymentApiPoint $orderPaymentApiPoint, ActionRequest $request)
    {
        dd($orderPaymentApiPoint, $request);
    }

    public function asController(OrderPaymentApiPoint $orderPaymentApiPoint, ActionRequest $request)
    {
        $this->initialisation($orderPaymentApiPoint->organisation, $request);
        $this->handle($orderPaymentApiPoint, $request);
    }

}
