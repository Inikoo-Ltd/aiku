<?php

namespace App\Models\CRM\Livechat;

use Illuminate\Database\Eloquent\Model;
use App\Models\CRM\Livechat\ChatSession;
use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ChatEvent extends Model
{
    use HasFactory;

    protected $table = 'chat_events';

    protected $casts = [
        'event_type' => ChatEventTypeEnum::class,
        'actor_type' => ChatActorTypeEnum::class,
        'payload' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $fillable = [
        'session_id',
        'event_type',
        'actor_type',
        'actor_id',
        'payload',
    ];

    /**
     * Get the chat session that owns the event.
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class);
    }

    /**
     * Get the actor model based on actor_type (polymorphic-like relationship).
     */
    public function actor()
    {
        if (!$this->actor_type || !$this->actor_id) {
            return null;
        }

        return match($this->actor_type) {
            ChatActorTypeEnum::USER => $this->belongsTo(WebUser::class, 'actor_id'),
            ChatActorTypeEnum::AGENT => $this->belongsTo(ChatAgent::class, 'actor_id'),
            ChatActorTypeEnum::GUEST => null,
            ChatActorTypeEnum::SYSTEM => null, // System events have no specific actor
            ChatActorTypeEnum::AI => null, // AI events have no specific actor
        };
    }

    /**
     * Check if event is an open event.
     */
    public function isOpenEvent(): bool
    {
        return $this->event_type === ChatEventTypeEnum::OPEN;
    }

    /**
     * Check if event is a close event.
     */
    public function isCloseEvent(): bool
    {
        return $this->event_type === ChatEventTypeEnum::CLOSE;
    }

    /**
     * Check if event is a transfer request.
     */
    public function isTransferRequest(): bool
    {
        return $this->event_type === ChatEventTypeEnum::TRANSFER_REQUEST;
    }

    /**
     * Check if event is a transfer accept.
     */
    public function isTransferAccept(): bool
    {
        return $this->event_type === ChatEventTypeEnum::TRANSFER_ACCEPT;
    }

    /**
     * Check if event is a transfer reject.
     */
    public function isTransferReject(): bool
    {
        return $this->event_type === ChatEventTypeEnum::TRANSFER_REJECT;
    }

    /**
     * Check if event is an AI reply.
     */
    public function isAiReply(): bool
    {
        return $this->event_type === ChatEventTypeEnum::AI_REPLY;
    }

    /**
     * Check if event is a rating.
     */
    public function isRating(): bool
    {
        return $this->event_type === ChatEventTypeEnum::RATING;
    }

    /**
     * Check if event is a note.
     */
    public function isNote(): bool
    {
        return $this->event_type === ChatEventTypeEnum::NOTE;
    }

    /**
     * Check if event involves translation.
     */
    public function isTranslateMessage(): bool
    {
        return $this->event_type === ChatEventTypeEnum::TRANSLATE_MESSAGE;
    }

    /**
     * Get the rating value from payload (if event is rating).
     */
    public function getRatingValue(): ?int
    {
        if (!$this->isRating() || !$this->payload) {
            return null;
        }

        return $this->payload['rating'] ?? null;
    }

    /**
     * Get the note content from payload (if event is note).
     */
    public function getNoteContent(): ?string
    {
        if (!$this->isNote() || !$this->payload) {
            return null;
        }

        return $this->payload['note'] ?? null;
    }

    /**
     * Get transfer details from payload (if event is transfer).
     */
    public function getTransferDetails(): ?array
    {
        if (!in_array($this->event_type, [
            ChatEventTypeEnum::TRANSFER_REQUEST,
            ChatEventTypeEnum::TRANSFER_ACCEPT,
            ChatEventTypeEnum::TRANSFER_REJECT
        ]) || !$this->payload) {
            return null;
        }

        return [
            'from_agent_id' => $this->payload['from_agent_id'] ?? null,
            'to_agent_id' => $this->payload['to_agent_id'] ?? null,
            'reason' => $this->payload['reason'] ?? null,
        ];
    }

    /**
     * Scope a query to only include events of specific type.
     */
    public function scopeOfType($query, ChatEventTypeEnum $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope a query to only include events from specific actor.
     */
    public function scopeFromActor($query, ChatActorTypeEnum $actorType, ?int $actorId = null)
    {
        $query = $query->where('actor_type', $actorType);

        if ($actorId) {
            $query->where('actor_id', $actorId);
        }

        return $query;
    }

    /**
     * Scope a query to only include rating events.
     */
    public function scopeRatings($query)
    {
        return $query->where('event_type', ChatEventTypeEnum::RATING);
    }

    /**
     * Scope a query to only include transfer events.
     */
    public function scopeTransfers($query)
    {
        return $query->whereIn('event_type', [
            ChatEventTypeEnum::TRANSFER_REQUEST,
            ChatEventTypeEnum::TRANSFER_ACCEPT,
            ChatEventTypeEnum::TRANSFER_REJECT
        ]);
    }

    /**
     * Create a new chat event with payload.
     */
    public static function logEvent(
        int $sessionId,
        ChatEventTypeEnum $eventType,
        ?ChatActorTypeEnum $actorType = null,
        ?int $actorId = null,
        ?array $payload = null
    ): self {
        return self::create([
            'session_id' => $sessionId,
            'event_type' => $eventType,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'payload' => $payload,
        ]);
    }
}
