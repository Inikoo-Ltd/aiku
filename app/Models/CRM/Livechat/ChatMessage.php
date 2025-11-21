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
 * @property-read Model|\Eloquent|null $sender
 * @property-read ChatSession|null $session
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

    protected $fillable = [
        'session_id',
        'message_type',
        'sender_type',
        'sender_id',
        'message_text',
        'media_id',
        'is_read',
        'delivered_at',
        'read_at',
    ];

    /**
     * Get the chat session that owns the message.
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class);
    }

    /**
     * Get the media associated with the message.
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }


    public function sender(): MorphTo
    {
        return $this->morphTo();
    }



    /**
     * Mark message as delivered.
     */
    public function markAsDelivered(): void
    {
        if (!$this->delivered_at) {
            $this->update(['delivered_at' => now()]);
        }
    }

    /**
     * Mark message as read.
     */
    public function markAsRead(): void
    {
        if (!$this->is_read) {
            $this->update([
                'is_read' => true,
                'read_at' => now()
            ]);
        }
    }

    /**
     * Check if message is from user.
     */
    public function isFromUser(): bool
    {
        return $this->sender_type === ChatSenderTypeEnum::USER;
    }

    /**
     * Check if message is from agent.
     */
    public function isFromAgent(): bool
    {
        return $this->sender_type === ChatSenderTypeEnum::AGENT;
    }

    /**
     * Check if message is from AI.
     */
    public function isFromAI(): bool
    {
        return $this->sender_type === ChatSenderTypeEnum::AI;
    }

    /**
     * Check if message is text type.
     */
    public function isText(): bool
    {
        return $this->message_type === ChatMessageTypeEnum::TEXT;
    }

    /**
     * Check if message is image type.
     */
    public function isImage(): bool
    {
        return $this->message_type === ChatMessageTypeEnum::IMAGE;
    }

    /**
     * Check if message is file type.
     */
    public function isFile(): bool
    {
        return $this->message_type === ChatMessageTypeEnum::FILE;
    }

    /**
     * Scope a query to only include unread messages.
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope a query to only include messages from specific sender type.
     */
    public function scopeFromSenderType($query, ChatSenderTypeEnum $senderType)
    {
        return $query->where('sender_type', $senderType);
    }

    /**
     * Scope a query to only include text messages.
     */
    public function scopeTextMessages($query)
    {
        return $query->where('message_type', ChatMessageTypeEnum::TEXT);
    }
}
