<?php

namespace App\Models\CRM\Livechat;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ChatAgent extends Model
{
     use HasFactory, SoftDeletes;

    protected $table = 'chat_agents';

    protected $casts = [
        'is_online' => 'boolean',
        'auto_accept' => 'boolean',
        'is_available' => 'integer',
        'specialization' => 'array',
        'deleted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $fillable = [
        'user_id',
        'max_concurrent_chats',
        'is_online',
        'current_chat_count',
        'specialization',
        'auto_accept',
        'is_available',
    ];

     protected static function booted(): void
    {
        static::creating(function (ChatAgent $agent) {
            // check, what if user_id have activated
            $existing = static::where('user_id', $agent->user_id)
                ->whereNull('deleted_at')
                ->exists();

            if ($existing) {
                throw new \Exception('User already has an active chat agent profile.');
            }
        });

        static::updating(function (ChatAgent $agent) {
            // check if user change
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
        // checl what if there agent that active with same user_id
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
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * Get the chat assignments for the agent.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(ChatAssignment::class, 'agent_id');
    }

    /**
     * Get the chat sessions assigned to the agent.
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(ChatSession::class, 'agent_id');
    }

    /**
     * Check if agent is available to take new chats.
     */
    public function isAvailableForChat(): bool
    {
        return $this->is_online
            && $this->is_available
            && $this->current_chat_count < $this->max_concurrent_chats;
    }

    /**
     * Get available slots for new chats.
     */
    public function getAvailableSlots(): int
    {
        return max(0, $this->max_concurrent_chats - $this->current_chat_count);
    }

    /**
     * Increment current chat count.
     */
    public function incrementChatCount(): void
    {
        $this->increment('current_chat_count');
    }

    /**
     * Decrement current chat count.
     */
    public function decrementChatCount(): void
    {
        $this->decrement('current_chat_count');
    }

    /**
     * Set agent online status.
     */
    public function setOnline(bool $online = true): void
    {
        $this->update(['is_online' => $online]);
    }

    /**
     * Check if agent has specific specialization.
     */
    public function hasSpecialization(string $specialization): bool
    {
        return in_array($specialization, $this->specialization ?? []);
    }

    /**
     * Add specialization to agent.
     */
    public function addSpecialization(string $specialization): void
    {
        $specializations = $this->specialization ?? [];
        if (!in_array($specialization, $specializations)) {
            $specializations[] = $specialization;
            $this->update(['specialization' => $specializations]);
        }
    }

    /**
     * Remove specialization from agent.
     */
    public function removeSpecialization(string $specialization): void
    {
        $specializations = $this->specialization ?? [];
        $key = array_search($specialization, $specializations);
        if ($key !== false) {
            unset($specializations[$key]);
            $this->update(['specialization' => array_values($specializations)]);
        }
    }

    /**
     * Scope a query to only include online agents.
     */
    public function scopeOnline($query)
    {
        return $query->where('is_online', true);
    }

    /**
     * Scope a query to only include available agents.
     */
    public function scopeAvailable($query)
    {
        return $query->where('is_available', true)
                    ->where('is_online', true)
                    ->whereColumn('current_chat_count', '<', 'max_concurrent_chats');
    }

    /**
     * Scope a query to only include agents with specific specialization.
     */
    public function scopeWithSpecialization($query, string $specialization)
    {
        return $query->whereJsonContains('specialization', $specialization);
    }

    /**
     * Find available agent for chat assignment.
     */
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

    /**
     * Get agent statistics.
     */
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
