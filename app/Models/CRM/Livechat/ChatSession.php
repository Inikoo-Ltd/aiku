<?php

namespace App\Models\CRM\Livechat;

use App\Models\CRM\WebUser;
use App\Models\Helpers\Language;
use Illuminate\Database\Eloquent\Model;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Enums\CRM\Livechat\ChatSessionClosedByTypeEnum;

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
 * @property-read Language $language
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CRM\Livechat\ChatMessage> $messages
 * @property-read WebUser|null $webUser
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatSession newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatSession newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatSession query()
 * @mixin \Eloquent
 */
class ChatSession extends Model
{
    use HasFactory;
    protected $table = 'chat_sessions';


    protected $casts = [
        'status' => ChatSessionStatusEnum::class,
        'priority' => ChatPriorityEnum::class,
        'closed_by' => ChatSessionClosedByTypeEnum::class,
        'closed_at' => 'datetime',
        'rating' => 'decimal:1',
    ];

    protected $guarded = [];


    public function webUser()
    {
        return $this->belongsTo(WebUser::class, 'web_user_id');
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'chat_session_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ChatAssignment::class, 'chat_session_id');
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


}
