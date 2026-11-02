<?php

namespace App\Models\Chat;

use App\Actions\Chat\ChatSession\DraftChatReply;
use App\Enums\CRM\Livechat\ChatAiDraftStatusEnum;
use App\Enums\CRM\Livechat\ChatAiOutcomeEnum;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Models\Helpers\Ticket;
use App\Models\Tasks\StaffTask;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatSessionClosedByTypeEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Models\CRM\Customer;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Language;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $meta_channel_id
 * @property int|null $shop_id
 * @property int|null $customer_id
 * @property string $ulid
 * @property ChatSessionStatusEnum $status
 * @property string|null $guest_identifier
 * @property string|null $ai_model_version
 * @property int $language_id
 * @property int|null $active_user_language_id
 * @property int|null $user_language_id
 * @property int|null $agent_language_id
 * @property ChatPriorityEnum $priority
 * @property int|null $rating
 * @property ChatSessionClosedByTypeEnum|null $closed_by
 * @property bool $is_spam
 * @property \Illuminate\Support\Carbon|null $spam_at
 * @property int|null $spammed_by_agent_id
 * @property bool $is_highlighted
 * @property \Illuminate\Support\Carbon|null $highlighted_at
 * @property int|null $highlighted_by_agent_id
 * @property array|null $metadata
 * @property string|null $geo_country_code
 * @property int|null $website_visitor_id
 * @property \Illuminate\Support\Carbon|null $last_visitor_message_at
 * @property \Illuminate\Support\Carbon|null $last_agent_message_at
 * @property \Illuminate\Support\Carbon|null $closed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $delete_comment
 * @property int|null $ai_claim_run_id
 * @property \Illuminate\Support\Carbon|null $ai_claimed_at
 * @property-read \Illuminate\Support\Carbon|null $whatsapp_window_closes_at
 * @property-read bool $can_send_non_template_message
 * @property-read int $whatsapp_window_seconds_left
 */
class MetaChatSession extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'meta_chat_sessions';

    protected $guarded = [];

    protected $casts = [
        'status' => ChatSessionStatusEnum::class,
        'priority' => ChatPriorityEnum::class,
        'closed_by' => ChatSessionClosedByTypeEnum::class,
        'metadata' => 'array',
        'is_spam' => 'boolean',
        'spam_at' => 'datetime',
        'is_highlighted' => 'boolean',
        'highlighted_at' => 'datetime',
        'last_visitor_message_at' => 'datetime',
        'last_agent_message_at' => 'datetime',
        'closed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'ai_claimed_at' => 'datetime',
    ];

    /**
     * The assistant leaves a draft only while this much of the 24-hour window is left, so staff
     * still have time to review it and send it as free text.
     */
    public const int AI_WHATSAPP_MIN_WINDOW_MINUTES = 60;

    /**
     * Leaves out the chats another AI run is still working, the same way as ChatSession.
     */
    public function scopeNotClaimedByAnotherAiRun($query, ?int $runId)
    {
        return ChatAiRun::whereNotClaimedByAnotherRun($query, $runId);
    }

    /**
     * The WhatsApp chats the hourly Aiku routine works: the customer wrote and nobody has opened it
     * since, nobody holds it, and Aiku recorded no outcome for the customer's latest message.
     */
    public function scopeUnopenedForAiku($query)
    {
        return $query->whereHas('messages', fn ($message) => $message->whereIn('sender_type', [ChatSenderTypeEnum::GUEST->value, ChatSenderTypeEnum::USER->value])->where('is_read', false))
            ->whereDoesntHave('assignments', fn ($assignment) => $assignment->where('status', ChatAssignmentStatusEnum::ACTIVE->value))
            ->notSettledByAikuSinceCustomerWrote();
    }

    /**
     * WhatsApp chats a colleague holds where the customer has waited
     * ChatSession::AI_HELD_UNANSWERED_MINUTES with no staff reply: the routine may leave a draft for
     * the colleague there, and nothing else.
     */
    public function scopeHeldUnansweredForAiku($query)
    {
        return $query->whereHas('assignments', fn ($assignment) => $assignment->where('status', ChatAssignmentStatusEnum::ACTIVE->value))
            ->where('last_visitor_message_at', '<=', now()->subMinutes(ChatSession::AI_HELD_UNANSWERED_MINUTES))
            ->where(fn ($unanswered) => $unanswered->whereNull('last_agent_message_at')->orWhereColumn('last_visitor_message_at', '>', 'last_agent_message_at'))
            ->notSettledByAikuSinceCustomerWrote();
    }

    /**
     * WhatsApp chats a colleague opened but nobody holds, where the customer has waited
     * ChatSession::AI_OPENED_UNANSWERED_MINUTES with no staff reply, and not yet
     * ChatSession::AI_OPENED_UNANSWERED_MAX_HOURS: the routine may leave a draft there, and nothing else.
     */
    public function scopeOpenedUnansweredForAiku($query)
    {
        return $query->whereDoesntHave('messages', fn ($message) => $message->whereIn('sender_type', [ChatSenderTypeEnum::GUEST->value, ChatSenderTypeEnum::USER->value])->where('is_read', false))
            ->whereDoesntHave('assignments', fn ($assignment) => $assignment->where('status', ChatAssignmentStatusEnum::ACTIVE->value))
            ->whereBetween('last_visitor_message_at', [now()->subHours(ChatSession::AI_OPENED_UNANSWERED_MAX_HOURS), now()->subMinutes(ChatSession::AI_OPENED_UNANSWERED_MINUTES)])
            ->where(fn ($unanswered) => $unanswered->whereNull('last_agent_message_at')->orWhereColumn('last_visitor_message_at', '>', 'last_agent_message_at'))
            ->notSettledByAikuSinceCustomerWrote();
    }

    public function scopeNotSettledByAikuSinceCustomerWrote($query)
    {
        return $query->whereDoesntHave('aiOutcomes', fn ($outcome) => $outcome->whereRaw(
            'chat_ai_outcomes.trigger_message_id = (select max(id) from meta_chat_messages where meta_chat_messages.meta_chat_session_id = meta_chat_sessions.id and meta_chat_messages.sender_type in (?, ?))',
            [ChatSenderTypeEnum::GUEST->value, ChatSenderTypeEnum::USER->value]
        )->where(fn ($settles) => $settles->where('chat_ai_outcomes.outcome', '!=', ChatAiOutcomeEnum::SUGGESTION_ALREADY_WAITING->value)
            ->orWhereExists(fn ($draft) => $draft->selectRaw('1')->from('chat_ai_drafts')
                ->whereColumn('chat_ai_drafts.meta_chat_session_id', 'meta_chat_sessions.id')
                ->where('chat_ai_drafts.status', ChatAiDraftStatusEnum::PENDING->value)
                ->whereRaw("chat_ai_drafts.facts->>'mode' = ?", [DraftChatReply::ASSISTANT])
                ->whereColumn('chat_ai_drafts.created_at', '>=', 'meta_chat_sessions.last_visitor_message_at'))));
    }

    public function metaChannel(): BelongsTo
    {
        return $this->belongsTo(MetaChannel::class);
    }

    /**
     * Who a WhatsApp message goes to: the phone number when we have one, which Meta prefers when
     * both are sent, and the business-scoped user id for a contact who only shows a username.
     *
     * @return array{to?: string, recipient?: string}
     */
    public function whatsappRecipient(): array
    {
        return array_filter([
            'to'        => preg_replace('/\D/', '', (string) $this->phone_number),
            'recipient' => $this->whatsapp_user_id,
        ]);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
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

    public function messages(): HasMany
    {
        return $this->hasMany(MetaChatMessage::class);
    }

    public function aiDrafts(): HasMany
    {
        return $this->hasMany(ChatAiDraft::class, 'meta_chat_session_id');
    }

    public function aiOutcomes(): HasMany
    {
        return $this->hasMany(ChatAiOutcome::class, 'meta_chat_session_id');
    }

    public function lastVisitorMessage(): HasMany
    {
        return $this->messages()->whereIn('sender_type', [
            ChatSenderTypeEnum::GUEST->value,
            ChatSenderTypeEnum::USER->value,
        ]);
    }

    /**
     * WhatsApp only takes free text within 24 hours of the customer's last message; our own replies do not extend it.
     */
    public function getWhatsappWindowClosesAtAttribute(): ?Carbon
    {
        $lastInboundAt = $this->relationLoaded('lastVisitorMessage')
            ? $this->lastVisitorMessage->first()?->created_at
            : $this->lastVisitorMessage()->latest()->first()?->created_at;

        return collect([$lastInboundAt, $this->last_visitor_message_at])->filter()->max()?->copy()->addDay();
    }

    public function getCanSendNonTemplateMessageAttribute(): bool
    {
        return $this->whatsapp_window_closes_at?->isFuture() ?? false;
    }

    /**
     * Sent as seconds rather than a timestamp so the countdown does not depend on the agent's computer clock.
     */
    public function getWhatsappWindowSecondsLeftAttribute(): int
    {
        $closesAt = $this->whatsapp_window_closes_at;

        return $closesAt ? max(0, (int) ceil(now()->diffInSeconds($closesAt, false))) : 0;
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

    public function assignments(): HasMany
    {
        return $this->hasMany(MetaChatAssignment::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(MetaChatEvent::class);
    }

    public function trackingEvents(): HasMany
    {
        return $this->hasMany(MetaTrackingEvent::class);
    }
}
