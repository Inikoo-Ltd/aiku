<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Whatsapp;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class HandleWhatsappWebhook
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        foreach (Arr::get($payload, 'entry', []) as $entry) {
            foreach (Arr::get($entry, 'changes', []) as $change) {
                if (Arr::get($change, 'field') !== 'messages') {
                    continue;
                }

                if (filled(Arr::get($change, 'value.messages'))) {
                    StoreIncomingWhatsappMessage::run($change['value']);
                }

                if (filled(Arr::get($change, 'value.statuses'))) {
                    UpdateWhatsappMessageStatus::run($change['value']);
                }
            }
        }
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        if (!$this->hasValidSignature($request)) {
            abort(401);
        }

        $this->handle($request->all());

        return response()->json(['received' => true]);
    }

    /**
     * Meta signs the raw body with the app secret. The check is waived only on local
     * installs that have no secret configured, so replaying payloads by hand keeps working.
     */
    protected function hasValidSignature(ActionRequest $request): bool
    {
        $appSecret = (string) config('meta.whatsapp.app_secret');

        if ($appSecret === '') {
            return app()->environment('local');
        }

        $expectedSignature = 'sha256='.hash_hmac('sha256', $request->getContent(), $appSecret);

        return hash_equals($expectedSignature, (string) $request->header('X-Hub-Signature-256'));
    }
}
