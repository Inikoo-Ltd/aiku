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
            && $this->hubParameter($request, 'mode') === 'subscribe'
            && hash_equals($verifyToken, $this->hubParameter($request, 'verify_token'))
        ) {
            return response($this->hubParameter($request, 'challenge'), 200);
        }

        abort(403);
    }

  
    private function hubParameter(ActionRequest $request, string $name): string
    {
        return (string) ($request->query->get('hub.'.$name) ?? $request->query->get('hub_'.$name, ''));
    }
}
