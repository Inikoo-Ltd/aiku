<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Whatsapp;

use App\Actions\Chat\MetaChatSession\StoreMetaChatMessage;
use App\Actions\Chat\Whatsapp\Concerns\WithWhatsappCredentials;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Chat\MetaChannel;
use App\Models\Chat\MetaChatMessage;
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
            ->filter(fn ($message) => filled(Arr::get($message, 'id')))
            ->reject(fn ($message) => MetaChatMessage::where('meta_message_id', Arr::get($message, 'id'))->exists());

        if ($digits === '' || $messages->isEmpty()) {
            return;
        }

        $metaChatSession = $this->threadWithoutReopening($shop, $metaChannel, $digits);

        foreach ($messages as $message) {
            $type    = (string) Arr::get($message, 'type');
            $waNode  = Arr::get($message, $type);
            $isStaff = filled(Arr::get($message, 'to')) || preg_replace('/\D/', '', (string) Arr::get($message, 'from')) === $businessDigits;
            $sentAt  = Carbon::createFromTimestamp((int) Arr::get($message, 'timestamp', now()->timestamp));

            StoreMetaChatMessage::run($metaChatSession, [
                'meta_message_id' => Arr::get($message, 'id'),
                'message_type'    => $this->messageType($type),
                'sender_type'     => $isStaff ? ChatSenderTypeEnum::AGENT : ChatSenderTypeEnum::GUEST,
                'message_text'    => $type === 'media_placeholder'
                    ? __('Media shared on the WhatsApp Business app')
                    : $this->messageText($type, $message, $waNode),
                'is_read'         => true,
                'created_at'      => $sentAt,
                'updated_at'      => $sentAt,
                'metadata'        => [
                    'wa_type'    => $type,
                    'wa_payload' => $type !== 'text' ? $waNode : null,
                    'wa_status'  => Arr::get($message, 'history_context.status'),
                    'source'     => 'whatsapp_business_app_history',
                ],
            ]);

            $column = $isStaff ? 'last_agent_message_at' : 'last_visitor_message_at';

            if (!$metaChatSession->{$column} || $metaChatSession->{$column}->lt($sentAt)) {
                $metaChatSession->update([$column => $sentAt]);
            }
        }
    }
}
