<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Models\Chat;

use App\Models\Helpers\Language;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $meta_chat_message_id
 * @property int $target_language_id
 * @property string $translated_text
 */
class MetaChatMessageTranslation extends Model
{
    protected $table = 'meta_chat_message_translations';

    protected $fillable = [
        'meta_chat_message_id',
        'target_language_id',
        'translated_text',
    ];

    public function metaChatMessage(): BelongsTo
    {
        return $this->belongsTo(MetaChatMessage::class);
    }

    public function targetLanguage(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'target_language_id');
    }
}
