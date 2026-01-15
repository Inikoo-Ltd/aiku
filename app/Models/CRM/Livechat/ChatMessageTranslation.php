<?php

namespace App\Models\CRM\Livechat;

use App\Models\Helpers\Language;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
