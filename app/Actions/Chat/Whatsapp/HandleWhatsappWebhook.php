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
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class HandleWhatsappWebhook
{
    use AsAction;
    use WithWhatsappCredentials;

    /**
     * Every change is queued rather than worked here. Meta retries a webhook it does not get
     * a 200 for within seconds, and a retry means the same message stored twice and the same
     * broadcasts fired again, so the response must not wait on the work the payload asks for.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        foreach (Arr::get($payload, 'entry', []) as $entry) {
            foreach (Arr::get($entry, 'changes', []) as $change) {
                if (Arr::get($change, 'field') === 'message_template_status_update') {
                    UpdateWhatsappTemplateStatus::dispatch($change['value']);

                    continue;
                }

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
        if (!$this->hasValidSignature($request)) {
            abort(401);
        }

        $this->handle($request->all());

        return response()->json(['received' => true]);
    }

    /**
     * Meta signs the raw body with the app secret. Local installs skip the check outright
     * so payloads can be replayed by hand; everywhere else it is mandatory, and a missing
     * secret rejects rather than waves the request through.
     */
    protected function hasValidSignature(ActionRequest $request): bool
    {
        if (app()->environment('local')) {
            return true;
        }

        $appSecret = $this->webhookSecret($request);

        if ($appSecret === '') {
            Log::warning('WhatsApp webhook rejected: no Meta app secret set for this organisation');

            return false;
        }

        $expectedSignature = 'sha256='.hash_hmac('sha256', $request->getContent(), $appSecret);

        return hash_equals($expectedSignature, (string) $request->header('X-Hub-Signature-256'));
    }

    /**
     * Every organisation registers its own Meta app, so which secret signs a payload
     * depends on which account it is addressed to. Reading that from the unverified body
     * only selects which secret to check against — the signature still has to match, so
     * nothing is trusted before it is proven.
     */
    protected function webhookSecret(ActionRequest $request): string
    {
        return $this->metaAppCredentials($this->webhookShop($request)?->organisation)['app_secret'];
    }

    /**
     * Messages and statuses are addressed to a phone number, but a template belongs to the
     * WhatsApp Business Account rather than to any one of the numbers under it, so a
     * message_template_status_update carries no metadata at all. Its account is the entry
     * id, which every payload carries, so the number answers first and the account is what
     * is left when there is no number to go on.
     *
     * A shop's support number resolves here too: it belongs to the same organisation, so
     * it is the same app secret that has to verify the payload.
     */
    protected function webhookShop(ActionRequest $request): ?Shop
    {
        $phoneNumberId = (string) $request->json('entry.0.changes.0.value.metadata.phone_number_id');

        if ($phoneNumberId !== '') {
            return $this->resolveWhatsappNumber($phoneNumberId)['shop'] ?? null;
        }

        $wabaId = (string) $request->json('entry.0.id');

        if ($wabaId !== '') {
            return Shop::whereJsonContains('settings->whatsapp->waba_id', $wabaId)->first();
        }

        return null;
    }
}
