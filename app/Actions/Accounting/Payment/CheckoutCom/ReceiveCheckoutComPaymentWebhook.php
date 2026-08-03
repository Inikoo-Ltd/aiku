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

        $validKeys = [];
        if (app()->environment('local')) {
            if ($localKey = config('app.sandbox.checkout_com.webhook_key')) {
                $validKeys[] = $localKey;
            }
        } elseif ($sandboxKey = Arr::get($group->settings, 'checkout_com.webhook_auth.sandbox.authorization')) {
            $validKeys[] = $sandboxKey;
        }
        if ($productionKey = Arr::get($group->settings, 'checkout_com.webhook_auth.production.authorization')) {
            $validKeys[] = $productionKey;
        }

        $authorized = false;
        foreach ($validKeys as $validKey) {
            if (hash_equals((string)$validKey, (string)$authorization)) {
                $authorized = true;
            }
        }

        if (!$authorized) {
            return response()->json([
                'error' => 'Unauthorized'
            ], 400);
        }

        $paymentGatewayLog = $group->paymentGatewayLogs()->create([
            'payload' => $request->all(),
            'data' => [
                'headers' => $request->headers->all(),
            ],
            'gateway' => 'checkout-com'
        ]);

        PreProcessCheckoutComPaymentGatewayLog::run($paymentGatewayLog);

        return response()->json(['message' => 'Webhook received']);
    }


}
