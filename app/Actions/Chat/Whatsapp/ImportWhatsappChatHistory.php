<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Whatsapp;

use App\Actions\Chat\MetaChatSession\StoreMetaChatMessage;
use App\Actions\Chat\Whatsapp\Concerns\MatchesWhatsappHistory;
use App\Actions\Chat\Whatsapp\Concerns\WithWhatsappCredentials;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Chat\MetaChannel;
use App\Models\Chat\MetaChatMessage;
use App\Models\Chat\MetaChatSession;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * When a Coexistence number is connected, Meta shares up to six months of the Business app's
 * one to one chats on the history field, in chunks that may arrive in any order. They are the
 * past, so they are filed as dated, read messages: no session opens, nobody is notified, and a
 * contact who has not written since gets a closed thread rather than a waiting one.
 */
class ImportWhatsappChatHistory extends StoreIncomingWhatsappMessage
{
    use WithWhatsappCredentials;
    use MatchesWhatsappHistory;

    public string $jobQueue = 'default';

    /**
     * @param  array<string, mixed>  $value  The `changes[].value` node of a history webhook
     */
    public function handle(array $value): void
    {
        [$shop, $metaChannel] = $this->shopAndChannel($value);

        if (!$shop || !$metaChannel || !$this->isWhatsappCoexistence($shop)) {
            return;
        }

        $businessDigits = preg_replace('/\D/', '', (string) Arr::get($value, 'metadata.display_phone_number'));

        foreach (Arr::get($value, 'history', []) as $chunk) {
            // A business that turned history sharing off on the app (error 2593109) sends this
            // instead of threads; there is nothing to import, now or later.
            if ($errors = Arr::get($chunk, 'errors')) {
                Log::info('WhatsApp history not shared', ['shop_id' => $shop->id, 'errors' => $errors]);

                continue;
            }

            foreach (Arr::get($chunk, 'threads', []) as $thread) {
                try {
                    $this->importThread($shop, $metaChannel, $businessDigits, $thread);
                } catch (Throwable $exception) {
                    Log::warning('WhatsApp history thread not imported', [
                        'shop_id' => $shop->id,
                        'thread'  => Arr::get($thread, 'id'),
                        'error'   => $exception->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $thread
     */
    protected function importThread(Shop $shop, MetaChannel $metaChannel, string $businessDigits, array $thread): void
    {
        $digits   = preg_replace('/\D/', '', (string) Arr::get($thread, 'id'));
        $messages = collect(Arr::get($thread, 'messages', []))
            ->filter(fn ($message) => filled(Arr::get($message, 'id')) && Arr::get($message, 'type') !== 'reaction')
            ->reject(fn ($message) => MetaChatMessage::where('meta_message_id', Arr::get($message, 'id'))->exists());

        if ($digits === '' || $messages->isEmpty()) {
            return;
        }

        $metaChatSession = $this->threadWithoutReopening($shop, $metaChannel, $digits);
        $knownKeys       = $this->knownWaMessageKeys($metaChatSession);

        foreach ($messages->sortBy(fn ($message) => (int) Arr::get($message, 'timestamp')) as $message) {
            $type    = (string) Arr::get($message, 'type');
            $waNode  = Arr::get($message, $type);
            $isStaff = filled(Arr::get($message, 'to')) || preg_replace('/\D/', '', (string) Arr::get($message, 'from')) === $businessDigits;
            $sentAt  = Carbon::createFromTimestamp((int) Arr::get($message, 'timestamp', now()->timestamp));
            $key     = static::waMessageKey(Arr::get($message, 'id'));
            $text    = $this->historyText($type, $message, $waNode);

            if (($key && $knownKeys->has($key)) || $this->findLiveCopy($metaChatSession, $isStaff, $text, $sentAt)) {
                continue;
            }

            if ($type === 'edit' && $this->applyEdit($metaChatSession, (array) $waNode, $text, $sentAt)) {
                continue;
            }

            StoreMetaChatMessage::run($metaChatSession, [
                'meta_message_id' => Arr::get($message, 'id'),
                'message_type'    => $this->messageType($type),
                'sender_type'     => $isStaff ? ChatSenderTypeEnum::AGENT : ChatSenderTypeEnum::GUEST,
                'message_text'    => $text,
                'is_read'         => true,
                'created_at'      => $sentAt,
                'updated_at'      => $sentAt,
                'metadata'        => [
                    'wa_type'    => $type,
                    'wa_payload' => $type !== 'text' ? $waNode : null,
                    'wa_status'  => Arr::get($message, 'history_context.status'),
                    'source'     => self::WHATSAPP_HISTORY_SOURCE,
                ],
            ]);

            foreach (array_filter([$key, static::waMessageKey(Arr::get($waNode, 'original_message_id'))]) as $storedKey) {
                $knownKeys->put($storedKey, true);
            }

            $column = $isStaff ? 'last_agent_message_at' : 'last_visitor_message_at';

            if (!$metaChatSession->{$column} || $metaChatSession->{$column}->lt($sentAt)) {
                $metaChatSession->update([$column => $sentAt]);
            }
        }
    }

    /**
     * History carries types a live webhook never does (errors for what WhatsApp will not share,
     * edit, a product card sent from the app); every one of them gets words, so no empty bubble.
     *
     * @param  array<string, mixed>  $message
     */
    public function historyText(string $type, array $message, mixed $waNode): ?string
    {
        $text = match ($type) {
            'media_placeholder' => __('Media shared on the WhatsApp Business app'),
            'errors'            => __('Unsupported message'),
            'edit'              => Arr::get($waNode, 'message.text.body') ?? Arr::get($waNode, 'message.'.Arr::get($waNode, 'message.type').'.caption'),
            'interactive'       => $this->interactiveText((array) $waNode)
                ?? Arr::get($waNode, 'body.text')
                ?? Arr::get($waNode, 'product.title')
                ?? Arr::get($waNode, 'header.text'),
            default             => $this->messageText($type, $message, $waNode),
        };

        if (blank($text) && !in_array($type, DownloadWhatsappMedia::MEDIA_TYPES, true)) {
            return __('Unsupported message: :type', ['type' => $type ?: 'unknown']);
        }

        return $text;
    }

    /**
     * @param  array<string, mixed>  $edit
     */
    public function applyEdit(MetaChatSession $metaChatSession, array $edit, ?string $text, Carbon $editedAt): bool
    {
        $original = $this->findEditedMessage($metaChatSession, $edit);

        if (!$original || blank($text)) {
            return false;
        }

        $original->update([
            'message_text' => $text,
            'metadata'     => array_merge($original->metadata ?? [], ['wa_edited_at' => $editedAt->toIso8601String()]),
        ]);

        return true;
    }

    /**
     * @param  array<string, mixed>  $edit
     */
    public function findEditedMessage(MetaChatSession $metaChatSession, array $edit): ?MetaChatMessage
    {
        $originalId  = (string) Arr::get($edit, 'original_message_id');
        $originalKey = static::waMessageKey($originalId);

        if ($originalId === '') {
            return null;
        }

        return $metaChatSession->messages()
            ->whereNotNull('meta_message_id')
            ->where(fn ($query) => $query->whereNull('metadata->wa_type')->orWhere('metadata->wa_type', '!=', 'edit'))
            ->get()
            ->first(fn (MetaChatMessage $message) => $message->meta_message_id === $originalId
                || ($originalKey && static::waMessageKey($message->meta_message_id) === $originalKey));
    }
}
