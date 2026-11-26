<?php

/*
 * Author: Eka Yudianta
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Whatsapp\Concerns;

use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Models\Chat\MetaChatMessage;
use App\Models\Chat\MetaChatSession;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Meta gives the history copy of a message a different wamid from the live message or the echo
 * Aiku already stored: the wamid encodes the phone number on the far side, and that is the
 * business number in history but the customer's number (or BSUID) live. The message key at the
 * end of the wamid is the same in both, so that is what the two copies are matched on.
 */
trait MatchesWhatsappHistory
{
    public const string WHATSAPP_HISTORY_SOURCE = 'whatsapp_business_app_history';

    public const int WHATSAPP_HISTORY_MATCH_SECONDS = 60;

    public static function waMessageKey(?string $waMessageId): ?string
    {
        if (!$waMessageId || !str_starts_with($waMessageId, 'wamid.')) {
            return null;
        }

        $decoded = base64_decode(substr($waMessageId, 6), true);

        if ($decoded === false || !preg_match('/\x18([\x01-\x7f])([\x21-\x7e]+)\x00$/', $decoded, $match)) {
            return null;
        }

        return ord($match[1]) === strlen($match[2]) ? $match[2] : null;
    }

    /**
     * @return Collection<string, true>
     */
    protected function knownWaMessageKeys(MetaChatSession $metaChatSession): Collection
    {
        $messages = $metaChatSession->messages()
            ->whereNotNull('meta_message_id')
            ->get(['id', 'meta_message_id', 'metadata']);

        return $messages
            ->flatMap(fn (MetaChatMessage $message) => [
                static::waMessageKey($message->meta_message_id),
                static::waMessageKey(Arr::get($message->metadata, 'wa_payload.original_message_id')),
            ])
            ->filter()
            ->mapWithKeys(fn (string $key) => [$key => true]);
    }

    protected function isStaffSender(ChatSenderTypeEnum $senderType): bool
    {
        return !in_array($senderType, [ChatSenderTypeEnum::GUEST, ChatSenderTypeEnum::USER], true);
    }

    /**
     * For a wamid whose key cannot be read: the same words from the same side within a minute of
     * a message that did not come from history is the same message.
     */
    protected function findLiveCopy(MetaChatSession $metaChatSession, bool $isStaff, ?string $text, Carbon $sentAt, ?int $exceptId = null): ?MetaChatMessage
    {
        if (blank($text)) {
            return null;
        }

        return $metaChatSession->messages()
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->where('message_text', $text)
            ->whereBetween('created_at', [
                $sentAt->copy()->subSeconds(self::WHATSAPP_HISTORY_MATCH_SECONDS),
                $sentAt->copy()->addSeconds(self::WHATSAPP_HISTORY_MATCH_SECONDS),
            ])
            ->where(fn ($query) => $query->whereNull('metadata->source')->orWhere('metadata->source', '!=', self::WHATSAPP_HISTORY_SOURCE))
            ->get()
            ->first(fn (MetaChatMessage $message) => $this->isStaffSender($message->sender_type) === $isStaff);
    }
}
