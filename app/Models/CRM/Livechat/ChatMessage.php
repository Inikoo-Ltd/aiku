<?php

namespace App\Models\CRM\Livechat;

use App\Models\Media;

use App\Models\CRM\WebUser;
use App\Models\CRM\Livechat\ChatAgent;
use Illuminate\Database\Eloquent\Model;
use App\Models\CRM\Livechat\ChatSession;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ChatMessage extends Model
{
    use HasFactory, SoftDeletes;

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