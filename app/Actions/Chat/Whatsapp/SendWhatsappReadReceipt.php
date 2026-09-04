<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Whatsapp;

use App\Actions\Chat\Whatsapp\Concerns\WithWhatsappCredentials;
use App\Models\Chat\MetaChatMessage;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class SendWhatsappReadReceipt
{
    use AsAction;
    use WithWhatsappCredentials;

    public string $jobQueue = 'urgent';

    public function asJob(MetaChatMessage $metaChatMessage): void
    {
        $this->handle($metaChatMessage);
    }

    /**
     * Turns on the customer's blue ticks. Meta cascades the receipt to every earlier
     * message in the conversation, so sending it for the newest inbound message marks
     * the whole thread; passing any single message marks that one and its predecessors.
     */
    public function handle(MetaChatMessage $metaChatMessage): bool
    {
        $metaMessageId = (string) $metaChatMessage->meta_message_id;

        if ($metaMessageId === '' || $metaChatMessage->metaChannel?->code !== 'whatsapp') {
            return false;
        }

        [
            'phone_number_id' => $phoneNumberId,
            'access_token'    => $accessToken,
        ] = $this->whatsappCredentials($metaChatMessage->metaChatSession?->shop);

        if ($phoneNumberId === '' || $accessToken === '') {
            Log::warning('WhatsApp read receipt skipped, channel is not configured', [
                'meta_chat_message_id' => $metaChatMessage->id,
            ]);

            return false;
        }

        $response = Http::withToken($accessToken)->post($this->whatsappEndpoint($phoneNumberId.'/messages'), [
            'messaging_product' => 'whatsapp',
            'status'            => 'read',
            'message_id'        => $metaMessageId,
        ]);

        if ($response->failed()) {
            // Meta refuses receipts for messages it no longer holds (roughly 30 days),
            // and there is nothing to retry when that happens.
            Log::warning('WhatsApp read receipt failed', [
                'meta_chat_message_id' => $metaChatMessage->id,
                'meta_message_id'      => $metaMessageId,
                'status'               => $response->status(),
                'error'                => Arr::get($response->json(), 'error.message'),
            ]);

            return false;
        }

        return true;
    }
}
