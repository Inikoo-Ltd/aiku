<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jun 2026 21:07:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Chat;

use App\Enums\CRM\Livechat\ChatAgentPresenceStatusEnum;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Language;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $max_concurrent_chats
 * @property bool $is_online
 * @property int $is_available
 * @property int $current_chat_count
 * @property array<array-key, mixed>|null $specialization
 * @property bool $auto_accept
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property int|null $language_id
 * @property ChatAgentPresenceStatusEnum $presence_status
 * @property \Illuminate\Support\Carbon|null $last_heartbeat_at
 * @property \Illuminate\Support\Carbon|null $last_activity_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Chat\ChatAssignment> $assignments
 * @property-read Language|null $language
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Organisation> $organisations
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Chat\ShopHasChatAgent> $shopAssignments
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Shop> $shops
 * @property-read User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatAgent available()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatAgent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatAgent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatAgent online()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatAgent onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatAgent query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatAgent withSpecialization(string $specialization)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatAgent withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatAgent withoutTrashed()
 * @mixin \Eloquent
 */
class ChatAgent extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'chat_agents';

    protected $guarded = [];

    protected $casts = [
        'is_online' => 'boolean',
        'auto_accept' => 'boolean',
        'is_available' => 'integer',
        'specialization' => 'array',
        'language_id'    => 'integer',
        'presence_status' => ChatAgentPresenceStatusEnum::class,
        'last_heartbeat_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'deleted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];


    protected static function booted(): void
    {
        static::creating(function (ChatAgent $agent) {
            $existing = static::where('user_id', $agent->user_id)
                ->whereNull('deleted_at')
                ->exists();

            if ($existing) {
                throw new \Exception('User already has an active chat agent profile.');
            }
        });

        static::updating(function (ChatAgent $agent) {
            if ($agent->isDirty('user_id')) {
                $existing = static::where('user_id', $agent->user_id)
                    ->whereNull('deleted_at')
                    ->where('id', '!=', $agent->id)
                    ->exists();

                if ($existing) {
                    throw new \Exception('Another active agent already exists for this user.');
                }
            }
        });
    }

    public function restoreWithValidation(): bool
    {
        $existing = static::where('user_id', $this->user_id)
            ->whereNull('deleted_at')
            ->where('id', '!=', $this->id)
            ->exists();

        if ($existing) {
            throw new \Exception('Cannot restore: Another active agent exists for this user.');
        }

        return $this->restore();
    }


    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }


    public function assignments(): HasMany
    {
        return $this->hasMany(ChatAssignment::class, 'chat_agent_id');
    }

    public function shopAssignments()
    {
        return $this->hasMany(ShopHasChatAgent::class);
    }

    public function shops()
    {
        return $this->belongsToMany(Shop::class, 'shop_has_chat_agents')
            ->withPivot(['organisation_id'])
            ->withTimestamps();
    }

    public function organisations()
    {
        return $this->belongsToMany(Organisation::class, 'shop_has_chat_agents')
            ->withPivot(['shop_id'])
            ->withTimestamps();
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }


    public function isAvailableForChat(): bool
    {
        return $this->isOnline()
            && $this->is_available
            && $this->current_chat_count < $this->max_concurrent_chats;
    }

    public function isAvailable()
    {
        return $this->current_chat_count < $this->max_concurrent_chats;
    }


    public function hasFreshHeartbeat(): bool
    {
        return $this->last_heartbeat_at?->gt(self::heartbeatCutOff()) ?? false;
    }


    public function isConnected(): bool
    {
        return $this->presenceStatus() !== ChatAgentPresenceStatusEnum::OFFLINE;
    }


    public function isOnline(): bool
    {
        return $this->presence_status === ChatAgentPresenceStatusEnum::ONLINE
            && $this->hasFreshHeartbeat();
    }


    public function isAway(): bool
    {
        return $this->presence_status === ChatAgentPresenceStatusEnum::AWAY
            && $this->hasFreshHeartbeat();
    }


    public function presenceStatus(): ChatAgentPresenceStatusEnum
    {
        return $this->hasFreshHeartbeat()
            ? $this->presence_status
            : ChatAgentPresenceStatusEnum::OFFLINE;
    }


    public static function heartbeatCutOff(): Carbon
    {
        return now()->subSeconds((int) config('chat.presence.offline_after_seconds'));
    }


    public function setOnline(bool $online = true): void
    {
        $this->update([
            'is_online'         => $online,
            'presence_status'   => $online ? ChatAgentPresenceStatusEnum::ONLINE : ChatAgentPresenceStatusEnum::OFFLINE,
            'last_heartbeat_at' => $online ? now() : null,
            'last_activity_at'  => $online ? now() : $this->last_activity_at,
        ]);
    }


    public function hasSpecialization(string $specialization): bool
    {
        return in_array($specialization, $this->specialization ?? []);
    }


    public function scopeOnline($query)
    {
        return $query->where('presence_status', ChatAgentPresenceStatusEnum::ONLINE)
            ->where('last_heartbeat_at', '>', self::heartbeatCutOff());
    }


    public function scopeAvailable($query)
    {
        return $query->online()
            ->where('is_available', true)
            ->whereColumn('current_chat_count', '<', 'max_concurrent_chats');
    }


    public function scopeWithSpecialization($query, string $specialization)
    {
        return $query->whereJsonContains('specialization', $specialization);
    }


    public static function findAvailableAgent(?array $requiredSpecializations = null, ?int $shopId = null): ?self
    {
        $query = self::available();

        if ($requiredSpecializations) {
            foreach ($requiredSpecializations as $specialization) {
                $query->whereJsonContains('specialization', $specialization);
            }
        }

        if ($shopId) {
            $organisationId = Shop::where('id', $shopId)->value('organisation_id');

            $query->whereHas('shopAssignments', function ($assignment) use ($shopId, $organisationId) {
                $assignment->where('shop_id', $shopId)
                    ->orWhere(function ($orgWide) use ($organisationId) {
                        $orgWide->whereNull('shop_id')
                            ->where('organisation_id', $organisationId);
                    });
            });
        }

        return $query->inRandomOrder()->first();
    }


    public function getAvailableSlots(): int
    {
        return max(0, $this->max_concurrent_chats - $this->current_chat_count);
    }


    public function getStats(): array
    {
        return [
            'current_chats' => $this->current_chat_count,
            'max_chats' => $this->max_concurrent_chats,
            'available_slots' => $this->getAvailableSlots(),
            'is_online' => $this->isConnected(),
            'is_available' => $this->is_available,
            'presence_status' => $this->presenceStatus()->value,
            'last_heartbeat_at' => $this->last_heartbeat_at,
            'specializations' => $this->specialization ?? [],
        ];
    }
}
