<?php

namespace App\Models\CRM\Livechat;

use App\Models\Media;
use Illuminate\Database\Eloquent\Model;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int $id
 * @property int|null $chat_session_id
 * @property ChatMessageTypeEnum $message_type
 * @property ChatSenderTypeEnum $sender_type
 * @property int|null $sender_id
 * @property string|null $message_text
 * @property int|null $media_id
 * @property bool $is_read
 * @property \Illuminate\Support\Carbon|null $delivered_at
 * @property \Illuminate\Support\Carbon|null $read_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\CRM\Livechat\ChatSession|null $chatSession
 * @property-read Model|\Eloquent|null $sender
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatMessage fromSenderType(\App\Enums\CRM\Livechat\ChatSenderTypeEnum $senderType)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatMessage newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatMessage newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatMessage onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatMessage query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatMessage textMessages()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatMessage unread()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatMessage withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatMessage withoutTrashed()
 * @mixin \Eloquent
 */
class ChatMessage extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'chat_messages';

    protected $casts = [
        'message_type' => ChatMessageTypeEnum::class,
        'sender_type' => ChatSenderTypeEnum::class,
        'is_read' => 'boolean',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'deleted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $guarded = [];


    public function chatSession(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class);
    }


    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }


    public function sender(): MorphTo
    {
        return $this->morphTo();
    }


    public function markAsDelivered(): void
    {
        if (!$this->delivered_at) {
            $this->update(['delivered_at' => now()]);
        }
    }


    public function markAsRead(): void
    {
        if (!$this->is_read) {
            $this->update([
                'is_read' => true,
                'read_at' => now()
            ]);
        }
    }


    public function isFromUser(): bool
    {
        return $this->sender_type === ChatSenderTypeEnum::USER;
    }


    public function isFromAgent(): bool
    {
        return $this->sender_type === ChatSenderTypeEnum::AGENT;
    }


    public function isFromAI(): bool
    {
        return $this->sender_type === ChatSenderTypeEnum::AI;
    }


    public function isText(): bool
    {
        return $this->message_type === ChatMessageTypeEnum::TEXT;
    }


    public function isImage(): bool
    {
        return $this->message_type === ChatMessageTypeEnum::IMAGE;
    }


    public function isFile(): bool
    {
        return $this->message_type === ChatMessageTypeEnum::FILE;
    }


    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }


    public function scopeFromSenderType($query, ChatSenderTypeEnum $senderType)
    {
        return $query->where('sender_type', $senderType);
    }


    public function scopeTextMessages($query)
    {
        return $query->where('message_type', ChatMessageTypeEnum::TEXT);
    }
}
