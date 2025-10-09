<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 May 2025 12:38:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\TopUpPaymentApiPoint\WebHooks;

use App\Actions\RetinaAction;
use App\Http\Resources\Accounting\TopUpResource;
use App\Models\Accounting\CreditTransaction;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectSuccessPaymentTopUp extends RetinaAction
{
    public function asController(CreditTransaction $creditTransaction, ActionRequest $request): \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
    {

        $this->initialisation($request);

        return Redirect::route('retina.top_up.dashboard')->with(
            'notification',
            [
                'status'  => 'success',
                'title'   => __('Success!'),
                'message' => __('Top up balance :amount has been successfully processed.', [
                    'amount' => $creditTransaction->amount
                ]),
                'top_up'  => TopUpResource::make($creditTransaction->topUp)
            ]
        );
    }

}
