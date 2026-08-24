<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Whatsapp;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
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
        // TODO: remove later
        Log::info('WhatsApp webhook received', $payload);

        foreach (Arr::get($payload, 'entry', []) as $entry) {
            foreach (Arr::get($entry, 'changes', []) as $change) {
                if (Arr::get($change, 'field') !== 'messages') {
                    continue;
                }

                if (filled(Arr::get($change, 'value.messages'))) {
                    StoreIncomingWhatsappMessage::dispatch($change['value']);
                }

                if (filled(Arr::get($change, 'value.statuses'))) {
                    UpdateWhatsappMessageStatus::dispatch($change['value']);
                }
            }
        }
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        // TODO: make sure the security later
        // $appSecret = (string) config('meta.whatsapp.app_secret');

        // $expectedSignature = 'sha256='.hash_hmac('sha256', $request->getContent(), $appSecret);

        // if ($appSecret === '' || !hash_equals($expectedSignature, (string) $request->header('X-Hub-Signature-256'))) {
        //     abort(401);
        // }

        $this->handle($request->all());

        return response()->json(['received' => true]);
    }
}
