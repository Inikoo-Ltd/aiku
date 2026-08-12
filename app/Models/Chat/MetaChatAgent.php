<?php

namespace App\Models\Chat;

use App\Enums\CRM\Livechat\ChatAgentPresenceStatusEnum;
use App\Models\Helpers\Language;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use App\Models\Catalogue\Shop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $meta_channel_id
 * @property int $user_id
 * @property int $max_concurrent_chats
 * @property bool $is_online
 * @property bool $is_available
 * @property int $current_chat_count
 * @property array|null $specialization
 * @property bool $auto_accept
 * @property int|null $language_id
 * @property ChatAgentPresenceStatusEnum $presence_status
 * @property \Illuminate\Support\Carbon|null $last_heartbeat_at
 * @property \Illuminate\Support\Carbon|null $last_activity_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $delete_comment
 */
class MetaChatAgent extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'meta_chat_agents';

    protected $guarded = [];

    protected $casts = [
        'is_online' => 'boolean',
        'is_available' => 'boolean',
        'auto_accept' => 'boolean',
        'specialization' => 'array',
        'language_id' => 'integer',
        'presence_status' => ChatAgentPresenceStatusEnum::class,
        'last_heartbeat_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function metaChannel(): BelongsTo
    {
        return $this->belongsTo(MetaChannel::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(MetaChatAssignment::class);
    }

    public function shopAssignments(): HasMany
    {
        return $this->hasMany(ShopHasMetaChatAgent::class);
    }

    public function shops(): BelongsToMany
    {
        return $this->belongsToMany(Shop::class, 'shop_has_meta_chat_agents', 'meta_chat_agent_id', 'shop_id')
            ->withPivot('organisation_id', 'meta_channel_id')
            ->withTimestamps();
    }

    public function organisations(): BelongsToMany
    {
        return $this->belongsToMany(Organisation::class, 'shop_has_meta_chat_agents', 'meta_chat_agent_id', 'organisation_id')
            ->withPivot('shop_id', 'meta_channel_id')
            ->withTimestamps();
    }
}
