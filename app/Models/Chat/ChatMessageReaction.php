<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Models\Chat;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $chat_message_id
 * @property int $chat_session_id
 * @property string $reactor_type
 * @property int|null $reactor_id
 * @property string $emoji
 */
class ChatMessageReaction extends Model
{
    protected $table = 'chat_message_reactions';

    protected $guarded = [];

    public function chatMessage(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class);
    }
}
