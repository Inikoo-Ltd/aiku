<?php

namespace App\Models\Chat;

use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Models\Helpers\Language;
use App\Models\Helpers\Media;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $meta_channel_id
 * @property int|null $meta_chat_session_id
 * @property ChatMessageTypeEnum $message_type
 * @property ChatSenderTypeEnum $sender_type
 * @property int|null $sender_id
 * @property string|null $message_text
 * @property int|null $media_id
 * @property bool|null $is_ai_generated
 * @property bool|null $is_validated
 * @property bool $is_read
 * @property \Illuminate\Support\Carbon|null $delivered_at
 * @property \Illuminate\Support\Carbon|null $read_at
 * @property \Illuminate\Support\Carbon|null $edited_at
 * @property int|null $original_language_id
 * @property string|null $original_text
 * @property array|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $delete_comment
 */
class MetaChatMessage extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'meta_chat_messages';

    protected $guarded = [];

    protected $casts = [
        'message_type' => ChatMessageTypeEnum::class,
        'sender_type' => ChatSenderTypeEnum::class,
        'is_ai_generated' => 'boolean',
        'is_validated' => 'boolean',
        'is_read' => 'boolean',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'edited_at' => 'datetime',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function metaChannel(): BelongsTo
    {
        return $this->belongsTo(MetaChannel::class);
    }

    public function metaChatSession(): BelongsTo
    {
        return $this->belongsTo(MetaChatSession::class);
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
    }

    public function originalLanguage(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'original_language_id');
    }
}
