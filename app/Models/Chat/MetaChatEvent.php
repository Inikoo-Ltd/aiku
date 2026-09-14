<?php

namespace App\Models\Chat;

use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $meta_channel_id
 * @property int|null $meta_chat_session_id
 * @property ChatEventTypeEnum|null $event_type
 * @property ChatActorTypeEnum|null $actor_type
 * @property int|null $actor_id
 * @property array|null $payload
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class MetaChatEvent extends Model
{
    use HasFactory;

    protected $table = 'meta_chat_events';

    protected $guarded = [];

    protected $casts = [
        'event_type' => ChatEventTypeEnum::class,
        'actor_type' => ChatActorTypeEnum::class,
        'payload' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function metaChannel(): BelongsTo
    {
        return $this->belongsTo(MetaChannel::class);
    }

    public function metaChatSession(): BelongsTo
    {
        return $this->belongsTo(MetaChatSession::class);
    }
}
