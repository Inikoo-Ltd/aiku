<?php

namespace App\Models\CRM\Livechat;


use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CRM\Livechat\ChatAssignment> $assignments
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


    public function isAvailableForChat(): bool
    {
        return $this->is_online
            && $this->is_available
            && $this->current_chat_count < $this->max_concurrent_chats;
    }



    public function incrementChatCount(): void
    {
        $this->increment('current_chat_count');
    }


    public function decrementChatCount(): void
    {
        $this->decrement('current_chat_count');
    }


    public function setOnline(bool $online = true): void
    {
        $this->update(['is_online' => $online]);
    }


    public function hasSpecialization(string $specialization): bool
    {
        return in_array($specialization, $this->specialization ?? []);
    }


    public function scopeOnline($query)
    {
        return $query->where('is_online', true);
    }


    public function scopeAvailable($query)
    {
        return $query->where('is_available', true)
                    ->where('is_online', true)
                    ->whereColumn('current_chat_count', '<', 'max_concurrent_chats');
    }


    public function scopeWithSpecialization($query, string $specialization)
    {
        return $query->whereJsonContains('specialization', $specialization);
    }


    public static function findAvailableAgent(?array $requiredSpecializations = null): ?self
    {
        $query = self::available();

        if ($requiredSpecializations) {
            foreach ($requiredSpecializations as $specialization) {
                $query->whereJsonContains('specialization', $specialization);
            }
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
            'is_online' => $this->is_online,
            'is_available' => $this->is_available,
            'specializations' => $this->specialization ?? [],
        ];
    }
}