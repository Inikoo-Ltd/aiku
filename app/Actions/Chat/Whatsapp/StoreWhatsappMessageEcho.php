<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Whatsapp;

use App\Actions\Chat\ChatSession\SettleChatAiDraft;
use App\Actions\Chat\MetaChatSession\RestoreMetaChatSession;
use App\Actions\Chat\MetaChatSession\StoreMetaChatMessage;
use App\Actions\Chat\Whatsapp\Concerns\WithWhatsappCredentials;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Events\BroadcastMetaChatListEvent;
use App\Events\BroadcastRealtimeMetaChat;
use App\Models\Catalogue\Shop;
use App\Models\Chat\MetaChannel;
use App\Models\Chat\MetaChatMessage;
use Illuminate\Support\Arr;

/**
 * A Coexistence number is still answered on the WhatsApp Business app, and Meta echoes what
 * staff send there on the smb_message_echoes field. Stored as a staff reply on the contact's
 * thread, so a chat answered on the phone does not sit in Aiku as unanswered. It is not a
 * customer message: nothing reopens, nothing goes unread, no AI draft or out-of-hours reply.
 */
class StoreWhatsappMessageEcho extends StoreIncomingWhatsappMessage
{
    use WithWhatsappCredentials;

    /**
     * @param  array<string, mixed>  $value  The `changes[].value` node of a smb_message_echoes webhook
     */
    public function handle(array $value): void
    {
        [$shop, $metaChannel] = $this->shopAndChannel($value);

        if (!$shop || !$metaChannel || !$this->isWhatsappCoexistence($shop)) {
            return;
        }

        foreach (Arr::get($value, 'message_echoes', []) as $echo) {
            $this->storeMessage($shop, $metaChannel, $value, $echo);
        }
    }

    /**
     * @param  array<string, mixed>  $value
     * @param  array<string, mixed>  $message
     */
    protected function storeMessage(Shop $shop, MetaChannel $metaChannel, array $value, array $message): void
    {
        $waMessageId = (string) Arr::get($message, 'id');
        $digits      = preg_replace('/\D/', '', (string) Arr::get($message, 'to'));
        $type        = (string) Arr::get($message, 'type');

        if ($waMessageId === '' || $digits === '' || $type === 'reaction' || MetaChatMessage::where('meta_message_id', $waMessageId)->exists()) {
            return;
        }

        $metaChatSession = $this->threadWithoutReopening($shop, $metaChannel, $digits);

        if ($metaChatSession->trashed()) {
            $metaChatSession = RestoreMetaChatSession::make()->handle($metaChatSession);
        }

        $waNode = Arr::get($message, $type);

        $metaChatMessage = StoreMetaChatMessage::run($metaChatSession, [
            'meta_message_id' => $waMessageId,
            'message_type'    => $this->messageType($type),
            'sender_type'     => ChatSenderTypeEnum::AGENT,
            'message_text'    => $this->messageText($type, $message, $waNode),
            'replied_to_id'   => $this->resolveQuotedMessageId($metaChatSession, (string) Arr::get($message, 'context.id')),
            'is_read'         => true,
            'metadata'        => [
                'wa_type'    => $type,
                'wa_payload' => $type !== 'text' ? $waNode : null,
                'wa_context' => Arr::get($message, 'context'),
                'wa_status'  => 'sent',
                'source'     => 'whatsapp_business_app',
            ],
        ]);

        if (in_array($type, DownloadWhatsappMedia::MEDIA_TYPES, true)) {
            DownloadWhatsappMedia::dispatch($metaChatMessage, $shop);
        }

        $metaChatSession->update(['last_agent_message_at' => now()]);

        // A reply typed on the phone answers the customer as surely as one sent from Aiku, so a
        // pending draft settles against it rather than waiting on a reply that already went.
        SettleChatAiDraft::run($metaChatSession, $metaChatMessage);

        $metaChatMessage = $metaChatMessage->fresh(['attachment', 'metaChatSession']);

        BroadcastRealtimeMetaChat::dispatch($metaChatMessage);
        BroadcastMetaChatListEvent::dispatch($metaChatMessage, $metaChatSession->fresh());
    }
}
