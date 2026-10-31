<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jun 2026 21:07:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Chat;

use App\Actions\Chat\ChatSession\DraftChatReply;
use App\Enums\CRM\Livechat\ChatAiDraftStatusEnum;
use App\Enums\CRM\Livechat\ChatAiOutcomeEnum;
use App\Models\Helpers\Ticket;
use App\Models\Tasks\StaffTask;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatChannelEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\CRM\Livechat\ChatSessionClosedByTypeEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\WebUser;
use App\Models\Helpers\Language;
use App\Models\Traits\HasHistory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property int|null $web_user_id
 * @property string $ulid
 * @property ChatSessionStatusEnum $status
 * @property string|null $guest_identifier Random alias we use to identify the guest
 * @property string|null $ai_model_version
 * @property int $language_id
 * @property ChatPriorityEnum $priority
 * @property float|null $rating
 * @property ChatSessionClosedByTypeEnum|null $closed_by
 * @property string|null $last_visitor_message_at
 * @property string|null $last_agent_message_at
 * @property \Illuminate\Support\Carbon|null $closed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property int|null $shop_id
 * @property int|null $active_user_language_id
 * @property int|null $user_language_id
 * @property int|null $agent_language_id
 * @property array<array-key, mixed>|null $metadata
 * @property string|null $geo_country_code
 * @property int|null $website_visitor_id
 * @property int|null $ai_claim_run_id
 * @property \Illuminate\Support\Carbon|null $ai_claimed_at
 * @property-read Language|null $activeUserLanguage
 * @property-read Language|null $agentLanguage
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Chat\ChatAssignment> $assignments
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Chat\ChatEvent> $chatEvents
 * @property-read Language $language
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Chat\ChatMessage> $messages
 * @property-read Shop|null $shop
 * @property-read Language|null $userLanguage
 * @property-read WebUser|null $webUser
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatSession newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatSession newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatSession query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatSession withLastMessageTime()
 * @mixin \Eloquent
 */
class ChatSession extends Model implements Auditable
{
    use HasFactory;
    use SoftDeletes;
    use HasHistory;

    public function generateTags(): array
    {
        return ['crm'];
    }

    protected array $auditInclude = [
        'web_user_id',
        'suggested_customer_id',
        'suggestion_basis',
        'suggestion_rejected_at',
    ];

    protected $table = 'chat_sessions';


    protected $casts = [
        'status' => ChatSessionStatusEnum::class,
        'channel' => ChatChannelEnum::class,
        'priority' => ChatPriorityEnum::class,
        'closed_by' => ChatSessionClosedByTypeEnum::class,
        'closed_at' => 'datetime',
        'rating' => 'decimal:1',
        'metadata' => 'array',
        'is_spam' => 'boolean',
        'is_carrier' => 'boolean',
        'is_colleague' => 'boolean',
        'spam_at' => 'datetime',
        'is_highlighted' => 'boolean',
        'highlighted_at' => 'datetime',
        'deleted_at' => 'datetime',
        'ai_claimed_at' => 'datetime',
    ];

    public const int AI_CLAIM_HOURS = 2;

    public const int AI_HELD_UNANSWERED_MINUTES = 30;

    public const int AI_OPENED_UNANSWERED_MINUTES = 120;

    public const int AI_OPENED_UNANSWERED_MAX_HOURS = 48;

    protected $guarded = [];

    public function scopeNotSpam($query)
    {
        return $query->where('is_spam', false);
    }

    public function scopeSpam($query)
    {
        return $query->where('is_spam', true);
    }

    /**
     * Leaves out the chats another AI run is still working. A claim lapses once its run has been quiet
     * for ChatAiRun::IDLE_MINUTES, and after AI_CLAIM_HOURS in any case, so a run that died without
     * calling finish_run does not keep its chats from the next one.
     */
    public function scopeNotClaimedByAnotherAiRun($query, ?int $runId)
    {
        return ChatAiRun::whereNotClaimedByAnotherRun($query, $runId);
    }

    /**
     * The chats the hourly Aiku routine works: the customer wrote and nobody has opened it since,
     * nobody holds it, and Aiku left no note and recorded no outcome for the customer's latest message.
     */
    public function scopeUnopenedForAiku($query)
    {
        return $query->whereHas('messages', fn ($message) => $message->whereIn('sender_type', [ChatSenderTypeEnum::GUEST->value, ChatSenderTypeEnum::USER->value])->where('is_read', false))
            ->whereDoesntHave('assignments', fn ($assignment) => $assignment->where('status', ChatAssignmentStatusEnum::ACTIVE->value))
            ->notSettledByAikuSinceCustomerWrote();
    }

    /**
     * Chats a colleague holds where the customer has waited AI_HELD_UNANSWERED_MINUTES with no staff
     * reply: the routine may leave a draft for the colleague there, and nothing else.
     */
    public function scopeHeldUnansweredForAiku($query)
    {
        return $query->whereHas('assignments', fn ($assignment) => $assignment->where('status', ChatAssignmentStatusEnum::ACTIVE->value))
            ->where('last_visitor_message_at', '<=', now()->subMinutes(self::AI_HELD_UNANSWERED_MINUTES))
            ->where(fn ($unanswered) => $unanswered->whereNull('last_agent_message_at')->orWhereColumn('last_visitor_message_at', '>', 'last_agent_message_at'))
            ->notSettledByAikuSinceCustomerWrote();
    }

    /**
     * Chats a colleague opened but nobody holds, where the customer has waited AI_OPENED_UNANSWERED_MINUTES
     * with no staff reply, and not yet AI_OPENED_UNANSWERED_MAX_HOURS (older drafts go unused): the
     * routine may leave a draft there, and nothing else.
     */
    public function scopeOpenedUnansweredForAiku($query)
    {
        return $query->whereDoesntHave('messages', fn ($message) => $message->whereIn('sender_type', [ChatSenderTypeEnum::GUEST->value, ChatSenderTypeEnum::USER->value])->where('is_read', false))
            ->whereDoesntHave('assignments', fn ($assignment) => $assignment->where('status', ChatAssignmentStatusEnum::ACTIVE->value))
            ->whereBetween('last_visitor_message_at', [now()->subHours(self::AI_OPENED_UNANSWERED_MAX_HOURS), now()->subMinutes(self::AI_OPENED_UNANSWERED_MINUTES)])
            ->where(fn ($unanswered) => $unanswered->whereNull('last_agent_message_at')->orWhereColumn('last_visitor_message_at', '>', 'last_agent_message_at'))
            ->notSettledByAikuSinceCustomerWrote();
    }

    public function scopeNotSettledByAikuSinceCustomerWrote($query)
    {
        return $query->whereNot(fn ($noted) => $noted->whereHas('chatEvents', fn ($event) => $event->where('event_type', ChatEventTypeEnum::INTERNAL_NOTE->value)
                ->whereColumn('chat_events.created_at', '>', 'chat_sessions.last_visitor_message_at')))
            ->whereDoesntHave('aiOutcomes', fn ($outcome) => $outcome->whereRaw(
                'chat_ai_outcomes.trigger_message_id = (select max(id) from chat_messages where chat_messages.chat_session_id = chat_sessions.id and chat_messages.sender_type in (?, ?))',
                [ChatSenderTypeEnum::GUEST->value, ChatSenderTypeEnum::USER->value]
            )->where(fn ($settles) => $settles->where('chat_ai_outcomes.outcome', '!=', ChatAiOutcomeEnum::SUGGESTION_ALREADY_WAITING->value)
                ->orWhereExists(fn ($draft) => $draft->selectRaw('1')->from('chat_ai_drafts')
                    ->whereColumn('chat_ai_drafts.chat_session_id', 'chat_sessions.id')
                    ->where('chat_ai_drafts.status', ChatAiDraftStatusEnum::PENDING->value)
                    ->whereRaw("chat_ai_drafts.facts->>'mode' = ?", [DraftChatReply::ASSISTANT])
                    ->whereColumn('chat_ai_drafts.created_at', '>=', 'chat_sessions.last_visitor_message_at'))));
    }


    public function webUser(): BelongsTo
    {
        return $this->belongsTo(WebUser::class, 'web_user_id');
    }

    public function chatEvents(): HasMany
    {
        return $this->hasMany(ChatEvent::class, 'chat_session_id');
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'chat_session_id');
    }

    public function aiDrafts(): HasMany
    {
        return $this->hasMany(ChatAiDraft::class);
    }

    public function aiOutcomes(): HasMany
    {
        return $this->hasMany(ChatAiOutcome::class);
    }

    /**
     * Raised off this conversation, so the thread can say what is outstanding on it.
     */
    public function tickets(): MorphMany
    {
        return $this->morphMany(Ticket::class, 'source');
    }

    /**
     * Colleagues asked to do something for this conversation, e.g. chase a supplier for a document; the chat waits on the open ones.
     */
    public function staffTasks(): MorphMany
    {
        return $this->morphMany(StaffTask::class, 'model');
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(ChatLabel::class)->withPivot('created_at');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ChatAssignment::class, 'chat_session_id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }


    public function getRatingAttribute($value): float|null
    {
        return $value ? round($value, 1) : null;
    }

    public function setRatingAttribute($value): void
    {
        if ($value !== null) {
            $value = max(1, min(5, round($value, 1)));
        }
        $this->attributes['rating'] = $value;
    }

    public function scopeWithLastMessageTime($query)
    {
        return $query->addSelect([
            'last_message_at' => ChatMessage::select('created_at')
                ->whereColumn('chat_session_id', 'chat_sessions.id')
                ->latest()
                ->limit(1)
        ]);
    }
    public function userLanguage(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'user_language_id');
    }
    public function agentLanguage(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'agent_language_id');
    }
    public function activeUserLanguage(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'active_user_language_id');
    }

    public function isActive(): bool
    {
        return $this->status === ChatSessionStatusEnum::ACTIVE;
    }
    public function isClosed(): bool
    {
        return $this->status === ChatSessionStatusEnum::CLOSED;
    }

}
