<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 May 2025 12:32:34 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\TopUpPaymentApiPoint\WebHooks;

use App\Actions\OrgAction;
use App\Models\Accounting\TopUpPaymentApiPoint;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class TopUpPaymentFailure extends OrgAction
{
    use AsAction;

    public function handle(TopUpPaymentApiPoint $topUpPaymentApiPoint, ActionRequest $request)
    {
        dd($topUpPaymentApiPoint, $request);
    }

    public function asController(TopUpPaymentApiPoint $topUpPaymentApiPoint, ActionRequest $request)
    {
        $this->initialisation($topUpPaymentApiPoint->organisation, $request);
        $this->handle($topUpPaymentApiPoint, $request);
    }

}
