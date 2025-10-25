<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Oct 2025 12:39:30 Central Indonesia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Payment\CheckoutCom;

use App\Models\SysAdmin\Group;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ReceiveCheckoutComPaymentWebhook
{
    use AsAction;

    public function asController(ActionRequest $request): \Illuminate\Http\JsonResponse
    {
        $grpUlid = $request->headers->get('x-aiku-group-ulid');
        $group   = Group::where('ulid', $grpUlid)->first();
        if (!$group) {
            $group = Group::find(1);
        }
        $group->paymentGatewayLogs()->create([
            'payload' => $request->all(),
            'data' => [
                'headers' => $request->headers->all(),
            ],
            'gateway' => 'checkout-com'
        ]);

        return response()->json(['message' => 'Webhook received']);
    }


}
