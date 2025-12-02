<?php

namespace App\Models\CRM\Livechat;

use Illuminate\Database\Eloquent\Model;
use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Enums\CRM\Livechat\ChatEventActorTypeEnum;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int $id
 * @property int $chat_session_id
 * @property ChatEventTypeEnum|null $event_type
 * @property ChatActorTypeEnum|null $actor_type
 * @property int|null $actor_id
 * @property array<array-key, mixed>|null $payload
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\CRM\Livechat\ChatSession $chatSession
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatEvent fromActor(\App\Enums\CRM\Livechat\ChatActorTypeEnum $actorType, ?int $actorId = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatEvent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatEvent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatEvent ofType(\App\Enums\CRM\Livechat\ChatEventTypeEnum $eventType)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatEvent query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatEvent ratings()
 * @mixin \Eloquent
 */
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

    protected $guarded = [];

    public function chatSession(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class);
    }

    public function actor(): MorphTo
    {
        return $this->morphTo('actor', 'actor_type', 'actor_id');
    }

    public function isOpenEvent(): bool
    {
        return $this->event_type === ChatEventTypeEnum::OPEN;
    }


    public function isCloseEvent(): bool
    {
        return $this->event_type === ChatEventTypeEnum::CLOSE;
    }


    public function isTransferRequest(): bool
    {
        return $this->event_type === ChatEventTypeEnum::TRANSFER_REQUEST;
    }


    public function isTransferAccept(): bool
    {
        return $this->event_type === ChatEventTypeEnum::TRANSFER_ACCEPT;
    }


    public function isTransferReject(): bool
    {
        return $this->event_type === ChatEventTypeEnum::TRANSFER_REJECT;
    }


    public function isAiReply(): bool
    {
        return $this->event_type === ChatEventTypeEnum::AI_REPLY;
    }


    public function isRating(): bool
    {
        return $this->event_type === ChatEventTypeEnum::RATING;
    }


    public function isNote(): bool
    {
        return $this->event_type === ChatEventTypeEnum::NOTE;
    }


    public function isTranslateMessage(): bool
    {
        return $this->event_type === ChatEventTypeEnum::TRANSLATE_MESSAGE;
    }


    public function getRatingValue(): ?float
    {
        if (!$this->isRating() || !$this->payload) {
            return null;
        }

        return $this->payload['rating'] ?? null;
    }


    public function getNoteContent(): ?string
    {
        if (!$this->isNote() || !$this->payload) {
            return null;
        }

        return $this->payload['note'] ?? null;
    }

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


    public function scopeOfType($query, ChatEventTypeEnum $eventType)
    {
        return $query->where('event_type', $eventType);
    }


    public function scopeFromActor($query, ChatActorTypeEnum $actorType, ?int $actorId = null)
    {
        $query = $query->where('actor_type', $actorType);

        if ($actorId) {
            $query->where('actor_id', $actorId);
        }

        return $query;
    }

    public function scopeRatings($query)
    {
        return $query->where('event_type', ChatEventTypeEnum::RATING);
    }

    public static function logEvent(
        int $sessionId,
        ChatEventActorTypeEnum $eventType,
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
