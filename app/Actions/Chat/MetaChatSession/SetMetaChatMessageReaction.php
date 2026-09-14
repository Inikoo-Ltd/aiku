<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\MetaChatSession;

use App\Events\BroadcastMetaChatReaction;
use App\Models\Chat\MetaChatMessage;
use Lorisleiva\Actions\Concerns\AsAction;

class SetMetaChatMessageReaction
{
    use AsAction;

    /**
     * WhatsApp allows a single reaction per person per message: a different emoji
     * replaces the previous one and an empty emoji clears it. This mirrors that exactly,
     * so an inbound webhook and an agent's own click end up in the same state.
     */
    public function handle(
        MetaChatMessage $metaChatMessage,
        string $reactorType,
        ?int $reactorId,
        string $emoji,
        ?string $metaMessageId = null
    ): MetaChatMessage {
        $query = $metaChatMessage->reactions()->where('reactor_type', $reactorType);

        if ($reactorId !== null) {
            $query->where('reactor_id', $reactorId);
        } else {
            $query->whereNull('reactor_id');
        }

        $existing = $query->first();

        if ($emoji === '') {
            $existing?->delete();
        } elseif ($existing) {
            $existing->update(['emoji' => $emoji, 'meta_message_id' => $metaMessageId]);
        } else {
            $metaChatMessage->reactions()->create([
                'meta_chat_session_id' => $metaChatMessage->meta_chat_session_id,
                'reactor_type'         => $reactorType,
                'reactor_id'           => $reactorId,
                'emoji'                => $emoji,
                'meta_message_id'      => $metaMessageId,
            ]);
        }

        $metaChatMessage->load('reactions');

        BroadcastMetaChatReaction::dispatch($metaChatMessage);

        return $metaChatMessage;
    }
}
