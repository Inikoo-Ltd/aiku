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
 * @property int $meta_chat_message_id
 * @property int $meta_chat_session_id
 * @property string $reactor_type
 * @property int|null $reactor_id
 * @property string $emoji
 * @property string|null $meta_message_id
 */
class MetaChatMessageReaction extends Model
{
    protected $table = 'meta_chat_message_reactions';

    protected $guarded = [];

    public function metaChatMessage(): BelongsTo
    {
        return $this->belongsTo(MetaChatMessage::class);
    }
}
