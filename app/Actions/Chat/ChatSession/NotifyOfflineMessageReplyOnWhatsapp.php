<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Comms\WhatsappOutbox\SendCustomerNotificationWhatsapp;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Chat\ChatSession;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * An offline message is answered hours later, often by email, and the customer may not be
 * watching that inbox. With the shop's `chat.whatsapp_offline_replies` on, the first staff
 * answer to it also sends the "Chat notification to customer" WhatsApp. Only the first: the
 * marker is cleared by that answer and set again only by the next offline message, so a
 * conversation that goes on by email does not message the customer on every reply.
 */
class NotifyOfflineMessageReplyOnWhatsapp
{
    use AsObject;

    public const PENDING_KEY = 'whatsapp_offline_reply_pending_since';

    public static function markPending(ChatSession $chatSession): void
    {
        SetChatSessionMetadata::run($chatSession, [self::PENDING_KEY => now()->toISOString()]);
    }

    public function handle(ChatSession $chatSession): bool
    {
        if (!Arr::get($chatSession->metadata ?? [], self::PENDING_KEY)) {
            return false;
        }

        SetChatSessionMetadata::run($chatSession, [self::PENDING_KEY => null]);

        if (!Arr::get($chatSession->shop?->settings ?? [], 'chat.whatsapp_offline_replies')) {
            return false;
        }

        $customer = $chatSession->webUser?->customer;

        if (!$customer) {
            return false;
        }

        SendCustomerNotificationWhatsapp::dispatch(
            $customer,
            OutboxCodeEnum::CHAT_NOTIFICATION_TO_CUSTOMER,
            null,
            ['chat_url' => self::chatLink($chatSession)]
        );

        return true;
    }

    public static function chatLink(ChatSession $chatSession): ?string
    {
        if (!$chatSession->shop?->website || !$chatSession->ulid) {
            return null;
        }

        return $chatSession->shop->website->getUrl().'/?chat_session='.$chatSession->ulid;
    }
}
