<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Oct 2025 12:39:30 Central Indonesia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Payment\CheckoutCom;

use App\Models\SysAdmin\Group;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ReceiveCheckoutComPaymentWebhook
{
    use AsAction;

    public function asController(ActionRequest $request): \Illuminate\Http\JsonResponse
    {
        $grpUlid = $request->headers->get('x-aiku-group-ulid');
        $authorization = $request->headers->get('authorization');

        if (!$authorization) {
            return response()->json([
                'error' => 'Missing required header: authorization'
            ], 400);
        }


        if (!$grpUlid) {
            return response()->json([
                'error' => 'Missing required header: x-aiku-group-ulid'
            ], 400);
        }

        $group = Group::where('ulid', $grpUlid)->first();
        if (!$group) {
            return response()->json([
                'error' => 'Invalid header: x-aiku-group-ulid'
            ], 400);
        }

        $validKeys=[];
        if($sandboxKey=Arr::get($group->settings,'checkout_com.webhook_auth.sandbox.authorization')){
            $validKeys[]=$sandboxKey;
        }
        if($productionKey=Arr::get($group->settings,'checkout_com.webhook_auth.production.authorization')){
            $validKeys[]=$productionKey;
        }

        if(!in_array($authorization,$validKeys)){
            return response()->json([
                'error' => 'Unauthorized'
            ], 400);
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
