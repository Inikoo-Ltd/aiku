<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jun 2026 21:07:06 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Chat;

use App\Models\Helpers\Language;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $chat_message_id
 * @property int $target_language_id
 * @property string $translated_text
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Chat\ChatMessage|null $chatMessage
 * @property-read Language $targetLanguage
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatMessageTranslation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatMessageTranslation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatMessageTranslation query()
 * @mixin \Eloquent
 */
class ChatMessageTranslation extends Model
{
    use HasFactory;

    public $timestamps = true;

    protected $table = 'chat_message_translations';

    protected $fillable = [
        'chat_message_id',
        'target_language_id',
        'translated_text',
    ];

    /**
     * Get the chat message that owns the translation.
     */
    public function chatMessage(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class);
    }

    /**
     * Get the language of the translation.
     */
    public function targetLanguage(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'target_language_id');
    }
}
