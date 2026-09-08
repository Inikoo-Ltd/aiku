<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 22 Aug 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Chat;

use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $group_id
 * @property string $ulid
 * @property string $type
 * @property string|null $name
 * @property string|null $dm_key
 * @property \Illuminate\Support\Carbon|null $last_message_at
 */
class StaffConversation extends Model
{
    protected $guarded = [];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (StaffConversation $conversation) {
            $conversation->ulid ??= (string) Str::ulid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'staff_conversation_participants')
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(StaffMessage::class);
    }

    public function context(): MorphTo
    {
        return $this->morphTo();
    }

    public function hasParticipant(User $user): bool
    {
        return $this->participants()->where('users.id', $user->id)->exists();
    }

    public static function dmKey(array $userIds): string
    {
        sort($userIds);

        return implode('-', $userIds);
    }
}
