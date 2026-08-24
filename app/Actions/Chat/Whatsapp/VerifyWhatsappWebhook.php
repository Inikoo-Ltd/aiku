<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Whatsapp;

use Illuminate\Http\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class VerifyWhatsappWebhook
{
    use AsAction;

    public function asController(ActionRequest $request): Response
    {
        $verifyToken = (string) config('meta.whatsapp.webhook_verify_token');

        if (
            $verifyToken !== ''
            && $request->query('hub_mode') === 'subscribe'
            && hash_equals($verifyToken, (string) $request->query('hub_verify_token'))
        ) {
            return response($request->query('hub_challenge'), 200);
        }

        abort(403);
    }
}
