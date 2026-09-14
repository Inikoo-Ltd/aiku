<?php

/*
 * Author: Eka Yudinata <dev@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Whatsapp;

use App\Enums\CRM\Livechat\MetaTrackingEventTypeEnum;
use App\Models\Chat\MetaChatMessage;
use App\Models\Chat\MetaChatSession;
use App\Models\Chat\MetaTrackingEvent;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreMetaTrackingEvent
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(
        MetaTrackingEventTypeEnum $type,
        ?MetaChatMessage $metaChatMessage = null,
        ?string $metaMessageId = null,
        array $data = [],
        ?Carbon $happenedAt = null,
        ?MetaChatSession $metaChatSession = null
    ): ?MetaTrackingEvent {
        $metaMessageId ??= $metaChatMessage?->meta_message_id;

        try {
            return MetaTrackingEvent::firstOrCreate(
                ['source_id' => $this->sourceId($type, $metaMessageId, $metaChatMessage)],
                [
                    'meta_chat_session_id' => $metaChatMessage?->meta_chat_session_id ?? $metaChatSession?->id,
                    'meta_chat_message_id' => $metaChatMessage?->id,
                    'meta_message_id'      => $metaMessageId,
                    'type'                 => $type->value,
                    'data'                 => $data,
                    'created_at'           => $happenedAt ?? now(),
                ]
            );
        } catch (Exception $e) {
            /* History is never worth failing a send or a webhook over: this runs inside the
               send paths, where a throw would abort a message the customer is waiting on, and
               inside the webhook, where it would make Meta retry something already processed. */
            Log::error('Failed to store meta tracking event', [
                'meta_message_id'      => $metaMessageId,
                'meta_chat_message_id' => $metaChatMessage?->id,
                'type'                 => $type->value,
                'error'                => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Meta retries webhooks, so the same status can arrive more than once; keying on the
     * wamid and the status makes a replay a no-op rather than a duplicate row.
     *
     * A send that never reached Meta has no wamid to key on and no webhook will ever
     * follow, so those rows get a unique key instead - collapsing every failure into one
     * row would hide exactly what they are kept for.
     */
    private function sourceId(
        MetaTrackingEventTypeEnum $type,
        ?string $metaMessageId,
        ?MetaChatMessage $metaChatMessage
    ): string {
        if ($metaMessageId) {
            return $metaMessageId.':'.$type->value;
        }

        return implode(':', [$type->value, $metaChatMessage?->id ?? 'unknown', uniqid()]);
    }
}
