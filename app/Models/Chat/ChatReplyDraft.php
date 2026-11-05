<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Nov 2026 19:20:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Chat;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reply an agent has started and not sent yet (HELP-4523). One per conversation and agent,
 * kept on the server so colleagues see it and it follows the agent to another computer.
 *
 * @property int $id
 * @property int $chat_session_id
 * @property int $chat_agent_id
 * @property string $text
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read ChatSession $chatSession
 * @property-read ChatAgent $chatAgent
 */
class ChatReplyDraft extends Model
{
    protected $guarded = [];

    public function chatSession(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class);
    }

    public function chatAgent(): BelongsTo
    {
        return $this->belongsTo(ChatAgent::class);
    }

    public function writerName(): string
    {
        return $this->chatAgent?->user?->contact_name ?: ($this->chatAgent?->user?->username ?? '');
    }
}
