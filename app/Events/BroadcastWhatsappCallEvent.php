<?php

/*
 * Author: Eka Yudinata <ekayudinata@gmail.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Events;

use App\Models\Chat\MetaChatCall;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BroadcastWhatsappCallEvent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public string $ulid;

    public function __construct(public MetaChatCall $metaChatCall)
    {
        $this->ulid = $metaChatCall->metaChatSession->ulid;
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("meta-chat-session.{$this->ulid}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'call';
    }

    /**
     * The offer is deliberately left out: it is only of use to the browser that answers the
     * call, which asks for it over the wire it authenticated on, and a private channel is
     * still read by every agent watching the conversation.
     */
    public function broadcastWith(): array
    {
        return [
            'id'               => $this->metaChatCall->id,
            'wa_call_id'       => $this->metaChatCall->wa_call_id,
            'status'           => $this->metaChatCall->status->value,
            'direction'        => $this->metaChatCall->direction->value,
            'phone_number'     => $this->metaChatCall->phone_number,
            'user_id'          => $this->metaChatCall->user_id,
            'duration_seconds' => $this->metaChatCall->duration_seconds,
            'answered_at'      => $this->metaChatCall->answered_at?->toIso8601String(),
            'ended_at'         => $this->metaChatCall->ended_at?->toIso8601String(),
        ];
    }
}
