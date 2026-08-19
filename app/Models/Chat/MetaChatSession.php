<?php

namespace App\Models\Chat;

use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatSessionClosedByTypeEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Models\CRM\WebUser;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Language;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $meta_channel_id
 * @property int|null $shop_id
 * @property int|null $web_user_id
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
        'last_visitor_message_at' => 'datetime',
        'last_agent_message_at' => 'datetime',
        'closed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function metaChannel(): BelongsTo
    {
        return $this->belongsTo(MetaChannel::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function webUser(): BelongsTo
    {
        return $this->belongsTo(WebUser::class);
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

    public function lastVisitorMessage(): HasMany
    {
        return $this->messages()->whereIn('sender_type', [
            ChatSenderTypeEnum::GUEST->value,
            ChatSenderTypeEnum::USER->value,
        ]);
    }

    public function getCanSendNonTemplateMessageAttribute(): bool
    {
        $lastInboundAt = $this->relationLoaded('lastVisitorMessage')
            ? $this->lastVisitorMessage->first()?->created_at
            : $this->lastVisitorMessage()->latest()->first()?->created_at;

        return $lastInboundAt !== null && $lastInboundAt->gt(now()->subDay());
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(MetaChatAssignment::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(MetaChatEvent::class);
    }
}
