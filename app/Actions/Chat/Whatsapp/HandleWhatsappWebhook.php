<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Whatsapp;

use App\Actions\Chat\Whatsapp\Concerns\WithWhatsappCredentials;
use App\Actions\Chat\Whatsapp\Templates\UpdateWhatsappTemplateStatus;
use App\Models\Catalogue\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class HandleWhatsappWebhook
{
    use AsAction;
    use WithWhatsappCredentials;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        foreach (Arr::get($payload, 'entry', []) as $entry) {
            foreach (Arr::get($entry, 'changes', []) as $change) {
                if (Arr::get($change, 'field') === 'message_template_status_update') {
                    UpdateWhatsappTemplateStatus::run($change['value']);

                    continue;
                }

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
        $appSecret = $this->webhookSecret($request);

        if ($appSecret === '') {
            return app()->environment('local');
        }

        $expectedSignature = 'sha256='.hash_hmac('sha256', $request->getContent(), $appSecret);

        return hash_equals($expectedSignature, (string) $request->header('X-Hub-Signature-256'));
    }

    /**
     * Every organisation registers its own Meta app, so which secret signs a payload
     * depends on the number it is addressed to. Reading that number from the unverified
     * body only selects which secret to check against — the signature still has to match,
     * so nothing is trusted before it is proven.
     */
    protected function webhookSecret(ActionRequest $request): string
    {
        $phoneNumberId = (string) $request->json('entry.0.changes.0.value.metadata.phone_number_id');

        $organisation = $phoneNumberId === ''
            ? null
            : Shop::whereJsonContains('settings->whatsapp->phone_number_id', $phoneNumberId)->first()?->organisation;

        return $this->metaAppCredentials($organisation)['app_secret'];
    }
}
