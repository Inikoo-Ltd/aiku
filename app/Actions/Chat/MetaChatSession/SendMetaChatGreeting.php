<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 21 Sep 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\MetaChatSession;

use App\Actions\Chat\Whatsapp\Concerns\WithWhatsappCredentials;
use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Events\BroadcastRealtimeMetaChat;
use App\Models\Chat\MetaChatSession;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A stranger who only wrote "Hello" has given nothing to go on, and half of those turn out to
 * be suppliers fishing. One fixed line asks what they want, once: what comes back can be read.
 * The same words every time, never written by a model, and sent as the system so it counts
 * neither as an agent's reply nor as the conversation having been answered.
 */
class SendMetaChatGreeting
{
    use AsAction;
    use WithWhatsappCredentials;

    public function handle(MetaChatSession $metaChatSession, ?string $text = null, string $onceKey = 'greeted_at', bool $once = true): bool
    {
        if (($once && data_get($metaChatSession->metadata, $onceKey)) || !$metaChatSession->can_send_non_template_message) {
            return false;
        }

        ['phone_number_id' => $phoneNumberId, 'access_token' => $accessToken] = $this->whatsappCredentials($metaChatSession->shop);

        if ($phoneNumberId === '' || $accessToken === '') {
            return false;
        }

        $metaChatSession->update([
            'metadata' => array_merge($metaChatSession->metadata ?? [], [$onceKey => now()->toISOString()]),
        ]);

        $text ??= __('Hello, thank you for your message. How can we help you?', [], $metaChatSession->shop->language?->code);

        $response = Http::withToken($accessToken)->post($this->whatsappEndpoint($phoneNumberId.'/messages'), [
            'messaging_product' => 'whatsapp',
            'to'                => preg_replace('/\D/', '', (string) $metaChatSession->phone_number),
            'type'              => 'text',
            'text'              => ['body' => $text],
        ]);

        if ($response->failed()) {
            return false;
        }

        $metaChatMessage = StoreMetaChatMessage::run($metaChatSession, [
            'meta_message_id' => Arr::get($response->json(), 'messages.0.id'),
            'message_type'    => ChatMessageTypeEnum::TEXT,
            'sender_type'     => ChatSenderTypeEnum::SYSTEM,
            'message_text'    => $text,
            'metadata'        => ['wa_status' => 'sent', $onceKey => true],
        ]);

        BroadcastRealtimeMetaChat::dispatch($metaChatMessage->fresh(['attachment', 'metaChatSession']));

        return true;
    }
}
